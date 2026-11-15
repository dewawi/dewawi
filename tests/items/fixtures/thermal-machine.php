<?php

require_once __DIR__.'/thermal-circuit.php';

function thermalMachineBundle(string $kind = 'shared_evaporator', string $prefix = 'fixture:parallel'): stdClass
{
	$sharedEvap = in_array($kind, ['shared_evaporator', 'both_shared'], true); $sharedCond = in_array($kind, ['shared_condenser', 'both_shared'], true);
	$b = machineStageBundle(2, 2, $prefix); $single = thermalBundle('water', $prefix, 2);
	foreach($b->datasets as $r) {
		if($r->dataset_type === 'machine_configuration') $machine = $r;
		if($r->dataset_type === 'component_spec' && $r->data->role === 'evaporator') $evap = $r;
		if($r->dataset_type === 'component_spec' && $r->data->role === 'air_condenser') $cond = $r;
	}
	foreach($b->profiles as $p) { if($p->role === 'evaporator')$evapProfile=$p;if($p->role==='air_condenser')$condProfile=$p; }
	foreach($single->datasets as $r) { if($r->dataset_type==='heat_exchanger_map' && $r->data->role==='evaporator')$evapMap=$r;if($r->dataset_type==='heat_exchanger_map' && $r->data->role==='water_condenser')$condMap=$r;if($r->dataset_type==='thermal_balance_model')$model=$r; }
	$copy = fn($v) => unserialize(serialize($v));
	$b->datasets=array_values(array_filter($b->datasets,fn($r)=>!in_array($r->dataset_type,['reference_points'],true) && !in_array($r->data->role??null,['evaporator','air_condenser','fan'],true)));
	$b->profiles=array_values(array_filter($b->profiles,fn($p)=>!in_array($p->role,['evaporator','air_condenser','fan'],true)));
	$data=$machine->data;$data->cooling_kind='water';$data->air_paths=[];
	$data->components=array_values(array_filter($data->components,fn($c)=>!in_array($c->role,['evaporator','air_condenser','fan'],true)));
	$data->shared_components=array_values(array_filter($data->shared_components,fn($c)=>!in_array($c->instance_key,['evap','fan'],true)));
	$data->hydraulic_loops=[];$groups=[];$assignments=[];
	foreach(['evaporator','condenser'] as $side) {
		$shared=$side==='evaporator'?$sharedEvap:$sharedCond;$base=$side==='evaporator'?'evap':'cond';$loopBase=$side==='evaporator'?'load':'source';
		$template=$side==='evaporator'?$evap:$cond;$templateProfile=$side==='evaporator'?$evapProfile:$condProfile;$templateMap=$side==='evaporator'?$evapMap:$condMap;
		$sets=$shared?[['r1','r2']]:[['r1'],['r2']];
		foreach($sets as $members) {
			$instance=$shared?$base:$base.'.'.$members[0];$loop=$shared?$loopBase:$loopBase.'.'.$members[0];$keySuffix=$shared?'':':'.$members[0];
			$spec=$copy($template);$profile=$copy($templateProfile);$map=$copy($templateMap);
			$spec->dataset_key=$prefix.':'.$side.$keySuffix;$profile->profile_key=$prefix.':profile:'.$side.$keySuffix;$profile->dataset_ref=(object)['dataset_key'=>$spec->dataset_key,'revision'=>1];
			$profile->role=$side==='evaporator'?'evaporator':'water_condenser';$spec->data->role=$profile->role;$spec->data->specification->exchanger_type='plate';
			$ports=(object)['water_in'=>'liquid_in','water_out'=>'liquid_out'];$sectionKeys=[];$map->data->sections=[];$branches=[];
			foreach($members as $r) {
				$section=$side==='evaporator'||$shared?$r:'main';$sectionKeys[]=$section;
				$in=$side==='condenser'&&!$shared?'in':$section.'_in';$out=$side==='condenser'&&!$shared?'out':$section.'_out';$ports->$in='refrigerant_in';$ports->$out='refrigerant_out';
				$s=$copy($templateMap->data->sections[0]);$s->section_key=$section;
				$s->effectiveness=$side==='evaporator'?($r==='r1'?'0.5':'0.4'):($r==='r1'?'0.25':'0.5');
				$s->limits->saturation_temperature=$side==='evaporator'?thermalRangeFixture('0','9'):thermalRangeFixture('31','60');$map->data->sections[]=$s;
				$flow=$side==='evaporator'?($r==='r1'?'10.8':'8.82'):($r==='r1'?'6.687':'4.377');
				$branches[]=(object)['circuit_key'=>$r,'section_key'=>$section,'volume_flow_m3_h'=>$flow];
				foreach($data->circuits as $circuit) if($circuit->circuit_key===$r) {
					if($side==='evaporator')$circuit->evaporator_section=(object)['instance_key'=>$instance,'section_key'=>$section];
					else { $circuit->condenser_instance=$instance;if($shared)$circuit->condenser_section_key=$section; }
					foreach($circuit->refrigerant_groups as $group) foreach($group->members as &$port) {
						if($side==='evaporator')$port=str_replace('evap.',$instance.'.',$port);
						else $port=str_replace(['cond.'.$r.'.in','cond.'.$r.'.out'],[$instance.'.'.$in,$instance.'.'.$out],$port);
					}
					unset($port);
				}
			}
			$spec->data->ports=$ports;$spec->data->specification->sections=$sectionKeys;
			$map->dataset_key=$prefix.':'.$side.'-map'.$keySuffix;$map->data->exchanger_type='plate';$spec->data->specification->thermal_map_ref=(object)['dataset_key'=>$map->dataset_key,'revision'=>1];
			$data->components[]=(object)['instance_key'=>$instance,'role'=>$profile->role,'component_key'=>$spec->data->component_key,'profile_key'=>$profile->profile_key,'profile_revision'=>1,'ports'=>$ports];
			$b->datasets[]=$spec;$b->datasets[]=$map;$b->profiles[]=$profile;
			$boundaryIn=$loop.'_return';$boundaryOut=$loop.'_supply';$edges=[[$boundaryIn,$instance.'.water_in'],[$instance.'.water_out',$boundaryOut]];
			$hasPump=$side==='evaporator'&&($shared||$members[0]==='r1');
			if($hasPump) { $edges=[[$boundaryIn,'tank.in'],['tank.out','pump.in'],['pump.out',$instance.'.water_in'],[$instance.'.water_out',$boundaryOut]];foreach($data->shared_components as $component)$component->hydraulic_loop_key=$loop; }
			$data->hydraulic_loops[]=(object)['loop_key'=>$loop,'fluid_dataset'=>null,'boundary_in'=>$boundaryIn,'boundary_out'=>$boundaryOut,'edges'=>$edges,'tank_kind'=>$hasPump?'pressurized':null,'external_pressure_definition'=>'external_to_machine','pressure_loss_dataset'=>null];
			if($shared)$data->shared_components[]=(object)['instance_key'=>$instance,'shared_by_circuits'=>['r1','r2'],'refrigerant_sections_are_isolated'=>true,'hydraulic_loop_key'=>$loop];
			$total='0';foreach($branches as $branch)$total=Items_Service_TechnicalJson::addNonnegativeDecimals($total,$branch->volume_flow_m3_h);
			$groups[]=(object)['group_key'=>$loop,'topology_kind'=>$shared?'parallel_sections_common_inlet_v1':'single_section_boundary_v1','mixing_model_id'=>'synthetic_constant_properties_parallel_v1','side'=>$side,'instance_key'=>$instance,'hydraulic_loop_key'=>$loop,'inlet_port'=>$instance.'.water_in','outlet_port'=>$instance.'.water_out','medium_key'=>'fixture:liquid','total_volume_flow_m3_h'=>$total,'branches'=>$branches,'limits'=>(object)['secondary_inlet'=>thermalRangeFixture('0','45'),'mixed_outlet'=>thermalRangeFixture('0','80'),'total_volume_flow'=>thermalRangeFixture('0.1','100','m3/h')]];
		}
	}
	foreach($data->mode_topologies as $mode) if($mode->mode==='cooling')$mode->load_loop_key=$sharedEvap?'load':'load.r1';
	foreach(['r1','r2'] as $r) { $m=$copy($model);$m->dataset_key=$prefix.':circuit-model:'.$r;$m->data->circuit_key=$r;$m->data->search_bounds->Tc=thermalRangeFixture('31','60');$b->datasets[]=$m;$assignments[]=(object)['circuit_key'=>$r,'circuit_model_ref'=>(object)['dataset_key'=>$m->dataset_key,'revision'=>1]]; }
	$parallel=technicalDraft($prefix.':parallel-model','thermal_balance_model',(object)['contract_version'=>2,'usage'=>'synthetic_test_only','model_id'=>'synthetic_fixed_inlet_parallel_sections_v1','circuits'=>$assignments,'secondary_groups'=>$groups,'inactive_section_model_id'=>'synthetic_zero_heat_pass_through_v1','variables'=>(object)['temperature'=>'degC','volume_flow'=>'m3/h','heat_flow'=>'kW','capacity_rate'=>'kW/K'],'numerics'=>(object)['heat_tolerance_kW'=>'0.0000001','temperature_tolerance_K'=>'0.00000001'],'missing_evidence'=>['oem_shared_component_release_missing']]);
	$b->datasets[]=$parallel;$data->thermal_model_ref=(object)['dataset_key'=>$parallel->dataset_key,'revision'=>1];
	$b->template_kind=$single->template_kind;$b->production_import_allowed=false;$b->provenance_note=$single->provenance_note;
	foreach($b->datasets as $record)$record->import_context=(object)['template_kind'=>$b->template_kind,'production_import_allowed'=>false,'provenance_note'=>$b->provenance_note];
	return $b;
}

function thermalMachineRequest(stdClass $bundle, string $stage='full'): stdClass
{
	foreach($bundle->datasets as $record)if($record->dataset_type==='thermal_balance_model'&&$record->data->contract_version===2)$model=$record->data;
	$boundaries=[];
	foreach($model->secondary_groups as $group)$boundaries[]=(object)['group_key'=>$group->group_key,'boundary'=>(object)['medium_key'=>'fixture:liquid','inlet_degC'=>$group->side==='evaporator'?'10':'30','volume_flow_m3_h'=>$group->total_volume_flow_m3_h,'fluid_state'=>'single_phase_liquid']];
	return (object)['stage_key'=>$stage,'operating_mode'=>'cooling','secondary_boundaries'=>$boundaries];
}
