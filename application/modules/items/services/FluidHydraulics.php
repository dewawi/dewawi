<?php

class Items_Service_FluidHydraulics
{
	public const VERSION = 'known_flow_serial_parallel_hydraulics_v1';
	public const GRAVITY_M_S2 = 9.80665;

	public function pressure(stdClass $record, stdClass $fluid, string $sectionKey, stdClass $composition, string $temperature, string $flow): array
	{
		$schema=new Items_Service_TechnicalSchema(); $record=$schema->dataset($record); $fluid=$schema->dataset($fluid); if($record->dataset_type!=='pressure_loss_map' || $fluid->dataset_type!=='fluid_properties')throw new InvalidArgumentException('Pressure loss and fluid contracts required');
		$d=$record->data; $f=new Items_Service_FluidProperties(); $p=$f->state($fluid->data,$composition,$temperature);
		$this->sameFluid($d->fluid_ref,$fluid);
		[$c0,$c1,$cw]=$f::bracket($d->concentrations,$p['composition']->fraction); [$t0,$t1,$tw]=$f::bracket($d->temperatures_degC,$temperature); [$v0,$v1,$vw]=$f::bracket($d->volume_flows_m3_h,$flow);
		$sections=array_values(array_filter($d->sections,fn($s)=>$s->section_key===$sectionKey)); if(count($sections)!==1) throw new DomainException('pressure_loss_section_missing'); $grid=$sections[0]->pressure_losses;
		$at=function($c,$t)use($grid,$v0,$v1,$vw,$f){return $f::lerp((float)$grid[$c][$t][$v0],(float)$grid[$c][$t][$v1],$vw);};
		$drop=$f::lerp($f::lerp($at($c0,$t0),$at($c0,$t1),$tw),$f::lerp($at($c1,$t0),$at($c1,$t1),$tw),$cw)*$this->pressureFactor($d->pressure_unit);
		$r=['pressure_loss_Pa'=>$drop,'volume_flow_m3_h'=>(float)$flow,'fluid_state'=>$p,'dataset_key'=>$record->dataset_key,'revision'=>$record->revision]; $f->finite($r); return $r;
	}

	public function pipe(stdClass $pipe, array $state, float $flow): array
	{
		$pipe=(new Items_Service_TechnicalSchema())->fields($pipe,['model_id'=>['circular_laminar_poiseuille_v1'],'flow_condition'=>['steady_newtonian_fully_developed'],'length_m'=>'positive','internal_diameter_m'=>'positive','local_loss_coefficient'=>'nonnegative'],['segment_key'=>'key','edge_from'=>'key','edge_to'=>'key','source_locator'=>'text']);
		if(!isset($state['density_kg_m3'],$state['dynamic_viscosity_Pa_s']) || !is_finite($state['density_kg_m3']) || !is_finite($state['dynamic_viscosity_Pa_s']) || $state['density_kg_m3']<=0 || $state['dynamic_viscosity_Pa_s']<=0)throw new InvalidArgumentException('Positive pipe fluid properties required');
		if(!is_finite($flow) || $flow<=0 || (float)$pipe->internal_diameter_m**4<=0 || !is_finite((float)$pipe->internal_diameter_m**4))throw new DomainException('pipe_geometry_outside_numeric_range');
		$v=$flow/3600/(M_PI*(float)$pipe->internal_diameter_m**2/4); $re=$state['density_kg_m3']*abs($v)*(float)$pipe->internal_diameter_m/$state['dynamic_viscosity_Pa_s'];
		if($flow<=0 || $re>=2300 || !is_finite($re)) throw new DomainException('pipe_laminar_domain_not_satisfied');
		$friction=128*$state['dynamic_viscosity_Pa_s']*(float)$pipe->length_m*($flow/3600)/(M_PI*(float)$pipe->internal_diameter_m**4);
		$r=['pressure_loss_Pa'=>$friction+(float)$pipe->local_loss_coefficient*$state['density_kg_m3']*$v*$v/2,'reynolds_number'=>$re,'velocity_m_s'=>$v,'model_id'=>$pipe->model_id]; (new Items_Service_FluidProperties())->finite($r); return $r;
	}

	public function pump(stdClass $record, stdClass $fluid, stdClass $composition, stdClass $request, float $requiredPressure): array
	{
		$s=new Items_Service_TechnicalSchema(); $record=$s->dataset($record); $fluid=$s->dataset($fluid); if($record->dataset_type!=='pump_curve' || $fluid->dataset_type!=='fluid_properties' || !is_finite($requiredPressure) || $requiredPressure<0)throw new InvalidArgumentException('Pump/fluid contracts and nonnegative required pressure needed');
		$f=new Items_Service_FluidProperties(); $d=$record->data;
		$q=$s->fields($request,['temperature_degC'=>'number','volume_flow_m3_h'=>'positive','speed_rpm'=>'positive','frequency_Hz'=>'positive','operating_mode'=>'key']);
		$this->sameFluid($d->fluid_ref,$fluid); $p=$f->state($fluid->data,$composition,$q->temperature_degC);
		if($q->speed_rpm!==$d->speed_rpm || $q->frequency_Hz!==$d->frequency_Hz || $q->operating_mode!==$d->operating_mode) throw new DomainException('pump_operating_condition_mismatch');
		foreach(['temperature_degC','concentration','density_kg_m3','dynamic_viscosity_Pa_s'] as $k) { $v=$k==='concentration'?$p['composition']->fraction:($k==='temperature_degC'?$q->temperature_degC:$f::decimal($p[$k])); $limit=$d->limits->$k; if(Items_Service_TechnicalJson::compareDecimal($v,$limit->min)<0 || Items_Service_TechnicalJson::compareDecimal($v,$limit->max)>0) throw new DomainException('pump_fluid_applicability_violated'); }
		[$a,$b,$w]=$f::bracket(array_map(fn($p)=>$p->volume_flow_m3_h,$d->points),$q->volume_flow_m3_h); $left=$d->points[$a]; $right=$d->points[$b];
		$ordinate=$f::lerp((float)$left->ordinate,(float)$right->ordinate,$w); $pressure=$d->ordinate_unit==='m'?$ordinate*$p['density_kg_m3']*self::GRAVITY_M_S2:$ordinate*$this->pressureFactor($d->ordinate_unit);
		$hydraulic=$pressure*(float)$q->volume_flow_m3_h/3600; $reserve=$pressure-$requiredPressure; $r=['available_pressure_Pa'=>$pressure,'available_head_m'=>$pressure/($p['density_kg_m3']*self::GRAVITY_M_S2),'required_pressure_Pa'=>$requiredPressure,'reserve_Pa'=>$reserve,'duty_status'=>$reserve>=0?'sufficient':'insufficient','hydraulic_power_W'=>$hydraulic,'hydraulic_efficiency'=>null,'total_efficiency'=>null,'electrical_input_W'=>null,'electrical_status'=>'missing_evidence','fluid_state'=>$p];
		foreach(['hydraulic_efficiency','total_efficiency','electrical_input_W'] as $k) if($left->$k!==null && $right->$k!==null) $r[$k]=$f::lerp((float)$left->$k,(float)$right->$k,$w);
		if($r['electrical_input_W']===null && $r['total_efficiency']!==null) $r['electrical_input_W']=$hydraulic/$r['total_efficiency'];
		if($r['electrical_input_W']!==null) {
			if($r['electrical_input_W']+max(1e-6,$hydraulic*1e-8)<$hydraulic || ($r['total_efficiency']!==null && abs($r['electrical_input_W']*$r['total_efficiency']-$hydraulic)>max(1e-6,$hydraulic*1e-6))) throw new DomainException('pump_power_evidence_inconsistent');
			$r['electrical_status']='calculated';
		}
		$f->finite($r); return $r;
	}

	public function sameFluid(stdClass $ref, stdClass $fluid): void
	{
		if($ref->dataset_key!==$fluid->dataset_key || $ref->revision!==$fluid->revision || $fluid->dataset_type!=='fluid_properties') throw new InvalidArgumentException('Hydraulic fluid revision mismatch');
	}

	public function resolve(callable $resolve, stdClass $ref, string $type): stdClass
	{
		$r=(new Items_Service_TechnicalSchema())->dataset($resolve('technicaldataset',$ref->dataset_key,$ref->revision));
		if($r->dataset_key!==$ref->dataset_key || $r->revision!==$ref->revision || $r->dataset_type!==$type) throw new InvalidArgumentException('Fluid/hydraulic reference mismatch'); return $r;
	}

	public function component(stdClass $component, callable $resolve): stdClass
	{
		$p=(new Items_Service_TechnicalSchema())->profile($resolve('itemtechnicalprofile',$component->profile_key,$component->profile_revision));
		if($p->profile_key!==$component->profile_key || $p->revision!==$component->profile_revision || $p->item_ref!==$component->component_key || $p->role!==$component->role) throw new InvalidArgumentException('Hydraulic profile identity mismatch');
		$r=$this->resolve($resolve,$p->dataset_ref,'component_spec');
		if($r->data->component_key!==$component->component_key || $r->data->role!==$component->role || Items_Service_TechnicalJson::encode($r->data->ports)!==Items_Service_TechnicalJson::encode($component->ports)) throw new InvalidArgumentException('Hydraulic component identity mismatch'); return $r;
	}

	public function mapBinding(stdClass $spec, stdClass $map): void
	{
		$d=$map->data;
		if($spec->data->component_key!==$d->component_key || $spec->data->model!==$d->model || $spec->data->role!==$d->role || $d->measurement_boundary!=='flange_to_flange') throw new InvalidArgumentException('Pressure loss component mismatch');
		$expected=$spec->data->specification->sections??['whole']; $actual=array_map(fn($s)=>$s->section_key,$d->sections); sort($expected);sort($actual);
		if($actual!==$expected) throw new InvalidArgumentException('Pressure loss section coverage mismatch');
	}

	public function pumpBinding(stdClass $spec, stdClass $curve): void
	{
		$d=$curve->data; $p=$spec->data->specification;
		if($spec->data->role!=='pump' || $spec->data->component_key!==$d->component_key || $spec->data->model!==$d->model || ($p->speed_rpm!==null && $p->speed_rpm!==$d->speed_rpm) || ($p->frequency_Hz!==null && $p->frequency_Hz!==$d->frequency_Hz) || ($p->fluid_ref!==null && Items_Service_TechnicalJson::encode($p->fluid_ref)!==Items_Service_TechnicalJson::encode($d->fluid_ref))) throw new InvalidArgumentException('Pump curve component mismatch');
	}

	public function pathBinding(stdClass $machine, stdClass $loop, stdClass $path): void
	{
		$d=$path->data; $members=[]; $pumps=[];
		foreach($machine->components as $c) foreach($c->ports as $port=>$kind) if($kind==='liquid_in') foreach($loop->edges as $edge) if($edge[1]===$c->instance_key.'.'.$port) { if($c->role==='pump')$pumps[]=$c->instance_key;else $members[]=$c->instance_key; }
		$actual=$d->component_instances;sort($actual);sort($members);
		if($d->loop_key!==$loop->loop_key || $actual!==$members || count($pumps)>1 || $d->pump_instance!==($pumps[0]??null)) throw new InvalidArgumentException('Hydraulic path must cover the existing loop exactly');
		$connections=[]; foreach($d->connections as $connection) {
			$edge=[$connection->edge_from,$connection->edge_to]; $key=json_encode($edge,JSON_THROW_ON_ERROR);
			if(isset($connections[$key]) || !in_array($edge,$loop->edges,true))throw new InvalidArgumentException('Invalid hydraulic connection evidence');
			$connections[$key]=$connection->loss_basis;
			$pipes=array_filter($d->pipe_segments,fn($p)=>[$p->edge_from,$p->edge_to]===$edge);
			if(($connection->loss_basis==='explicit_pipe_segments')!==(bool)$pipes)throw new InvalidArgumentException('Connection/pipe loss evidence mismatch');
		}
		if(count($connections)!==count($loop->edges))throw new InvalidArgumentException('Every hydraulic connection requires loss evidence');
		foreach($d->pipe_segments as $pipe) if(!in_array([$pipe->edge_from,$pipe->edge_to],$loop->edges,true)) throw new InvalidArgumentException('Pipe segment is outside the existing loop');
	}

	public function distributionBinding(stdClass $machine, stdClass $loop, stdClass $path, callable $resolve): void
	{
		$this->pathBinding($machine, $loop, $path);
		$d = $path->data;
		if($d->contract_version !== 2) throw new InvalidArgumentException('Versioned distribution path required');
		$components = []; foreach($machine->components as $component) $components[$component->instance_key] = $component;
		if(!in_array($d->parallel_instance, $d->component_instances, true) || !in_array($components[$d->parallel_instance]->role ?? '', ['evaporator', 'water_condenser'], true)) throw new InvalidArgumentException('Parallel sections require an existing liquid exchanger');
		foreach($d->component_instances as $key) {
			$spec = $this->component($components[$key], $resolve);
			$sections = $spec->data->specification->sections ?? ['whole'];
			if($key === $d->parallel_instance) {
				$actual = array_map(fn($section) => $section->section_key, $d->parallel_sections); sort($actual); sort($sections);
				if($actual !== $sections) throw new InvalidArgumentException('Distribution section coverage mismatch');
			} elseif(count($sections) !== 1 || !in_array($spec->data->role, ['tank', 'evaporator', 'water_condenser'], true)) throw new InvalidArgumentException('Additional parallel or valve topology unsupported');
		}
	}

	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, ?string $blocker=null): array
	{
		$s=new Items_Service_TechnicalSchema(); $machine=$s->dataset($machine); if($machine->dataset_type!=='machine_configuration') throw new InvalidArgumentException('Hydraulic machine configuration required');
		$input=$s->fields($request,['loops'=>'list:object']); $result=['calculation_version'=>self::VERSION,'eligible'=>false,'status'=>'review_required','solution'=>null,'diagnostics'=>[],'reasons'=>['machine_physics_not_released']];
		$inputs=[]; foreach($input->loops as $r) { $r=$s->fields($r,['loop_key'=>'key','composition'=>'object','flow'=>'object','component_states'=>'list:object','pipe_states'=>'list:object','external_state'=>'object','pump_condition'=>'?object']); if(isset($inputs[$r->loop_key])) throw new InvalidArgumentException('Duplicate hydraulic loop state'); $inputs[$r->loop_key]=$r; }
		$expected=array_map(fn($l)=>$l->loop_key,$machine->data->hydraulic_loops); $actual=array_keys($inputs);sort($expected);sort($actual);if($expected!==$actual || !$expected) throw new InvalidArgumentException('All hydraulic loops require explicit states');
		if($blocker!==null) { $result['reasons'][]=$blocker;return $result; }
		$solutions=[];
		foreach($machine->data->hydraulic_loops as $loop) {
			try { $solutions[$loop->loop_key]=$this->loop($machine->data,$loop,$inputs[$loop->loop_key],$resolve); }
			catch(DomainException $e) { $result['reasons'][]=$loop->loop_key.':'.$e->getMessage(); }
		}
		$result['diagnostics']=$solutions;
		if(count($solutions)===count($expected)) { $result['solution']=['loops'=>$solutions,'eligible'=>false];$result['mathematical_status']='calculated'; } else $result['mathematical_status']='blocked';
		return $result;
	}

	private function loop(stdClass $machine, stdClass $loop, stdClass $input, callable $resolve): array
	{
		if($loop->fluid_dataset===null || $loop->pressure_loss_dataset===null) throw new DomainException('fluid_or_hydraulic_path_missing');
		$f=new Items_Service_FluidProperties();$s=new Items_Service_TechnicalSchema();$fluid=$this->resolve($resolve,$loop->fluid_dataset,'fluid_properties'); $path=$this->resolve($resolve,$loop->pressure_loss_dataset,'hydraulic_path'); $this->pathBinding($machine,$loop,$path);
		if($path->data->contract_version !== 1) throw new DomainException('distribution_path_requires_operating_point_solver');
		if($loop->tank_kind==='open')throw new DomainException('open_loop_static_head_not_supported');
		$composition=$f->composition($fluid->data,$input->composition); $flow=$f->flow($fluid->data,$composition,$input->flow);$mass=$flow['mass_flow_kg_s'];
		if((isset($loop->flow_min_m3_h) && $flow['volume_flow_m3_h']<(float)$loop->flow_min_m3_h) || (isset($loop->flow_max_m3_h) && $flow['volume_flow_m3_h']>(float)$loop->flow_max_m3_h)) throw new DomainException('hydraulic_loop_flow_limit');
		$components=[]; foreach($machine->components as $c)$components[$c->instance_key]=$c; $states=[];
		foreach($input->component_states as $state) { $state=$s->fields($state,['instance_key'=>'key','sections'=>'list:object']);if(isset($states[$state->instance_key]))throw new InvalidArgumentException('Duplicate component pressure state');$states[$state->instance_key]=$state->sections; }
		$expected=$path->data->component_instances;$actual=array_keys($states);sort($expected);sort($actual);if($expected!==$actual)throw new InvalidArgumentException('Incomplete hydraulic component states');
		$total=0.0;$parts=[];
		foreach($path->data->component_instances as $key) {
			$spec=$this->component($components[$key],$resolve); $ref=$spec->data->specification->pressure_loss_ref??null; if($ref===null) throw new DomainException('component_pressure_loss_missing');
			$map=$this->resolve($resolve,$ref,'pressure_loss_map');$this->mapBinding($spec,$map);$this->sameFluid($map->data->fluid_ref,$fluid);$branches=[];$branchMass=0.0;
			foreach($states[$key] as $state) { $state=$s->fields($state,['section_key'=>'key','temperature_degC'=>'number','volume_flow_m3_h'=>'positive']);if(isset($branches[$state->section_key]))throw new InvalidArgumentException('Duplicate section pressure state');$p=$this->pressure($map,$fluid,$state->section_key,$composition,$state->temperature_degC,$state->volume_flow_m3_h);$branchMass+=(float)$state->volume_flow_m3_h*$p['fluid_state']['density_kg_m3']/3600;$branches[$state->section_key]=$p; }
			$expected=array_map(fn($v)=>$v->section_key,$map->data->sections);$actual=array_keys($branches);sort($expected);sort($actual);if($expected!==$actual)throw new InvalidArgumentException('Incomplete pressure section states');$this->massBalance($branchMass,$mass);
			$drops=array_column($branches,'pressure_loss_Pa');$drop=$this->parallel($drops);$parts[$key]=['pressure_loss_Pa'=>$drop,'sections'=>$branches];$total+=$drop;
		}
		$pipeStates=[];foreach($input->pipe_states as $p){$p=$s->fields($p,['segment_key'=>'key','temperature_degC'=>'number','volume_flow_m3_h'=>'positive']);if(isset($pipeStates[$p->segment_key]))throw new InvalidArgumentException('Duplicate pipe state');$pipeStates[$p->segment_key]=$p;}
		$expected=array_map(fn($p)=>$p->segment_key,$path->data->pipe_segments);$actual=array_keys($pipeStates);sort($expected);sort($actual);if($expected!==$actual)throw new InvalidArgumentException('Incomplete pipe states');$pipes=[];
		foreach($path->data->pipe_segments as $pipe) { $p=$pipeStates[$pipe->segment_key];$state=$f->state($fluid->data,$composition,$p->temperature_degC);$this->massBalance((float)$p->volume_flow_m3_h*$state['density_kg_m3']/3600,$mass);$loss=$this->pipe($pipe,$state,(float)$p->volume_flow_m3_h);$pipes[$pipe->segment_key]=$loss;$total+=$loss['pressure_loss_Pa']; }
		if($path->data->external_loss_ref===null) throw new DomainException('external_pressure_loss_missing');
		$external=$this->resolve($resolve,$path->data->external_loss_ref,'pressure_loss_map');if($external->data->role!=='external' || array_map(fn($v)=>$v->section_key,$external->data->sections)!==['whole'])throw new InvalidArgumentException('External loss boundary mismatch');
		$e=$s->fields($input->external_state,['temperature_degC'=>'number','volume_flow_m3_h'=>'positive']);$loss=$this->pressure($external,$fluid,'whole',$composition,$e->temperature_degC,$e->volume_flow_m3_h);$this->massBalance((float)$e->volume_flow_m3_h*$loss['fluid_state']['density_kg_m3']/3600,$mass);$total+=$loss['pressure_loss_Pa'];
		if(isset($loop->external_requirement_kPa) && abs((float)$loop->external_requirement_kPa*1000-$loss['pressure_loss_Pa'])>max(1e-6,$loss['pressure_loss_Pa']*1e-8))throw new DomainException('external_pressure_requirement_conflict');
		$pump=null;
		if($path->data->pump_instance!==null) {
			$spec=$this->component($components[$path->data->pump_instance],$resolve);$p=$spec->data->specification;
			if($p->curve_ref===null || $p->curve_definition!=='gross' || $p->speed_rpm===null || $p->frequency_Hz===null || $p->fluid_ref===null || $input->pump_condition===null) throw new DomainException('pump_curve_or_boundary_evidence_missing');
			$curve=$this->resolve($resolve,$p->curve_ref,'pump_curve');$this->pumpBinding($spec,$curve);$this->sameFluid($p->fluid_ref,$fluid);$condition=$s->fields($input->pump_condition,['temperature_degC'=>'number','speed_rpm'=>'positive','frequency_Hz'=>'positive','operating_mode'=>'key']);$state=$f->state($fluid->data,$composition,$condition->temperature_degC);$condition->volume_flow_m3_h=$f::decimal(3600*$mass/$state['density_kg_m3']);$pump=$this->pump($curve,$fluid,$composition,$condition,$total);
		} elseif($input->pump_condition!==null) throw new InvalidArgumentException('No assigned pump in this loop');
		$r=['eligible'=>false,'fluid_revision'=>$loop->fluid_dataset,'flow'=>$flow,'component_losses'=>$parts,'pipe_losses'=>$pipes,'external_loss'=>$loss,'required_pressure_Pa'=>$total,'pump'=>$pump,'pump_status'=>$pump===null?'not_assigned':$pump['duty_status']];$f->finite($r);return $r;
	}

	public function parallel(array $drops): float
	{
		if(!$drops) throw new DomainException('parallel_pressure_evidence_missing');
		if(max($drops)-min($drops)>max(1e-6,max($drops)*1e-8)) throw new DomainException('prescribed_parallel_flows_not_in_pressure_balance'); return array_sum($drops)/count($drops);
	}

	private function massBalance(float $actual,float $expected): void { if(abs($actual-$expected)>max(1e-10,$expected*1e-9))throw new DomainException('hydraulic_mass_continuity_violated'); }
	private function pressureFactor(string $unit): float { return ['Pa'=>1.0,'kPa'=>1000.0,'bar'=>100000.0][$unit]; }
}
