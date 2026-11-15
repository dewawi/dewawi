<?php

require_once __DIR__.'/fluid-hydraulics.php';

function operatingModeBundle(string $kind='heat_pump', string $prefix='fixture:d6'): stdClass
{
	$b=thermalBundle('water',$prefix,2);$num=fn($v)=>json_encode((float)$v,JSON_THROW_ON_ERROR);
	foreach($b->datasets as $record){if($record->dataset_type==='machine_configuration')$machine=$record;if($record->dataset_type==='component_spec'&&$record->data->role==='evaporator')$evap=$record;if($record->dataset_type==='component_spec'&&$record->data->role==='water_condenser')$cond=$record;if($record->dataset_type==='compressor_polynomial')unset($record->data->quantities->discharge_temperature);}
	foreach($b->profiles as $profile)if($profile->role==='water_condenser')$condProfile=$profile;
	$d=$machine->data;$d->configuration_version=2;$d->refrigerant_reversible=$kind==='reversible';$d->mode_topologies=[];
	$c=$d->circuits[0];$c->expansion_device->kind='fixed';$c->topology_kind='mode_specific_fixed_connections_v1';unset($c->evaporator_section,$c->condenser_instance,$c->refrigerant_groups);
	$fluid=fluidFixture('water','mass_fraction',true,$prefix.':fluid');$b->datasets[]=$fluid;$fluidRef=(object)['dataset_key'=>$fluid->dataset_key,'revision'=>1];foreach($d->hydraulic_loops as $loop)$loop->fluid_dataset=clone $fluidRef;
	$rec=null;
	if($kind==='full_heat_recovery'){
		$cond->data->role='recovery_exchanger';$cond->data->component_key='fixture:recovery';$cond->data->model='SYNTHETIC-RECOVERY';$condProfile->role='recovery_exchanger';$condProfile->item_ref='fixture:recovery';$condProfile->binding->model=$cond->data->model;$condProfile->binding->manufacturersku='SYNTHETIC-recovery';
		foreach($d->components as $component)if($component->instance_key==='cond.r1'){$component->role='recovery_exchanger';$component->component_key='fixture:recovery';}
	}
	if($kind==='partial_heat_recovery'){
		$rec=unserialize(serialize($cond));$rec->dataset_key=$prefix.':recovery';$rec->data->component_key='fixture:recovery';$rec->data->model='SYNTHETIC-RECOVERY';$rec->data->role='recovery_exchanger';$rec->data->specification->thermal_map_ref=(object)['dataset_key'=>$prefix.':recovery-map','revision'=>1];
		$profile=unserialize(serialize($condProfile));$profile->profile_key=$prefix.':profile:recovery';$profile->dataset_ref=(object)['dataset_key'=>$rec->dataset_key,'revision'=>1];$profile->item_ref='fixture:recovery';$profile->role='recovery_exchanger';$profile->binding->model=$rec->data->model;$profile->binding->manufacturersku='SYNTHETIC-recovery';$b->profiles[]=$profile;$b->datasets[]=$rec;
		$d->components[]=(object)['instance_key'=>'recovery','role'=>'recovery_exchanger','component_key'=>'fixture:recovery','profile_key'=>$profile->profile_key,'profile_revision'=>1,'ports'=>clone $rec->data->ports];
		$d->hydraulic_loops[]=(object)['loop_key'=>'recovery','fluid_dataset'=>clone $fluidRef,'boundary_in'=>'recovery_return','boundary_out'=>'recovery_supply','edges'=>[['recovery_return','recovery.water_in'],['recovery.water_out','recovery_supply']],'tank_kind'=>null,'external_pressure_definition'=>'external_to_machine','pressure_loss_dataset'=>null];
	}
	if($kind==='reversible'){
		foreach([$evap,$cond] as $spec)foreach($spec->data->ports as &$type)if(str_starts_with($type,'refrigerant_'))$type='refrigerant_bidirectional';unset($type);
		foreach($d->components as $component)if($component->role==='evaporator')$component->ports=clone $evap->data->ports;elseif($component->role==='water_condenser')$component->ports=clone $cond->data->ports;
		$c->expansion_device->ports=(object)['in'=>'refrigerant_bidirectional','out'=>'refrigerant_bidirectional'];
		$valve=technicalDraft($prefix.':switch','component_spec',(object)['component_key'=>'fixture:switch','role'=>'technical_option','model'=>'SYNTHETIC-SWITCH','refrigerant'=>$d->refrigerant,'electrical'=>null,'ports'=>(object)['D'=>'refrigerant_bidirectional','S'=>'refrigerant_bidirectional','A'=>'refrigerant_bidirectional','B'=>'refrigerant_bidirectional'],'specification'=>(object)['effect'=>'neutral_verified','effect_dataset_ref'=>null,'source_locator'=>'synthetic switch routes','refrigerant_valve'=>(object)['model_id'=>'synthetic_refrigerant_port_switch_v1','states'=>[(object)['state_key'=>'cool','connections'=>[['D','B'],['A','S']],'closed_ports'=>[]],(object)['state_key'=>'heat','connections'=>[['D','A'],['B','S']],'closed_ports'=>[]]]]]]);
		$profile=technicalProfile('switch','technical_option',$valve);$profile->profile_key=$prefix.':profile:switch';$b->datasets[]=$valve;$b->profiles[]=$profile;$d->components[]=(object)['instance_key'=>'switch','role'=>'technical_option','component_key'=>'fixture:switch','profile_key'=>$profile->profile_key,'profile_revision'=>1,'ports'=>clone $valve->data->ports];
	}
	$x=$c->expansion_device->device_key;$connections=[];
	foreach($c->compressor_instances as $key){$connections[]=[$key.'.discharge',$kind==='reversible'?'switch.D':($rec!==null?'recovery.in':'cond.r1.in')];$connections[]=[$key.'.suction',$kind==='reversible'?'switch.S':'evap.r1_out'];}
	if($kind==='reversible')$connections=array_merge($connections,[['switch.A','evap.r1_in'],['evap.r1_out',$x.'.in'],[$x.'.out','cond.r1.out'],['cond.r1.in','switch.B']]);
	else{$connections=array_merge($connections,[['cond.r1.out',$x.'.in'],[$x.'.out','evap.r1_in']]);if($rec!==null)$connections[]=['recovery.out','cond.r1.in'];}
	$c->refrigerant_connections=$connections;
	$modes=$kind==='reversible'?['cooling','heat_pump']:[$kind];$maps=[];
	$assignment=fn($instance,$section,$in,$out,$loop)=>(object)['instance_key'=>$instance,'section_key'=>$section,'inlet_port'=>$instance.'.'.$in,'outlet_port'=>$instance.'.'.$out,'loop_key'=>$loop];
	foreach($modes as $mode){
		$e=$assignment('evap','r1','r1_in','r1_out','load');$hot=$assignment('cond.r1','main','in','out','source');$recovery=$rec===null?null:$assignment('recovery','main','in','out','recovery');$expIn=$x.'.in';$expOut=$x.'.out';$valves=[];
		if($kind==='reversible'){
			$valves=[(object)['instance_key'=>'switch','state_key'=>$mode==='cooling'?'cool':'heat']];
			if($mode==='cooling'){$e=$assignment('evap','r1','r1_out','r1_in','load');$expIn=$x.'.out';$expOut=$x.'.in';}
			else{$e=$assignment('cond.r1','main','out','in','source');$hot=$assignment('evap','r1','r1_in','r1_out','load');}
		}
		$points=[];
		foreach($d->stages as $stage){$full=count($stage->active_compressors)===2;$q=$full?30:10;$p=$full?7.15:2.05;$m=$full?300/3600:100/3600;$qr=$rec===null?0:($full?4:1);$hEv=200+$q/$m;$hDis=$hEv+$p/$m;$hRec=$hDis-$qr/$m;$tDis=60+($hDis-597.8)/2.4;
			$power=[];foreach(array_merge(array_map(fn($c)=>$c->instance_key,$d->components),[$x]) as $key)if(!in_array($key,$stage->active_compressors,true))$power[]=(object)['instance_key'=>$key,'electrical_power_kW'=>'0','heat_destination'=>'outside_refrigerant_and_useful_boundary','source_locator'=>'explicit synthetic ideal auxiliary and standby input'];
			$pointKey=$mode.':'.$stage->stage_key;$points[]=(object)['point_key'=>$pointKey,'stage_key'=>$stage->stage_key,'Te_degC'=>'5','Tc_degC'=>'50','power_boundary'=>(object)['model_id'=>'synthetic_all_electrical_inputs_hx_flange_useful_heat_v1','external_auxiliary_kW'=>'0.1','source_locator'=>'synthetic complete boundary','inputs'=>$power]];
			foreach(['evaporator'=>$e,'condenser'=>$hot,'recovery'=>$recovery] as $side=>$a)if($a!==null){
				$component=array_values(array_filter($d->components,fn($c)=>$c->instance_key===$a->instance_key))[0];$spec=$a->instance_key==='evap'?$evap:($a->instance_key==='recovery'?$rec:$cond);
				if(!isset($maps[$a->instance_key])){$mapKey=$prefix.':'.($a->instance_key==='evap'?'evaporator':($a->instance_key==='recovery'?'recovery':'condenser')).'-map';$spec->data->specification->thermal_map_ref=(object)['dataset_key'=>$mapKey,'revision'=>1];$maps[$a->instance_key]=technicalDraft($mapKey,'heat_exchanger_map',(object)['contract_version'=>2,'usage'=>'synthetic_test_only','component_key'=>$spec->data->component_key,'model'=>$spec->data->model,'role'=>$spec->data->role,'exchanger_type'=>'plate','refrigerant'=>$d->refrigerant,'temperature_basis'=>'pure_saturation','model_id'=>'synthetic_refrigerant_enthalpy_points_v1','state_basis'=>'explicit_synthetic_states_no_eos_v1','enthalpy_reference'=>'synthetic_common_datum_v1','refrigerant_sections_are_isolated'=>true,'fluid_ref'=>clone $fluidRef,'fluid_composition'=>fluidComposition(),'units'=>(object)['temperature'=>'degC','pressure'=>'kPa','specific_enthalpy'=>'kJ/kg','mass_flow'=>'kg/s','heat_flow'=>'kW','volume_flow'=>'m3/h'],'sections'=>[(object)['section_key'=>$a->section_key,'limits'=>(object)['Te'=>thermalRangeFixture('0','9'),'Tc'=>thermalRangeFixture('40','60'),'secondary_inlet'=>thermalRangeFixture('0','80'),'secondary_outlet'=>thermalRangeFixture('0','80'),'volume_flow'=>thermalRangeFixture('0.1','100','m3/h'),'refrigerant_temperature'=>thermalRangeFixture('0','100'),'refrigerant_pressure'=>thermalRangeFixture('100','2000','kPa')],'points'=>[]]],'missing_evidence'=>['real_oem_mode_map_missing']]);}
				$state=fn($t,$pressure,$enthalpy,$phase)=>(object)['temperature_degC'=>$num($t),'pressure_kPa'=>$num($pressure),'enthalpy_kJ_kg'=>$num($enthalpy),'phase'=>$phase];
				if($side==='evaporator'){$in=$state(5,300,200,'two_phase');$out=$state(15,300,$hEv,'superheated_vapor');$heat=$q;$flow=10.8;$tin=20;$tout=$tin-$heat/12;}
				elseif($side==='recovery'){$in=$state($tDis,1200,$hDis,'superheated_vapor');$out=$state(60,1200,$hRec,'superheated_vapor');$heat=$qr;$flow=3.6;$tin=55;$tout=$tin+$heat/4;}
				else{$in=$state($rec!==null?60:$tDis,1200,$rec!==null?$hRec:$hDis,'superheated_vapor');$out=$state(50,1200,200,'liquid');$heat=$q+$p-$qr;$flow=3.6;$tin=30;$tout=$tin+$heat/4;}
				$maps[$a->instance_key]->data->sections[0]->points[]=(object)['point_key'=>$pointKey,'mode'=>$mode,'stage_key'=>$stage->stage_key,'operating_role'=>$side==='recovery'?'desuperheater':$side,'Te_degC'=>'5','Tc_degC'=>'50','mass_flow_kg_s'=>$num($m),'inlet_state'=>$in,'outlet_state'=>$out,'secondary'=>(object)['inlet_degC'=>$num($tin),'outlet_degC'=>$num($tout),'volume_flow_m3_h'=>$num($flow)],'heat_flow_kW'=>$num($heat)];
			}
		}
		$model=technicalDraft($prefix.':mode:'.$mode,'operating_mode_model',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','mode'=>$mode,'circuit_key'=>'r1','topology_kind'=>$rec===null?'evaporator_condenser_v1':'desuperheater_before_condenser_v1','enabled_components'=>array_map(fn($c)=>$c->instance_key,$d->components),'isolated_components'=>[],'stage_keys'=>array_map(fn($s)=>$s->stage_key,$d->stages),'evaporator'=>$e,'condenser'=>$hot,'recovery'=>$recovery,'expansion'=>(object)['model_id'=>'synthetic_isenthalpic_no_external_heat_v1','inlet_port'=>$expIn,'outlet_port'=>$expOut],'valve_states'=>$valves,'energy_boundary_ref'=>clone $d->thermal_model_ref,'pipe_model'=>'synthetic_zero_refrigerant_pipe_pressure_heat_loss_v1','inactive_compressor_model'=>'synthetic_no_refrigerant_flow_v1','points'=>$points,'numerics'=>(object)['heat_tolerance_kW'=>'0.0000001','mass_tolerance_kg_s'=>'0.000000001'],'missing_evidence'=>['real_oem_operating_release_missing']]);
		$b->datasets[]=$model;$d->mode_topologies[]=(object)['mode'=>$mode,'release_status'=>'draft','topology_ref'=>'self:'.$mode,'circuit_keys'=>['r1'],'load_loop_key'=>in_array($mode,['full_heat_recovery','heat_pump'],true)?$hot->loop_key:$e->loop_key,'operating_model_ref'=>(object)['dataset_key'=>$model->dataset_key,'revision'=>1]];
	}
	$b->datasets=array_values(array_filter($b->datasets,fn($r)=>!in_array($r->dataset_type,['heat_exchanger_map','reference_points'],true)));foreach($maps as $map)$b->datasets[]=$map;
	foreach($b->datasets as $record){if($record->dataset_type==='component_spec'&&in_array($record->data->role,['evaporator','water_condenser','recovery_exchanger'],true))$record->data->specification->exchanger_type='plate';$record->import_context=(object)['template_kind'=>$b->template_kind,'production_import_allowed'=>false,'provenance_note'=>$b->provenance_note];}
	return $b;
}

function operatingModeRequest(stdClass $bundle, ?string $mode=null, string $stage='full'): stdClass
{
	foreach($bundle->datasets as $record)if($record->dataset_type==='operating_mode_model'&&($mode===null||$record->data->mode===$mode)){$model=$record->data;break;}
	return (object)['operating_mode'=>$model->mode,'stage_key'=>$stage,'point'=>(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'degC'],'valve_states'=>unserialize(serialize($model->valve_states))];
}

function operatingModeHydraulics(stdClass $bundle, string $mode='heat_pump', string $stage='full'): stdClass
{
	foreach($bundle->datasets as $r){if($r->dataset_type==='machine_configuration')$machine=$r->data;if($r->dataset_type==='operating_mode_model'&&$r->data->mode===$mode)$model=$r->data;if($r->dataset_type==='fluid_properties')$fluid=$r;}
	$prefix=$fluid->dataset_key;$assignments=[];foreach(['evaporator','condenser','recovery'] as $side)if($model->$side!==null)$assignments[$model->$side->loop_key]=$model->$side;
	$specs=[];foreach($bundle->datasets as $r)if($r->dataset_type==='component_spec')$specs[$r->data->component_key]=$r;$requests=[];
	foreach($machine->hydraulic_loops as $loop){$assignment=$assignments[$loop->loop_key];$component=array_values(array_filter($machine->components,fn($c)=>$c->instance_key===$assignment->instance_key))[0];$ref=$specs[$component->component_key]->data->specification->thermal_map_ref;$map=array_values(array_filter($bundle->datasets,fn($r)=>$r->dataset_key===$ref->dataset_key))[0];$point=array_values(array_filter($map->data->sections[0]->points,fn($p)=>$p->mode===$mode&&$p->stage_key===$stage))[0];$tin=$point->secondary->inlet_degC;$tout=$point->secondary->outlet_degC;$flow=$point->secondary->volume_flow_m3_h;$members=[];$states=[];$pump=null;
		foreach($machine->components as $c)foreach($c->ports as $port=>$kind)if($kind==='liquid_in')foreach($loop->edges as $edge)if($edge[1]===$c->instance_key.'.'.$port){$spec=$specs[$c->component_key];if($c->role==='pump'){$pump=$c->instance_key;$curve=pumpFixture($fluid,$prefix.':pump-curve',$spec->data->component_key,$spec->data->model);foreach($curve->data->points as $p)$p->ordinate='0';$spec->data->specification->curve_ref=(object)['dataset_key'=>$curve->dataset_key,'revision'=>1];$spec->data->specification->fluid_ref=(object)['dataset_key'=>$fluid->dataset_key,'revision'=>1];$spec->data->specification->speed_rpm='2900';$spec->data->specification->frequency_Hz='50';$bundle->datasets[]=$curve;continue;}
			$members[]=$c->instance_key;$section=$c->instance_key===$assignment->instance_key?$assignment->section_key:'whole';$loss=pressureFixture($fluid,$prefix.':loss:'.$c->instance_key,$spec->data->component_key,$spec->data->model,$spec->data->role,[$section=>(float)$flow],0);$spec->data->specification->pressure_loss_ref=(object)['dataset_key'=>$loss->dataset_key,'revision'=>1];$bundle->datasets[]=$loss;$states[]=(object)['instance_key'=>$c->instance_key,'sections'=>[(object)['section_key'=>$section,'temperature_degC'=>$tin,'volume_flow_m3_h'=>$flow]]];
		}
		$external=pressureFixture($fluid,$prefix.':external:'.$loop->loop_key,'fixture:external','SYNTHETIC-EXTERNAL','external',['whole'=>(float)$flow],0);$bundle->datasets[]=$external;$path=technicalDraft($prefix.':path:'.$loop->loop_key,'hydraulic_path',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','loop_key'=>$loop->loop_key,'topology_kind'=>'serial_components_parallel_sections_v1','component_instances'=>$members,'pump_instance'=>$pump,'external_loss_ref'=>(object)['dataset_key'=>$external->dataset_key,'revision'=>1],'pipe_segments'=>[],'pressure_boundary'=>'closed_loop_dissipative_only_v1','connections'=>array_map(fn($edge)=>(object)['edge_from'=>$edge[0],'edge_to'=>$edge[1],'loss_basis'=>'documented_zero','source_locator'=>'Explicit ideal synthetic connections'],$loop->edges),'missing_evidence'=>[]]);$bundle->datasets[]=$path;$loop->pressure_loss_dataset=(object)['dataset_key'=>$path->dataset_key,'revision'=>1];
		$requests[]=(object)['loop_key'=>$loop->loop_key,'composition'=>fluidComposition(),'flow'=>(object)['value'=>$flow,'unit'=>'m3/h','at_degC'=>$tin],'component_states'=>$states,'pipe_states'=>[],'external_state'=>(object)['temperature_degC'=>$tout,'volume_flow_m3_h'=>$flow],'pump_condition'=>$pump===null?null:(object)['temperature_degC'=>$tin,'speed_rpm'=>'2900','frequency_Hz'=>'50','operating_mode'=>'fixed']];
	}
	foreach($bundle->datasets as $record)$record->import_context=(object)['template_kind'=>$bundle->template_kind,'production_import_allowed'=>false,'provenance_note'=>$bundle->provenance_note];
	return (object)['loops'=>$requests];
}
