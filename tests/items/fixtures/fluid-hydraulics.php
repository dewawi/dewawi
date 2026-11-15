<?php

require_once __DIR__.'/thermal-machine.php';

function fluidFixture(string $mixture='water', string $basis='mass_fraction', bool $constant=false, string $key='fixture:d5:fluid'): stdClass
{
	$ts=['-10','0','20','80'];$cs=$mixture==='water'?['0']:['0','0.2','0.4'];$table=[];
	foreach($cs as $c){$row=[];foreach($ts as $t){$row[]=(object)['density_kg_m3'=>$constant?'1000':(string)(1100-5*(float)$t+100*(float)$c),'specific_heat_J_kg_K'=>$constant?'4000':(string)(3000+50*(float)$t-1000*(float)$c),'dynamic_viscosity_Pa_s'=>(string)((0.08-0.0005*(float)$t)*(1+5*(float)$c))];}$table[]=$row;}
	$r=technicalDraft($key,'fluid_properties',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','fluid_key'=>$key,'mixture'=>$mixture,'concentration_basis'=>$basis,'volume_reference_degC'=>$basis==='volume_fraction'?'20':null,'interpolation'=>'bilinear_cp_integral_v1','units'=>(object)['temperature'=>'degC','concentration'=>'fraction','density'=>'kg/m3','specific_heat'=>'J/(kg K)','dynamic_viscosity'=>'Pa s','specific_enthalpy'=>'J/kg'],'temperatures_degC'=>$ts,'concentrations'=>$cs,'properties'=>$table,'freezing_degC'=>array_map(fn($c)=>(string)(-15-50*(float)$c),$cs),'missing_evidence'=>[]]);
	$r->import_context=(object)['template_kind'=>'synthetic_fluid_hydraulic_test_v1','production_import_allowed'=>false,'provenance_note'=>'Invented properties; no physical water or glycol data.'];return $r;
}

function fluidComposition(string $fraction='0', string $basis='mass_fraction'): stdClass
{
	return (object)['basis'=>$basis,'fraction'=>$fraction,'volume_reference_degC'=>$basis==='volume_fraction'?'20':null];
}

function pressureFixture(stdClass $fluid, string $key, string $componentKey, string $model, string $role, array $flows, float $drop=4000): stdClass
{
	$sections=[];foreach($flows as $section=>$ratedFlow){$grid=[];foreach($fluid->data->concentrations as $ci=>$c){$row=[];foreach($fluid->data->temperatures_degC as $ti=>$t){$mu=$role==='external'?0.075:(float)$fluid->data->properties[$ci][$ti]->dynamic_viscosity_Pa_s;$row[]=array_map(fn($v)=>(string)($drop*(float)$v/$ratedFlow*$mu/0.075),['0.1','10','40']);}$grid[]=$row;}$sections[]=(object)['section_key'=>$section,'pressure_losses'=>$grid];}
	$r=technicalDraft($key,'pressure_loss_map',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','component_key'=>$componentKey,'model'=>$model,'role'=>$role,'fluid_ref'=>(object)['dataset_key'=>$fluid->dataset_key,'revision'=>1],'interpolation'=>'trilinear_flow_temperature_concentration_v1','measurement_boundary'=>$role==='external'?'external_to_machine':'flange_to_flange','pressure_unit'=>'Pa','volume_flow_unit'=>'m3/h','temperatures_degC'=>$fluid->data->temperatures_degC,'concentrations'=>$fluid->data->concentrations,'volume_flows_m3_h'=>['0.1','10','40'],'sections'=>$sections,'missing_evidence'=>[]]);$r->import_context=clone $fluid->import_context;return $r;
}

function pumpFixture(stdClass $fluid, string $key, string $componentKey='fixture:pump', string $model='SYNTHETIC'): stdClass
{
	$r=technicalDraft($key,'pump_curve',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','component_key'=>$componentKey,'model'=>$model,'fluid_ref'=>(object)['dataset_key'=>$fluid->dataset_key,'revision'=>1],'model_id'=>'piecewise_linear_fixed_speed_v1','measurement_boundary'=>'pump_flange_differential','speed_rpm'=>'2900','frequency_Hz'=>'50','operating_mode'=>'fixed','ordinate_unit'=>'m','volume_flow_unit'=>'m3/h','limits'=>(object)['temperature_degC'=>(object)['min'=>'-10','max'=>'80'],'concentration'=>(object)['min'=>$fluid->data->concentrations[0],'max'=>end($fluid->data->concentrations)],'density_kg_m3'=>(object)['min'=>'100','max'=>'2000'],'dynamic_viscosity_Pa_s'=>(object)['min'=>'0.001','max'=>'1']],'points'=>[(object)['volume_flow_m3_h'=>'0.1','ordinate'=>'10','hydraulic_efficiency'=>'0.7','total_efficiency'=>'0.5','electrical_input_W'=>null],(object)['volume_flow_m3_h'=>'40','ordinate'=>'5','hydraulic_efficiency'=>'0.7','total_efficiency'=>'0.5','electrical_input_W'=>null]],'missing_evidence'=>[]]);$r->import_context=clone $fluid->import_context;return $r;
}

function fluidMachineBundle(string $prefix='fixture:d5:machine'): stdClass
{
	$b=thermalMachineBundle('both_shared',$prefix);$fluid=fluidFixture('water','mass_fraction',true,$prefix.':fluid');$b->datasets[]=$fluid;$ref=(object)['dataset_key'=>$fluid->dataset_key,'revision'=>1];
	foreach($b->datasets as $r){if($r->dataset_type==='machine_configuration')$machine=$r;if($r->dataset_type==='thermal_balance_model'&&$r->data->contract_version===2)$model=$r->data;}
	$specs=[];foreach($b->datasets as $r)if($r->dataset_type==='component_spec')$specs[$r->data->role]=$r;
	foreach($machine->data->hydraulic_loops as $loop){$loop->fluid_dataset=clone $ref;$members=[];$pump=null;
		foreach($machine->data->components as $component)foreach($component->ports as $port=>$kind)if($kind==='liquid_in')foreach($loop->edges as $edge)if($edge[1]===$component->instance_key.'.'.$port){$spec=$specs[$component->role];if($component->role==='pump'){$pump=$component->instance_key;$curve=pumpFixture($fluid,$prefix.':pump-curve',$spec->data->component_key,$spec->data->model);$spec->data->specification->curve_ref=(object)['dataset_key'=>$curve->dataset_key,'revision'=>1];$spec->data->specification->fluid_ref=clone $ref;$spec->data->specification->speed_rpm='2900';$spec->data->specification->frequency_Hz='50';$b->datasets[]=$curve;continue;}
			$members[]=$component->instance_key;$flows=['whole'=>19.62];foreach($model->secondary_groups as $g)if($g->instance_key===$component->instance_key){$flows=[];foreach($g->branches as $branch)$flows[$branch->section_key]=(float)$branch->volume_flow_m3_h;}
			$map=pressureFixture($fluid,$prefix.':loss:'.$component->instance_key,$spec->data->component_key,$spec->data->model,$spec->data->role,$flows,$component->role==='tank'?2000:4000);$spec->data->specification->pressure_loss_ref=(object)['dataset_key'=>$map->dataset_key,'revision'=>1];$b->datasets[]=$map;
		}
		$total=$loop->loop_key==='load'?19.62:11.064;$external=pressureFixture($fluid,$prefix.':external:'.$loop->loop_key,'fixture:external','SYNTHETIC-EXTERNAL','external',['whole'=>$total],1000);$b->datasets[]=$external;
		$path=technicalDraft($prefix.':path:'.$loop->loop_key,'hydraulic_path',(object)['contract_version'=>1,'usage'=>'synthetic_test_only','loop_key'=>$loop->loop_key,'topology_kind'=>'serial_components_parallel_sections_v1','component_instances'=>$members,'pump_instance'=>$pump,'external_loss_ref'=>(object)['dataset_key'=>$external->dataset_key,'revision'=>1],'pipe_segments'=>[],'pressure_boundary'=>'closed_loop_dissipative_only_v1','connections'=>array_map(fn($edge)=>(object)['edge_from'=>$edge[0],'edge_to'=>$edge[1],'loss_basis'=>'documented_zero','source_locator'=>'Invented direct ideal connections, synthetic only'],$loop->edges),'missing_evidence'=>[]]);$path->import_context=clone $fluid->import_context;$loop->pressure_loss_dataset=(object)['dataset_key'=>$path->dataset_key,'revision'=>1];$b->datasets[]=$path;
	}
	foreach($b->datasets as $record)$record->import_context=(object)['template_kind'=>$b->template_kind,'production_import_allowed'=>false,'provenance_note'=>$b->provenance_note];
	return $b;
}

function fluidHydraulicRequest(stdClass $bundle): stdClass
{
	foreach($bundle->datasets as $r){if($r->dataset_type==='machine_configuration')$machine=$r->data;if($r->dataset_type==='thermal_balance_model'&&$r->data->contract_version===2)$model=$r->data;}
	$loops=[];foreach($machine->hydraulic_loops as $loop){$tin=$loop->loop_key==='load'?'10':'30';$states=[];$pump=null;foreach($model->secondary_groups as $g)if($g->hydraulic_loop_key===$loop->loop_key){$total=$g->total_volume_flow_m3_h;$sections=[];foreach($g->branches as $branch)$sections[]=(object)['section_key'=>$branch->section_key,'temperature_degC'=>$tin,'volume_flow_m3_h'=>$branch->volume_flow_m3_h];$states[]=(object)['instance_key'=>$g->instance_key,'sections'=>$sections];}
		if($loop->loop_key==='load'){$states[]=(object)['instance_key'=>'tank','sections'=>[(object)['section_key'=>'whole','temperature_degC'=>$tin,'volume_flow_m3_h'=>$total]]];$pump=(object)['temperature_degC'=>$tin,'speed_rpm'=>'2900','frequency_Hz'=>'50','operating_mode'=>'fixed'];}
		$loops[]=(object)['loop_key'=>$loop->loop_key,'composition'=>fluidComposition(),'flow'=>(object)['unit'=>'m3/h','value'=>$total,'at_degC'=>$tin],'component_states'=>$states,'pipe_states'=>[],'external_state'=>(object)['temperature_degC'=>$loop->loop_key==='load'?'7.275229357798166':'35.989018438177872','volume_flow_m3_h'=>$total],'pump_condition'=>$pump];
	}return (object)['loops'=>$loops];
}
