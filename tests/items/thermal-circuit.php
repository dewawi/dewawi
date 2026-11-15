<?php

foreach(['TechnicalJson', 'TechnicalSchema', 'CompressorPolynomial', 'MachineStage', 'HeatExchangerMap', 'ThermalCircuit'] as $class) require_once __DIR__.'/../../application/modules/items/services/'.$class.'.php';
require_once __DIR__.'/fixtures/thermal-circuit.php';

$count = 0;
$check = function(bool $ok, string $message) use (&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
$reject = function(callable $work, string $message) use ($check): void {
	try { $work(); } catch(InvalidArgumentException|RuntimeException $e) { $check(true, $message); return; }
	throw new RuntimeException('Expected rejection: '.$message);
};
$clone = fn($v) => unserialize(serialize($v));
$evaluate = function(stdClass $bundle, stdClass $request, ?string $blocker = null): array {
	[$machine, $resolve, $compressor] = thermalCallbacks($bundle);
	return (new Items_Service_ThermalCircuit())->evaluate($machine, $request, $resolve, $compressor, $blocker);
};
$find = function(stdClass $bundle, string $suffix): stdClass {
	foreach($bundle->datasets as $record) if(str_ends_with($record->dataset_key, $suffix)) return $record;
	throw new RuntimeException('Missing synthetic fixture');
};
$near = fn($a, $b, $tolerance = 1e-7) => $a !== null && abs((float)$a - $b) <= $tolerance;
foreach(['air', 'water'] as $kind) {
	$b = thermalBundle($kind); $request = thermalRequest($kind); $result = $evaluate($b, $request); $solution = $result['solution'];
	$check($result['convergence']['status'] === 'converged' && $result['uniqueness']['certified'], 'Known unique reference did not converge');
	$check($near($solution['point']->Te_degC, 5.0, 1e-8) && $near($solution['point']->Tc_degC, 50.0, 1e-8), 'Independent analytical Te5/Tc50 reference');
	$balance = $solution['energy_balance'];
	$check($near($balance['compressor_cooling_kW'], 10.0) && $near($balance['compressor_power_kW'], 2.05) && $near($balance['required_condenser_heat_kW'], 12.05), 'Independent compressor/energy balance');
	$check($near($solution['evaporator']['heat_flow_kW'], 10.0) && $near($solution['condenser']['heat_flow_kW'], 12.05), 'Independent exchanger balances');
	$check(max(abs($balance['residual_evaporator_kW']), abs($balance['residual_condenser_kW'])) <= 1e-7, 'Heat residual tolerance');
	$check(max(array_map('abs',array_merge(...$solution['residual_enclosures_kW'])))<=1e-7 && max($solution['temperature_error_bound_K'])<=1e-8,'Certified residual/temperature error tolerances');
	$check($near($solution['evaporator']['secondary_outlet_degC'], 7.5) && $near($solution['condenser']['secondary_outlet_degC'], 42.5), 'Explicit cp/flow outlet balances');
	$check($result['eligible'] === false && $solution['compressor_stage']['eligible'] === false && $result['status'] === 'review_required' && $result['useful_machine_cooling_capacity']['value'] === null && $result['machine_efficiency']['value'] === null, 'Synthetic convergence released machine');
	foreach($solution['search_brackets_degC'] as $bracket)$check($bracket[1]-$bracket[0]<=1e-8,'Temperature convergence bracket tolerance');
	$check($result['convergence']['outer_iterations'] <= 60 && $result['convergence']['inner_iterations'] <= 62 * 60, 'Unbounded iteration');
	$check(json_encode($result, JSON_THROW_ON_ERROR) === json_encode($evaluate($b,$request), JSON_THROW_ON_ERROR), 'Nonreproducible thermal result');
	foreach(['expansion_system_evidence_missing','control_release_missing','evaporator:pressure_loss_evidence_missing','condenser:pressure_loss_evidence_missing'] as $reason) $check(in_array($reason,$result['reasons'],true),'Missing engineering blocker');
	if($kind === 'air') $airResult = $result;
}
// Independent 2x2 solution for a changed exchanger, rather than copying solver arithmetic.
$b = thermalBundle(); $find($b,':evaporator-map')->data->sections[0]->effectiveness = '0.4';
$r = $evaluate($b, thermalRequest());
$a = 1.7; $bcoef = -0.01; $c = -0.11; $d = 241.0/300.0 + 0.01; $rhs1 = 6.0; $rhs2 = 12.0 + 241.0/300.0*35.0;
$det = $a*$d-$bcoef*$c; $te = ($rhs1*$d-$bcoef*$rhs2)/$det; $tc = ($a*$rhs2-$c*$rhs1)/$det;
$check($near($r['solution']['point']->Te_degC,$te) && $near($r['solution']['point']->Tc_degC,$tc) && !$near($te,5.0),'D2-069: different map did not change operating point');
// The full D3 cubic remains usable when its derivative intervals establish uniqueness.
$b = thermalBundle(); $curve = $find($b,':compressor');
$curve->data->quantities->cooling_capacity->coefficients_C0_to_C9[0] = '9975';
$curve->data->quantities->cooling_capacity->coefficients_C0_to_C9[3] = '1';
$r = $evaluate($b,thermalRequest());
$check($r['convergence']['status']==='converged' && $near($r['solution']['point']->Te_degC,5.0) && $near($r['solution']['point']->Tc_degC,50.0),'Certified nonlinear polynomial');
// Same approach and different water-side spread, with every property/flow supplied explicitly.
foreach([['15','1.8','0.5',5.0],['17','1.2857142857142858','0.5833333333333334',7.0]] as [$tin,$flow,$effectiveness,$spread]) {
	$b=thermalBundle();$find($b,':evaporator-map')->data->sections[0]->effectiveness=$effectiveness;
	$request=thermalRequest();$request->evaporator->inlet_degC=$tin;$request->evaporator->volume_flow_m3_h=$flow;
	$r=$evaluate($b,$request);$evap=$r['solution']['evaporator'];
	$check($near($evap['secondary_delta_T_K'],$spread) && $near($evap['approach_K'],5.0),'D2-072: water spread conflated with approach');
}
$b=thermalBundle();$request=thermalRequest();
$request->temperature_offset_probe=(object)['model_id'=>'fixed_temperature_offsets_v1','ambient_degC'=>'35','supply_degC'=>'10','condensing_offset_K'=>'15','evaporator_approach_K'=>'5','water_delta_T_K'=>'7'];
$r=$evaluate($b,$request);
$check($r['temperature_offset_probe']['Te_degC']===5.0 && $r['temperature_offset_probe']['Tc_degC']===50.0 && $r['temperature_offset_probe']['eligible']===false,'D2-071: explicit flagged offset probe');
$check(json_encode($r['solution'],JSON_THROW_ON_ERROR)===json_encode($airResult['solution'],JSON_THROW_ON_ERROR) && !isset($airResult['temperature_offset_probe']),'Offset probe changed solver or appeared by default');
$copy=$clone($request);$r=$evaluate($b,$copy);$copy->evaporator->inlet_degC='12';
$check($r['original_request']->evaporator->inlet_degC==='10','Historical input mutated');
foreach(['unit','formula','model','effectiveness','unisolated','balance','iterations','tolerance','unknown_field','missing_flow','air_on_water','mode'] as $case) {
	$b=thermalBundle();$request=thermalRequest();$map=$find($b,':evaporator-map');$model=$find($b,':thermal-model');
	switch($case) {
		case 'unit':$map->data->variables->heat_flow='W';break;
		case 'formula':$map->data->expression='eval()';break;
		case 'model':$map->data->model_id='custom_formula';break;
		case 'effectiveness':$map->data->sections[0]->effectiveness='1';break;
		case 'unisolated':$section=$clone($map->data->sections[0]);$section->section_key='other';$map->data->sections[]=$section;$map->data->refrigerant_sections_are_isolated=false;break;
		case 'balance':$model->data->energy_balance->external_heat_loss_kW='1';break;
		case 'iterations':$model->data->numerics->max_outer_iterations=81;break;
		case 'tolerance':$model->data->numerics->heat_tolerance_kW='1e-20';break;
		case 'unknown_field':$request->eligible=true;break;
		case 'missing_flow':unset($request->evaporator->volume_flow_m3_h);break;
		case 'air_on_water':$b=thermalBundle('water');$request=thermalRequest('water');$request->temperature_offset_probe=(object)[];break;
		case 'mode':$request->operating_mode='reversible_heating';break;
	}
	$reject(fn()=>$evaluate($b,$request),'Invalid contract '.$case);
}
foreach(['cp','density','flow','state','fan','fan_mismatch','medium','inlet','flow_range','search','precision','envelope','no_envelope','basis','real_machine','no_model','no_map','overflow','certificate_overflow','inner_limit','outer_limit','ambiguous','multiple_roots','no_bracket','multiple_sections','technical'] as $case) {
	$b=thermalBundle();$request=thermalRequest();$map=$find($b,':evaporator-map');$model=$find($b,':thermal-model');$curve=$find($b,':compressor');$machine=$find($b,':machine:1');
	switch($case) {
		case 'cp':$map->data->media[0]->properties->specific_heat_kJ_kg_K=null;break;
		case 'density':$map->data->media[0]->properties->density_kg_m3=null;break;
		case 'flow':$request->evaporator->volume_flow_m3_h=null;break;
		case 'state':$request->evaporator->fluid_state=null;break;
		case 'fan':unset($request->condenser->fan);break;
		case 'fan_mismatch':$request->condenser->fan->speed_rpm='1400';break;
		case 'medium':$request->condenser->medium_key='other';break;
		case 'inlet':$request->evaporator->inlet_degC='100';break;
		case 'flow_range':$request->evaporator->volume_flow_m3_h='10001';break;
		case 'search':$model->data->search_bounds->Te->max='9.1';break;
		case 'precision':$model->data->search_bounds->Te->max='9.00000000000000000001';break;
		case 'envelope':$curve->data->operating_envelope->vertices[1]->Te='4';$curve->data->operating_envelope->vertices[2]->Te='4';break;
		case 'no_envelope':$curve->data->operating_envelope=null;break;
		case 'basis':$map->data->temperature_basis='dew';break;
		case 'real_machine':unset($machine->import_context);break;
		case 'no_model':unset($machine->data->thermal_model_ref);break;
		case 'no_map':$find($b,':evaporator')->data->specification->thermal_map_ref=null;break;
		case 'overflow':$map->data->media[0]->properties->density_kg_m3='1e308';break;
		case 'certificate_overflow':$curve->data->quantities->power_input->coefficients_C0_to_C9[9]='1e308';break;
		case 'inner_limit':$model->data->numerics->max_inner_iterations=1;break;
		case 'outer_limit':$model->data->numerics->max_outer_iterations=1;break;
		case 'ambiguous':$curve->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_merge(['20000','-2000'],array_fill(0,8,'0'));break;
		case 'multiple_roots':$curve->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_pad(['24000','-7000','0','1000'],10,'0');break;
		case 'no_bracket':$request->condenser->inlet_degC='0';break;
		case 'multiple_sections':$second=$clone($map->data->sections[0]);$second->section_key='r2';$map->data->sections[]=$second;break;
	}
	$r=$evaluate($b,$request,$case==='technical'?'released_real_thermal_model_not_available':null);
	$check($r['solution']===null && $r['eligible']===false && $r['reasons'],'Invalid case produced thermal solution: '.$case);
	$check(json_encode($r,JSON_THROW_ON_ERROR)!=='','Nonfinite result: '.$case);
	if(in_array($case,['fan_mismatch','medium','inlet','flow_range','search','precision','envelope'],true))$check($r['status']==='unsuitable','Proven violation did not reject: '.$case);
	if(in_array($case,['ambiguous','multiple_roots'],true))$check($r['convergence']['status']==='ambiguous_or_uncertified' && $r['convergence']['evaluations']===0,'Ambiguous roots were searched');
	if($case==='no_bracket')$check($r['status']==='unsuitable' && in_array('no_coupled_solution_in_validated_search_domain',$r['reasons'],true),'Proven absent root not rejected');
	if($case==='inner_limit')$check($r['convergence']['status']==='inner_iteration_limit','D2-070 inner nonconvergence hidden');
	if($case==='outer_limit')$check($r['convergence']['status']==='outer_iteration_limit','D2-070 outer nonconvergence hidden');
}
$b=thermalBundle('water');$machine=$find($b,':machine:1');
$machine->data->hydraulic_loops[0]->edges=[['return_water','tank.in'],['tank.out','pump.in'],['pump.out','evap.water_in'],['evap.water_out','cond.r1.water_in'],['cond.r1.water_out','supply_water']];
array_pop($machine->data->hydraulic_loops);
$r=$evaluate($b,thermalRequest('water'));
$check($r['solution']===null && $r['convergence']['evaluations']===0 && in_array('shared_secondary_liquid_loop_not_supported',$r['reasons'],true),'Evaporator and condenser on one secondary loop treated independently');
// Two independently known roots of Q=24-7Te+Te^2 and Q_hx=20-2Te.
foreach([1.0,4.0] as $te) {
	$tc=35.0+(22.0-1.99*$te)/(241.0/300.0);
	$check(abs((24.0-7.0*$te+$te*$te)-2.0*(10.0-$te))<1e-12 && $tc>=40.0 && $tc<=60.0,'Independent ambiguous-root reference');
}
foreach([2,4] as $circuits) {
	$b=machineStageBundle($circuits);[$machine,$resolve,$compressor]=thermalCallbacks($b);
	$r=(new Items_Service_ThermalCircuit())->evaluate($machine,thermalRequest(),$resolve,$compressor);
	$check($r['solution']===null && in_array('shared_exchanger_air_or_liquid_coupling_not_supported',$r['reasons'],true),'Shared 4/2 or 8/4 treated as independent');
}
$b=thermalBundle('air','fixture:thermal',2);$request=thermalRequest();$request->evaporator->volume_flow_m3_h='10.8';$request->condenser->volume_flow_m3_h='17832';
$find($b,':condenser-map')->data->sections[0]->limits->volume_flow->max='20000';
$r=$evaluate($b,$request);
$check($r['convergence']['status']==='converged' && $near($r['solution']['point']->Te_degC,5.0) && $near($r['solution']['point']->Tc_degC,50.0),'Two distinct active compressor instances not reused');
$check($near($r['solution']['energy_balance']['compressor_cooling_kW'],30.0) && $near($r['solution']['energy_balance']['compressor_power_kW'],7.15),'Independent two-compressor heat balance');
// Finite cancellation can otherwise fake an exact zero residual thousands of kelvins from the root.
$b=thermalBundle();$curve=$find($b,':compressor');$curve->data->quantities->cooling_capacity->unit='kW';$curve->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_pad(['1e20'],10,'0');
$curve->data->operating_envelope->vertices=[(object)['Te'=>'0','Tc'=>'2000000'],(object)['Te'=>'1000000','Tc'=>'2000000'],(object)['Te'=>'1000000','Tc'=>'4000000'],(object)['Te'=>'0','Tc'=>'4000000']];
$model=$find($b,':thermal-model');$model->data->search_bounds=(object)['Te'=>thermalRangeFixture('0','1000000'),'Tc'=>thermalRangeFixture('2000000','4000000')];
foreach(['evaporator','condenser'] as $side) {
	$map=$find($b,':'.$side.'-map');$map->data->sections[0]->limits->saturation_temperature=$model->data->search_bounds->{$side==='evaporator'?'Te':'Tc'};
	$map->data->sections[0]->limits->secondary_inlet=thermalRangeFixture('0','2e20');$map->data->sections[0]->limits->secondary_outlet=thermalRangeFixture('0','2e20');
	$map->data->sections[0]->limits->volume_flow=thermalRangeFixture('0.1','1e20','m3/h');$map->data->media[0]->properties->temperature_range=thermalRangeFixture('0','2e20');
}
$request=thermalRequest();$request->evaporator->inlet_degC='100000000000000500000';$request->evaporator->volume_flow_m3_h='1.8';$request->condenser->inlet_degC='0';$request->condenser->volume_flow_m3_h='2.4e17';
$r=$evaluate($b,$request);
$check($r['solution']===null && in_array('thermal_arithmetic_precision_unconfirmed',$r['reasons'],true),'Finite cancellation falsely converged');
$check($r['convergence']['evaluations']>0 && $r['uniqueness']['certified'],'Cancellation reference did not reach precision guard');
echo "PASS: $count thermal contract/coupling assertions (air/water analytic references, D2-069..072, certified uniqueness, bounded iteration, explicit fluids, units, limits, shared blockers and no machine release)\n";
