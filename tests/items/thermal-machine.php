<?php

foreach(['TechnicalJson','TechnicalSchema','CompressorPolynomial','MachineStage','HeatExchangerMap','ThermalCircuit','ThermalMachine'] as $class)require_once __DIR__.'/../../application/modules/items/services/'.$class.'.php';
require_once __DIR__.'/fixtures/thermal-machine.php';
$count=0;
$check=function(bool $ok,string $message)use(&$count):void { $count++;if(!$ok)throw new RuntimeException($message); };
$reject=function(callable $work,string $message)use($check):void { try{$work();}catch(InvalidArgumentException|RuntimeException $e){$check(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message); };
$clone=fn($v)=>unserialize(serialize($v));$near=fn($a,$b,$tol=1e-7)=>$a!==null&&abs((float)$a-$b)<=$tol;
$find=function($bundle,$suffix) { foreach($bundle->datasets as $r)if(str_ends_with($r->dataset_key,$suffix))return $r;throw new RuntimeException('Missing fixture'); };
$evaluate=function($bundle,$request,$blocker=null) { [$m,$r,$c]=thermalCallbacks($bundle);return (new Items_Service_ThermalMachine())->evaluate($m,$request,$r,$c,$blocker); };
foreach([['0','0','0'],['0.1','0.2','3e-1'],['10.8','8.82','1.962e1'],['6.687','4.377','1.1064e1'],['1e-3','1e3','1.000001e3'],['999','1','1e3']] as [$a,$b,$expected])$check(Items_Service_TechnicalJson::addNonnegativeDecimals($a,$b)===$expected,'Exact decimal stream addition');
$reject(fn()=>Items_Service_TechnicalJson::addNonnegativeDecimals('-1','2'),'Negative stream addition');
foreach(['independent'=>10,'shared_evaporator'=>9,'shared_condenser'=>9,'both_shared'=>8] as $kind=>$physicalCount) {
	$b=thermalMachineBundle($kind);$request=thermalMachineRequest($b);$r=$evaluate($b,$request);$s=$r['solution'];
	$check($r['convergence']['status']==='converged' && $s!==null,'Parallel case did not converge: '.$kind);
	$check($r['eligible']===false && $s['eligible']===false && $r['status']==='review_required' && $r['useful_machine_cooling_capacity']['value']===null,'Synthetic result released machine');
	$check($s['physical_component_count']===$physicalCount && count($s['components'])===$physicalCount,'Shared physical exchanger duplicated');
	$check($near($s['circuits']['r1']['point']->Te_degC,5.0,1e-8) && $near($s['circuits']['r1']['point']->Tc_degC,50.0,1e-8),'Independent r1 reference');
	$check($near($s['circuits']['r2']['point']->Te_degC,2.5,1e-8) && $near($s['circuits']['r2']['point']->Tc_degC,45.0,1e-8),'Independent r2 reference');
	$t=$s['thermal_totals'];$check($near($t['compressor_cooling_kW'],59.4) && $near($t['compressor_power_kW'],14.225) && $near($t['evaporator_heat_kW'],59.4) && $near($t['condenser_heat_kW'],73.625),'Independent total heat balances');
	$check(max(array_map('abs',$t['energy_residual_enclosure_kW']))<=1e-7,'Global energy residual outside tolerance');
	$check(count($s['compressors'])===4 && count(array_filter($s['compressors'],fn($c)=>$c['active']))===4,'Wrong actual compressor instances');
	$check($r['convergence']['evaluations']<=$r['convergence']['maximum_evaluations'] && $r['convergence']['maximum_evaluations']===7440,'Unbounded circuit iteration');
	$check(json_encode($r,JSON_THROW_ON_ERROR)===json_encode($evaluate($b,$request),JSON_THROW_ON_ERROR),'Parallel result not deterministic');
	foreach($s['secondary_groups'] as $group) {
		$g=$group['balance'];$check($g['volume_flow_residual_m3_h']==='0' && $g['branch_volume_flow_sum_m3_h']===$g['total_volume_flow_m3_h'],'Branch flows do not balance exactly');
		$check(max(array_map('abs',array_merge($g['secondary_heat_residual_enclosure_kW'],$g['compressor_heat_residual_enclosure_kW'])))<=1e-7,'Shared energy residual outside tolerance');
		$check($g['mixed_outlet_error_bound_K']<=1e-8 && $g['eligible']===false,'Invalid mixture precision or release');
	}
	if(isset($s['secondary_groups']['load'])) {
		$g=$s['secondary_groups']['load']['balance'];
		$check($near($g['section_outlets_degC']['r1'],7.5) && $near($g['section_outlets_degC']['r2'],7.0),'Different section outlets lost');
		$check($near($g['mixed_outlet_degC'],(12.0*7.5+9.8*7.0)/21.8),'Mixture is not weighted by capacity flow');
		$check($near(21.8*(10.0-$g['mixed_outlet_degC']),59.4),'Independent shared evaporator conservation');
		$check($near($g['heat_flow_sum_kW'],59.4) && !$near($g['heat_flow_sum_kW'],118.8),'Common capacity counted twice');
	}
	if(isset($s['secondary_groups']['source'])) {
		$g=$s['secondary_groups']['source']['balance'];
		$check($near($g['mixed_outlet_degC'],(7.43*35.0+(4.377/0.9)*37.5)/(7.43+4.377/0.9)),'Independent condenser mixture');
		$check($near((7.43+4.377/0.9)*($g['mixed_outlet_degC']-30.0),73.625),'Independent shared condenser conservation');
	}
	if($kind==='both_shared')$full=$r;
}
// Unsectioned legacy condenser ports retain their sole explicitly named map section.
$b=thermalMachineBundle('independent');
foreach($b->datasets as $record) {
	if($record->dataset_type==='heat_exchanger_map'&&$record->data->role==='water_condenser')$record->data->sections[0]->section_key='rated';
	if($record->dataset_type==='component_spec'&&$record->data->role==='water_condenser')$record->data->specification->sections=['rated'];
	if($record->dataset_type==='thermal_balance_model'&&$record->data->contract_version===2)foreach($record->data->secondary_groups as $g)if($g->side==='condenser')$g->branches[0]->section_key='rated';
}
$r=$evaluate($b,thermalMachineRequest($b));$check($r['convergence']['status']==='converged'&&$r['solution']['circuits']['r1']['sections']['condenser']['section_key']==='rated','Legacy non-main section was guessed or rejected');
// Different actual active models produce independent linear reference systems per circuit.
foreach(range(1,4) as $stage) {
	$b=thermalMachineBundle('both_shared');$request=thermalMachineRequest($b,'stage'.$stage);$r=$evaluate($b,$request);$s=$r['solution'];
	$check($r['convergence']['status']==='converged' && count(array_filter($s['compressors'],fn($c)=>$c['active']))===$stage,'Concrete operating-stage activation');
	foreach(['r1','r2'] as $key) {
		$w=$key==='r1'?($stage>=3?3.0:1.0):($stage>=4?3.0:($stage>=2?1.0:0.0));$p0=$w===3.0?7.0:2.0;
		if($w===0.0) { $check($s['circuits'][$key]['point']===null && $s['circuits'][$key]['energy_balance']['compressor_cooling_kW']===0.0,'Inactive circle acquired a temperature or thermal load');continue; }
		$ke=$key==='r1'?6.0:3.92;$kc=$key==='r1'?1.8575:(4.377/0.9*0.5);
		$a=$ke+0.1*$w;$bb=-0.01*$w;$cc=-0.11*$w;$d=$kc+0.01*$w;$rhs1=$ke*10.0-10.0*$w;$rhs2=10.0*$w+$p0+$kc*30.0;$det=$a*$d-$bb*$cc;
		$te=($rhs1*$d-$bb*$rhs2)/$det;$tc=($a*$rhs2-$cc*$rhs1)/$det;
		$check($near($s['circuits'][$key]['point']->Te_degC,$te) && $near($s['circuits'][$key]['point']->Tc_degC,$tc),'Independent partial-stage analytical root');
	}
	$load=$s['secondary_groups']['load']['balance'];$check($load['total_volume_flow_m3_h']==='1.962e1','Inactive compressor silently changed prescribed branch flow');
	if($stage<4)$check($load['heat_flow_sum_kW']<$full['solution']['secondary_groups']['load']['balance']['heat_flow_sum_kW'] && $load['mixed_outlet_degC']>$full['solution']['secondary_groups']['load']['balance']['mixed_outlet_degC'],'Stage load did not change common mixture');
	foreach($s['compressors'] as $c)if(!$c['active'])$check($c['evaluation']===null,'Inactive compressor evaluated');
}
$b=thermalMachineBundle('both_shared');$request=thermalMachineRequest($b);$find($b,':compressor')->data->quantities->cooling_capacity->coefficients_C0_to_C9[0]='10500';
$r=$evaluate($b,$request);$check($r['convergence']['status']==='converged' && $r['solution']['secondary_groups']['load']['balance']['heat_flow_sum_kW']>$full['solution']['secondary_groups']['load']['balance']['heat_flow_sum_kW'] && $r['solution']['secondary_groups']['load']['balance']['mixed_outlet_degC']<$full['solution']['secondary_groups']['load']['balance']['mixed_outlet_degC'],'Changed curve did not alter shared energy balance');
foreach(['flow_total','tiny_flow_mismatch','duplicate_section','missing_branch','unallocated_map_section','wrong_circuit','wrong_instance','units','serial','counterflow','reused_loop','wrong_port','missing_boundary','duplicate_boundary','mode','unknown_input','inactive_context','nonisolated','cross_refrigerant'] as $case) {
	$b=thermalMachineBundle('both_shared');$request=thermalMachineRequest($b);$m=$find($b,':parallel-model')->data;$config=$find($b,':machine:2')->data;
	switch($case) {
		case 'flow_total':$m->secondary_groups[0]->total_volume_flow_m3_h='20';break;
		case 'tiny_flow_mismatch':$m->secondary_groups[0]->total_volume_flow_m3_h='19.6200000000000000000001';break;
		case 'duplicate_section':$m->secondary_groups[0]->branches[1]->section_key='r1';break;
		case 'missing_branch':array_pop($m->secondary_groups[0]->branches);break;
		case 'unallocated_map_section':$map=$find($b,':evaporator-map');$extra=$clone($map->data->sections[0]);$extra->section_key='spare';$map->data->sections[]=$extra;$find($b,':evaporator')->data->specification->sections[]='spare';break;
		case 'wrong_circuit':$m->secondary_groups[0]->branches[1]->circuit_key='other';break;
		case 'wrong_instance':$m->secondary_groups[0]->instance_key='missing';break;
		case 'units':$m->variables->capacity_rate='W/K';break;
		case 'serial':$m->secondary_groups[0]->topology_kind='serial_sections';break;
		case 'counterflow':$m->secondary_groups[0]->topology_kind='counterflow_sections';break;
		case 'reused_loop':$m->secondary_groups[1]->hydraulic_loop_key=$m->secondary_groups[0]->hydraulic_loop_key;break;
		case 'wrong_port':$m->secondary_groups[0]->outlet_port='pump.out';break;
		case 'missing_boundary':array_pop($request->secondary_boundaries);break;
		case 'duplicate_boundary':$request->secondary_boundaries[]=$request->secondary_boundaries[0];break;
		case 'mode':$request->operating_mode='full_heat_recovery';break;
		case 'unknown_input':$request->eligible=true;break;
		case 'inactive_context':$request->stage_key='stage1';$request->compressor_contexts=(object)['r2.compressor1'=>new stdClass()];break;
		case 'nonisolated':$config->shared_components[count($config->shared_components)-1]->refrigerant_sections_are_isolated=false;break;
		case 'cross_refrigerant':$config->circuits[1]->refrigerant='other-fluid';break;
	}
	$reject(fn()=>$evaluate($b,$request),'Invalid shared contract '.$case);
}
foreach(['null_split','null_total','no_map','missing_section_map','no_properties','boundary_flow','boundary_medium','flow_limit','mix_limit','inactive_outlet','junction_tolerance','junction_temperature','nonconvergence','ambiguity','extrapolation','real_machine','technical','withdrawal'] as $case) {
	$b=thermalMachineBundle('both_shared');$request=thermalMachineRequest($b);$m=$find($b,':parallel-model')->data;$map=$find($b,':evaporator-map');
	switch($case) {
		case 'null_split':$m->secondary_groups[0]->branches[1]->volume_flow_m3_h=null;break;
		case 'null_total':$m->secondary_groups[0]->total_volume_flow_m3_h=null;break;
		case 'no_map':$find($b,':evaporator')->data->specification->thermal_map_ref=null;break;
		case 'missing_section_map':array_pop($map->data->sections);$find($b,':evaporator')->data->specification->sections=['r1'];break;
		case 'no_properties':$map->data->media[0]->properties->specific_heat_kJ_kg_K=null;break;
		case 'boundary_flow':$request->secondary_boundaries[0]->boundary->volume_flow_m3_h='20';break;
		case 'boundary_medium':$request->secondary_boundaries[0]->boundary->medium_key='other';break;
		case 'flow_limit':$map->data->sections[0]->limits->volume_flow=thermalRangeFixture('0.1','10','m3/h');break;
		case 'mix_limit':$m->secondary_groups[0]->limits->mixed_outlet=thermalRangeFixture('8','9');break;
		case 'inactive_outlet':$request->stage_key='stage1';$map->data->sections[1]->limits->secondary_outlet=thermalRangeFixture('0','9');break;
		case 'junction_temperature':$m->numerics->heat_tolerance_kW='1e-3';$m->numerics->temperature_tolerance_K='1e-9';foreach(['r1','r2'] as $key){$n=$find($b,':circuit-model:'.$key)->data->numerics;$n->temperature_tolerance_K='1e-6';$n->heat_tolerance_kW='1e-5';}break;
		case 'junction_tolerance':$m->numerics->heat_tolerance_kW='1e-9';break;
		case 'nonconvergence':$find($b,':circuit-model:r2')->data->numerics->max_outer_iterations=1;break;
		case 'ambiguity':$find($b,':compressor')->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_pad(['60000','-7000'],10,'0');break;
		case 'extrapolation':$find($b,':circuit-model:r2')->data->search_bounds->Tc=thermalRangeFixture('31','61');break;
		case 'real_machine':unset($find($b,':machine:2')->import_context);break;
	}
	if($case==='missing_section_map') { $reject(fn()=>$evaluate($b,$request),'Missing section is not guessed');continue; }
	$r=$evaluate($b,$request,in_array($case,['technical','withdrawal'],true)?'calculation_blocked':null);
	$check($r['solution']===null && $r['eligible']===false && $r['reasons'],'Blocked case yielded partial shared solution: '.$case);
	$check(json_encode($r,JSON_THROW_ON_ERROR)!=='','Nonfinite shared output');
	if(in_array($case,['boundary_flow','boundary_medium','flow_limit','mix_limit','inactive_outlet','extrapolation'],true))$check($r['status']==='unsuitable','Proven range/input violation not rejected');
	if(in_array($case,['junction_tolerance','junction_temperature'],true))$check($r['convergence']['status']==='junction_balance_not_converged','Shared tolerance failure yielded a partial result');
	if($case==='inactive_outlet')$check($r['convergence']['status']==='inactive_boundary_blocked','Parked boundary failure hidden');
	if($case==='nonconvergence')$check($r['diagnostics']['r2']['convergence']['status']==='outer_iteration_limit','Partial nonconvergence hidden');
	if($case==='ambiguity')$check($r['convergence']['evaluations']===0 && in_array('r1:solution_uniqueness_not_proven',$r['reasons'],true),'Ambiguity searched');
}
$b=thermalBundle('water','fixture:parallel:one',2);
// D4B1's original 2/1 fixture needs a larger explicitly configured map for both active models.
$request=thermalRequest('water');$request->evaporator->volume_flow_m3_h='10.8';$request->condenser->volume_flow_m3_h='4.458';$r=$evaluate($b,$request);
$check($r['convergence']['status']==='converged' && $near($r['solution']['point']->Te_degC,5.0) && $near($r['solution']['point']->Tc_degC,50.0),'2/1 existing solver regression');
$b=machineStageBundle(4);[$machine,$resolve,$compressor]=thermalCallbacks($b);$r=(new Items_Service_ThermalMachine())->evaluate($machine,(object)['stage_key'=>'full','operating_mode'=>'cooling','secondary_boundaries'=>[]],$resolve,$compressor);
$check($r['solution']===null && in_array('thermal_parallel_circuit_count_not_supported',$r['reasons'],true),'Unsupported 8/4 thermal case guessed');
$stage=(new Items_Service_MachineStage())->evaluate($machine,machineStageRequest(4),$compressor);$check($near($stage['stage_compressor_sums']['cooling_capacity']['value'],120.0) && count($stage['compressors'])===8,'8/4 structural/D4A compatibility');
echo "PASS: $count parallel thermal assertions (independent/shared evaporator/condenser, 4/2 analytic balances, fixed flows/mixing, activation, uniqueness, bounds and 2/1 + 8/4 compatibility)\n";
