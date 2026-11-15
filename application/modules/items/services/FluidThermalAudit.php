<?php

class Items_Service_FluidThermalAudit
{
	public function evaluate(stdClass $machine, array $thermal, stdClass $request, callable $resolve): array
	{
		$h=new Items_Service_FluidHydraulics();$f=new Items_Service_FluidProperties();$s=new Items_Service_TechnicalSchema();
		$result=['eligible'=>false,'status'=>'review_required','scope'=>'fluid_hydraulic_audit_of_fixed_thermal_solution','calculation'=>null,'diagnostics'=>[],'reasons'=>['thermal_solution_remains_synthetic_constant_capacity']];
		if($thermal['solution']===null) { $result['reasons'][]='thermal_solution_required';return $result; }
		$input=$s->fields($request,['loops'=>'list:object']);$loops=[];
		foreach($input->loops as $r) { if(!isset($r->loop_key) || isset($loops[$r->loop_key]))throw new InvalidArgumentException('Invalid thermal audit loop states');$loops[$r->loop_key]=$r; }
		$sourceResolve=$resolve;
		$resolve=function($table,$key,$revision)use($sourceResolve){
			$r=$sourceResolve($table,$key,$revision);
			if($table==='technicaldataset' && in_array($r->dataset_type,['fluid_properties','pressure_loss_map','pump_curve','hydraulic_path'],true) && ($r->data->usage!=='synthetic_test_only' || ($r->import_context->production_import_allowed??null)!==false))throw new DomainException('synthetic_fluid_hydraulic_provenance_required');
			return $r;
		};
		$hydraulics=$h->evaluate($machine,$request,$resolve);$result['diagnostics']['hydraulics']=$hydraulics;
		if($hydraulics['solution']===null){$result['reasons']=array_merge($result['reasons'],$hydraulics['reasons']);return $result;}
		$groups=[];
		try {
			foreach($thermal['solution']['secondary_groups'] as $key=>$group) {
				$contract=$group['contract'];$loopKey=$contract->hydraulic_loop_key;$state=$loops[$loopKey];$loop=array_values(array_filter($machine->data->hydraulic_loops,fn($l)=>$l->loop_key===$loopKey))[0];$fluid=$h->resolve($resolve,$loop->fluid_dataset,'fluid_properties');
				$c=$f->composition($fluid->data,$state->composition);$tin=$group['boundary']->inlet_degC;$mass=0.0;$heat=0.0;$branches=[];$details=[];
				$pressureStates=array_values(array_filter($state->component_states,fn($p)=>$p->instance_key===$contract->instance_key));
				if(count($pressureStates)!==1)throw new InvalidArgumentException('Thermal audit exchanger states missing');$pressureSections=[];foreach($pressureStates[0]->sections as $p)$pressureSections[$p->section_key]=$p;
				foreach($contract->branches as $branch) {
					$p=$pressureSections[$branch->section_key];
					if(Items_Service_TechnicalJson::compareDecimal($p->temperature_degC,$tin)!==0 || Items_Service_TechnicalJson::compareDecimal($p->volume_flow_m3_h,$branch->volume_flow_m3_h)!==0)throw new DomainException('thermal_hydraulic_branch_state_mismatch');
					$out=$thermal['solution']['circuits'][$branch->circuit_key][$contract->side]['secondary_outlet_degC'];
					$in=$f->state($fluid->data,$c,$tin);$end=$f->state($fluid->data,$c,$f::decimal($out));$m=(float)$branch->volume_flow_m3_h*$in['density_kg_m3']/3600;$q=$m*$f->enthalpyDifference($fluid->data,$c,$tin,$f::decimal($out));$mass+=$m;$heat+=$q;
					$branches[]=(object)['branch_key'=>$branch->section_key,'composition'=>$c,'temperature_degC'=>$f::decimal($out),'flow'=>(object)['unit'=>'kg/s','value'=>$f::decimal($m),'at_degC'=>$f::decimal($out)]];
					$details[$branch->section_key]=['mass_flow_kg_s'=>$m,'inlet_volume_flow_m3_h'=>(float)$branch->volume_flow_m3_h,'heat_to_fluid_W'=>$q,'inlet'=>$in,'outlet'=>$end];
				}
				$inFlow=$f->flow($fluid->data,$c,$state->flow);
				if(abs($mass-$inFlow['mass_flow_kg_s'])>max(1e-10,$mass*1e-9))throw new DomainException('thermal_hydraulic_total_flow_mismatch');
				$mix=count($branches)>1?$f->mix($fluid->data,$branches):['mixed'=>$end,'mass_flow_kg_s'=>$mass,'mixed_volume_flow_m3_h'=>3600*$mass/$end['density_kg_m3'],'energy_residual_W'=>0.0,'mass_residual_kg_s'=>0.0];
				$expected=$group['balance']['heat_flow_sum_kW']*1000*($contract->side==='evaporator'?-1:1);$residual=$heat-$expected;
				$this->temperatures($machine->data,$loop,$state,$h->resolve($resolve,$loop->pressure_loss_dataset,'hydraulic_path')->data,$contract->instance_key,(float)$tin,$mix['mixed']['temperature_degC']);
				$groups[$key]=['branches'=>$details,'common_inlet_volume_flow_m3_h'=>(float)$contract->total_volume_flow_m3_h,'mass_flow_kg_s'=>$mass,'heat_to_fluid_W'=>$heat,'thermal_reference_residual_W'=>$residual,'mixture'=>$mix];
				// This audit does not replace the constant-capacity thermal root with a variable-property root.
				if(abs($residual)>1e-3 || abs($mix['mixed']['temperature_degC']-$group['balance']['mixed_outlet_degC'])>1e-7)throw new DomainException('fluid_evidence_inconsistent_with_fixed_thermal_solution');
			}
			$result['calculation']=['secondary_groups'=>$groups,'hydraulics'=>$hydraulics['solution'],'eligible'=>false];$result['mathematical_status']='calculated';
		} catch(DomainException $e) { $result['reasons'][]=$e->getMessage();$result['mathematical_status']='blocked'; }
		$result['diagnostics']['secondary_groups']=$groups;return $result;
	}

	private function temperatures(stdClass $machine, stdClass $loop, stdClass $state, stdClass $path, string $exchanger, float $inlet, float $outlet): void
	{
		$components=[];foreach($machine->components as $component)$components[$component->instance_key]=$component;
		$states=[];foreach($state->component_states as $component)$states[$component->instance_key]=$component->sections;
		$edges=[];foreach($loop->edges as $edge)$edges[$edge[0]]=$edge[1];$port=$loop->boundary_in;$temperature=$inlet;
		for($i=0;$i<=count($loop->edges);$i++) {
			$next=$edges[$port];
			foreach($path->pipe_segments as $pipe) if($pipe->edge_from===$port && $pipe->edge_to===$next) foreach($state->pipe_states as $given) if($given->segment_key===$pipe->segment_key && abs((float)$given->temperature_degC-$temperature)>1e-7)throw new DomainException('thermal_hydraulic_pipe_temperature_mismatch');
			if($next===$loop->boundary_out)break;
			$instance=substr($next,0,strrpos($next,'.'));$component=$components[$instance];
			if($component->role==='pump') { if($state->pump_condition===null || abs((float)$state->pump_condition->temperature_degC-$temperature)>1e-7)throw new DomainException('thermal_hydraulic_pump_temperature_mismatch'); }
			else foreach($states[$instance] as $section) if(abs((float)$section->temperature_degC-$temperature)>1e-7)throw new DomainException('thermal_hydraulic_component_temperature_mismatch');
			if($instance===$exchanger)$temperature=$outlet;
			foreach($component->ports as $name=>$kind)if($kind==='liquid_out')$port=$instance.'.'.$name;
		}
		if(abs((float)$state->external_state->temperature_degC-$outlet)>1e-7)throw new DomainException('thermal_hydraulic_external_inlet_mismatch');
	}
}
