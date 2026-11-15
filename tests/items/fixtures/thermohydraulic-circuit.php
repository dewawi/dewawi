<?php

require_once __DIR__.'/operating-mode.php';

function thermohydraulicBundle(string $mode='cooling', string $prefix='fixture:d6b', bool $variable=false): stdClass
{
	$b=operatingModeBundle($mode,$prefix);operatingModeHydraulics($b,$mode==='reversible'?'heat_pump':$mode);
	foreach($b->datasets as $r){if($r->dataset_type==='machine_configuration')$machine=$r;if($r->dataset_type==='fluid_properties')$fluid=$r;}
	$d=$machine->data;$clone=fn($v)=>unserialize(serialize($v));
	$pumpComponent=array_values(array_filter($d->components,fn($v)=>$v->role==='pump'))[0];$second=$clone($pumpComponent);$second->instance_key='pump.secondary';$second->component_key='fixture:pump-secondary';$second->profile_key=$prefix.':profile:pump-secondary';$d->components[]=$second;
	$profile=array_values(array_filter($b->profiles,fn($v)=>$v->profile_key===$pumpComponent->profile_key))[0];$profile=$clone($profile);$profile->profile_key=$second->profile_key;$profile->item_ref=$second->component_key;$profile->binding->manufacturersku='SYNTHETIC-pump-secondary';$profile->dataset_ref=(object)['dataset_key'=>$prefix.':pump-secondary','revision'=>1];$b->profiles[]=$profile;
	$spec=array_values(array_filter($b->datasets,fn($v)=>$v->dataset_type==='component_spec'&&$v->data->role==='pump'))[0];$spec=$clone($spec);$spec->dataset_key=$profile->dataset_ref->dataset_key;$spec->data->component_key=$second->component_key;$spec->data->specification->curve_ref=(object)['dataset_key'=>$prefix.':pump-secondary-curve','revision'=>1];$b->datasets[]=$spec;
	$firstCurve=array_values(array_filter($b->datasets,fn($v)=>$v->dataset_type==='pump_curve'))[0];$secondCurve=$clone($firstCurve);$secondCurve->dataset_key=$spec->data->specification->curve_ref->dataset_key;$secondCurve->data->component_key=$second->component_key;$b->datasets[]=$secondCurve;
	$source=$d->hydraulic_loops[1];$edge=$source->edges[0];$source->edges[0]=[$edge[0],$second->instance_key.'.in'];array_splice($source->edges,1,0,[[$second->instance_key.'.out',$edge[1]]]);
	foreach($b->datasets as $r){
		if($r->dataset_type==='compressor_polynomial'){foreach(['cooling_capacity','power_input'] as $q){$constant=$q==='cooling_capacity'?($r->data->model==='SYNTHETIC-COMPRESSOR-B'?'20000':'10000'):($r->data->model==='SYNTHETIC-COMPRESSOR-B'?'5150':'2000');$r->data->quantities->$q->coefficients_C0_to_C9=array_pad([$constant],10,'0');}}
		if($r->dataset_type==='operating_mode_model'){$r->data->contract_version=2;$r->data->enabled_components[]=$second->instance_key;unset($r->data->points);$r->data->coupling=(object)['model_id'=>'synthetic_constant_compressor_two_liquid_loops_v1','secondary_heat_boundary'=>'synthetic_pump_and_dissipation_heat_removed_by_external_thermostats_v1','source_locator'=>'Explicit synthetic isothermal motor and pressure-loss thermostats; all pump input heat is removed outside the HX heat boundary'];$r->data->numerics=(object)['heat_tolerance_kW'=>'0.0000001','pressure_tolerance_Pa'=>'0.000001','temperature_tolerance_K'=>'0.00000001','max_iterations'=>60];}
		if($r->dataset_type==='heat_exchanger_map'){$old=$r->data;$r->data=(object)['contract_version'=>3,'usage'=>'synthetic_test_only','component_key'=>$old->component_key,'model'=>$old->model,'role'=>$old->role,'exchanger_type'=>$old->exchanger_type,'refrigerant'=>$old->refrigerant,'temperature_basis'=>'pure_saturation','model_id'=>'synthetic_fluid_enthalpy_effectiveness_v1','measurement_boundary'=>'secondary_stream_only_no_external_heat_v1','refrigerant_sections_are_isolated'=>true,'fluid_ref'=>$old->fluid_ref,'fluid_composition'=>$old->fluid_composition,'sections'=>[(object)['section_key'=>$old->sections[0]->section_key,'effectiveness'=>'0.5','limits'=>(object)['saturation_temperature'=>thermalRangeFixture('0','60'),'secondary_inlet'=>thermalRangeFixture('0','60'),'secondary_outlet'=>thermalRangeFixture('0','70'),'volume_flow'=>thermalRangeFixture('0.1','30','m3/h')]]],'missing_evidence'=>['real_oem_liquid_effectiveness_missing']];}
		if($r->dataset_type==='thermal_balance_model'){$r->data->search_bounds->Te=thermalRangeFixture('2','8');$r->data->search_bounds->Tc=thermalRangeFixture('44','56');}
		if($r->dataset_type==='hydraulic_path'&&$r->data->loop_key===$source->loop_key){$r->data->pump_instance=$second->instance_key;$r->data->connections=array_map(fn($edge)=>(object)['edge_from'=>$edge[0],'edge_to'=>$edge[1],'loss_basis'=>'documented_zero','source_locator'=>'Explicit synthetic zero connection losses'],$source->edges);}
		if($r->dataset_type==='pump_curve'){$r->data->ordinate_unit='Pa';$head=$r===$secondCurve?8358.75:12600;foreach($r->data->points as $p){$p->ordinate=json_encode($head-1000*(float)$p->volume_flow_m3_h,JSON_THROW_ON_ERROR);} $r->data->points[1]->volume_flow_m3_h='8';$r->data->points[1]->ordinate=json_encode($head-8000,JSON_THROW_ON_ERROR);}
	}
	if($variable)foreach($fluid->data->properties[0] as $i=>$p){$t=(float)$fluid->data->temperatures_degC[$i];$p->density_kg_m3=(string)(1040-2*$t);$p->specific_heat_J_kg_K=(string)(3800+10*$t);}
	foreach($b->datasets as $r)if($r->dataset_type==='pressure_loss_map')foreach($r->data->sections as $section)foreach($section->pressure_losses as $ci=>&$rows)foreach($rows as $ti=>&$flows)foreach($flows as $vi=>&$value){$factor=$r->data->role==='external'?500:1000;$viscosity=$variable?(float)$fluid->data->properties[$ci][$ti]->dynamic_viscosity_Pa_s/0.07:1;$value=json_encode($factor*(float)$r->data->volume_flows_m3_h[$vi]*$viscosity,JSON_THROW_ON_ERROR);}unset($rows,$flows,$value);
	$b->template_kind='synthetic_coupled_circuit_test_v1';$b->provenance_note='Invented coupled test data; no real OEM values.';
	foreach($b->datasets as $r)$r->import_context=(object)['template_kind'=>$b->template_kind,'production_import_allowed'=>false,'provenance_note'=>$b->provenance_note];return $b;
}

function thermohydraulicRequest(string $mode='cooling'): stdClass
{
	return (object)['operating_mode'=>$mode,'stage_key'=>'full','valve_states'=>[],'secondary_boundaries'=>(object)['evaporator'=>(object)['inlet_degC'=>'20'],'condenser'=>(object)['inlet_degC'=>'30']]];
}
