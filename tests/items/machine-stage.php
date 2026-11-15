<?php

require_once __DIR__.'/../../application/modules/items/services/TechnicalJson.php';
require_once __DIR__.'/../../application/modules/items/services/TechnicalSchema.php';
require_once __DIR__.'/../../application/modules/items/services/CompressorPolynomial.php';
require_once __DIR__.'/../../application/modules/items/services/MachineStage.php';
require_once __DIR__.'/fixtures/machine-stage.php';

$checks = 0;
$check = function(bool $ok, string $message) use (&$checks): void { $checks++; if(!$ok) throw new RuntimeException($message); };
$reject = function(callable $work, string $message) use ($check): void {
	try { $work(); } catch(InvalidArgumentException|RuntimeException $e) { $check(true, $message); return; }
	throw new RuntimeException('Expected rejection: '.$message);
};
$clone = fn($v) => unserialize(serialize($v));
$core = new Items_Service_MachineStage(); $polynomial = new Items_Service_CompressorPolynomial();
$evaluate = function(stdClass $bundle, stdClass $request, ?callable $transform = null) use ($core, $polynomial): array {
	$datasets = []; $profiles = [];
	foreach($bundle->datasets as $d) { $datasets[$d->dataset_key] = $d; if($d->dataset_type === 'machine_configuration') $machine = $d; }
	foreach($bundle->profiles as $p) $profiles[$p->profile_key] = $p;
	return $core->evaluate($machine, $request, function($component, $point, $context) use ($datasets, $profiles, $polynomial, $transform) {
		$profile = $profiles[$component->profile_key]; $record = (new Items_Service_TechnicalSchema())->dataset($datasets[$profile->dataset_ref->dataset_key]);
		$region = $record->data->operating_envelope === null ? ['status' => 'review_required', 'reason' => 'operating_envelope_missing'] : $polynomial->operatingRegion($record->data->operating_envelope, $point);
		$evaluation = ['status' => 'review_required', 'eligible' => false, 'inputs' => $point, 'curve' => ['refrigerant' => $record->data->refrigerant], 'conditions' => $record->data->conditions,
			'temperature_basis' => $record->data->temperature_basis, 'electrical' => $record->data->electrical, 'operating_region' => $region, 'violations' => [], 'reasons' => ['mathematical_reference_only'],
			'calculation' => $region['status'] === 'outside' ? null : $polynomial->evaluate($record, $point)];
		return $transform ? $transform($evaluation) : $evaluation;
	});
};
foreach([[1,1,10.0,2.05],[1,2,30.0,7.15],[2,2,60.0,14.3],[4,2,120.0,28.6]] as [$circles,$compressors,$q,$p]) {
	$bundle = machineStageBundle($circles, $compressors); $request = machineStageRequest($circles); $result = $evaluate($bundle, $request); $sum = $result['stage_compressor_sums'];
	$check(abs($sum['cooling_capacity']['value'] - $q) < 1e-12 && abs($sum['power_input']['value'] - $p) < 1e-12, 'Independent Q/P expectations');
	$check(abs($sum['compressor_cop']['value'] - $q / $p) < 1e-12, 'COP must be ratio of sums');
	$check($result['eligible'] === false && $result['status'] === 'review_required' && $result['useful_machine_cooling_capacity']['value'] === null, 'Machine release forbidden');
	$check($result['physical_component_count'] === $circles * ($compressors + 1) + 4, 'Shared physical components duplicated');
	$check(json_encode($result, JSON_THROW_ON_ERROR) === json_encode($evaluate($bundle, $request), JSON_THROW_ON_ERROR), 'Deterministic result');
	$check($sum['current']['value'] === null && $sum['discharge_temperature']['value'] === null, 'Unsafe electrical/temperature aggregate');
	$check(abs($sum['refrigerant_mass_flow']['value'] - $circles * ($compressors === 1 ? 100 : 300) / 3600.0) < 1e-12, 'Compatible mass flow normalization');
}
$bundle = machineStageBundle(); $request = machineStageRequest();
$request->circuit_points[1]->point = (object)['Te_degC' => '0', 'Tc_degC' => '40', 'temperature_unit' => 'degC'];
$result = $evaluate($bundle, $request); $sum = $result['stage_compressor_sums'];
$check(abs($sum['cooling_capacity']['value'] - 58.8) < 1e-12 && abs($sum['power_input']['value'] - 14.15) < 1e-12, 'Distinct circuit temperatures ignored');
$check($result['circuits']['r1']['compressor_sums']['cooling_capacity']['value'] === 30.0 && abs($result['circuits']['r2']['compressor_sums']['cooling_capacity']['value'] - 28.8) < 1e-12, 'Circuit results mixed');
$check($sum['refrigerant_mass_flow']['value'] === null && in_array('mass_flow_basis_differs', $sum['reasons'], true), 'Different mass bases were summed');
foreach([1 => [10.0, 2.05], 2 => [19.6, 4.05], 3 => [39.6, 9.15], 4 => [58.8, 14.15]] as $stage => [$q, $p]) {
	$r = $clone($request); $r->stage_key = 'stage'.$stage; $out = $evaluate($bundle, $r);
	$check(abs($out['stage_compressor_sums']['cooling_capacity']['value'] - $q) < 1e-12 && abs($out['stage_compressor_sums']['power_input']['value'] - $p) < 1e-12, 'Concrete stage selection');
	$check(count(array_filter($out['compressors'], fn($c) => $c['active'])) === $stage, 'Wrong active count');
	foreach($out['compressors'] as $c) if(!$c['active']) $check($c['evaluation'] === null, 'Inactive compressor evaluated');
	if($stage === 1) $check($out['circuits']['r2']['compressor_sums']['cooling_capacity']['value'] === null, 'Inactive circuit implies standby power');
}
foreach(['stage', 'mode', 'missing_point', 'duplicate_point', 'foreign_point', 'missing_tc', 'derived_temperature', 'inactive_context', 'machine_release'] as $case) {
	$r = $clone($request);
	switch($case) {
		case 'stage': $r->stage_key = 'missing'; break;
		case 'mode': $r->operating_mode = 'reversible_heating'; break;
		case 'missing_point': array_pop($r->circuit_points); break;
		case 'duplicate_point': $r->circuit_points[] = $r->circuit_points[0]; break;
		case 'foreign_point': $r->circuit_points[0]->circuit_key = 'r9'; break;
		case 'missing_tc': unset($r->circuit_points[0]->point->Tc_degC); break;
		case 'derived_temperature': $r->circuit_points[0]->point->air_degC = '35'; break;
		case 'inactive_context': $r->stage_key = 'stage1'; $r->compressor_contexts = (object)['r2.compressor1' => new stdClass()]; break;
		case 'machine_release': $r->eligible = true; break;
	}
	$reject(fn() => $evaluate($bundle, $r), $case);
}
foreach(['wrong_circuit', 'duplicate', 'unassigned', 'shared_section', 'refrigerant', 'no_coefficients', 'no_required_quantity'] as $case) {
	$b = $clone($bundle); foreach($b->datasets as $d) if($d->dataset_type === 'machine_configuration') $m = $d->data;
	switch($case) {
		case 'wrong_circuit': $m->circuits[0]->compressor_instances[0] = 'r2.compressor1'; break;
		case 'duplicate': $m->components[] = $m->components[0]; break;
		case 'unassigned': $m->stages[0]->active_compressors[] = 'missing'; break;
		case 'shared_section': $m->circuits[1]->evaporator_section->section_key = 'r1'; break;
		case 'refrigerant': $m->circuits[1]->refrigerant = 'different'; break;
		case 'no_coefficients': array_pop($b->datasets[1]->data->quantities->cooling_capacity->coefficients_C0_to_C9); break;
		case 'no_required_quantity': unset($b->datasets[1]->data->quantities->power_input); break;
	}
	$reject(fn() => $evaluate($b, $request), $case);
}
$outside = $clone($request); $outside->circuit_points[1]->point->Tc_degC = '61'; $out = $evaluate($bundle, $outside);
$check($out['status'] === 'unsuitable' && $out['stage_compressor_sums']['cooling_capacity']['value'] === null && $out['violations'], 'Outside limit not rejected or partial sum leaked');
foreach(['conditions', 'basis', 'missing_result', 'numeric_overflow', 'missing_mass', 'electrical'] as $case) {
	$out = $evaluate($bundle, machineStageRequest(), function($e) use ($case) {
		static $index = 0; $index++;
		if($index === 2) switch($case) {
			case 'conditions': $e['conditions'] = clone $e['conditions']; $e['conditions']->superheat_K = '8'; break;
			case 'basis': $e['temperature_basis'] = (object)['Te' => null, 'Tc' => null]; break;
			case 'missing_result': $e['calculation'] = null; break;
			case 'numeric_overflow': $e['calculation']['mathematical_status'] = 'invalid_result'; break;
			case 'missing_mass': $e['calculation']['quantities']['refrigerant_mass_flow'] = ['status' => 'not_available', 'value' => null, 'unit' => null]; break;
			case 'electrical': $e['electrical'] = (object)['voltage_V' => '230', 'phases' => 1, 'execution_code' => 'different']; break;
		}
		return $e;
	});
	if(in_array($case, ['missing_mass', 'electrical'], true)) $check($out['stage_compressor_sums']['cooling_capacity']['value'] === 60.0, 'Valid Q/P suppressed by optional quantity/execution');
	else $check($out['stage_compressor_sums']['cooling_capacity']['value'] === null, 'Incomplete/incompatible sum');
	if($case === 'missing_mass') $check($out['stage_compressor_sums']['refrigerant_mass_flow']['value'] === null, 'Missing mass flow estimated');
}
foreach(['parallel_operation_release_missing', 'oil_management_and_stage_limits_missing', 'circuit_control_evidence_missing'] as $reason) $check(in_array('r1:'.$reason, $result['reasons'], true), 'Missing engineering evidence');

$copyRequest=machineStageRequest();$copyResult=$evaluate($bundle,$copyRequest);$copyRequest->circuit_points[0]->point->Te_degC='7';
$check($copyResult['original_request']->circuit_points[0]->point->Te_degC==='5','Returned historical inputs share mutable request objects');
$kgSeconds=$evaluate($bundle,machineStageRequest(),function($e) {
	$mass=&$e['calculation']['quantities']['refrigerant_mass_flow'];$mass['value']/=3600.0;$mass['unit']='kg/s';return $e;
});
$check(abs($kgSeconds['stage_compressor_sums']['refrigerant_mass_flow']['value']-600.0/3600.0)<1e-12,'kg/s mass flow normalization');
$overflow=$evaluate($bundle,machineStageRequest(),function($e) {
	$e['calculation']['quantities']['cooling_capacity']['normalized']['value']=1e308;return $e;
});
$check($overflow['stage_compressor_sums']['cooling_capacity']['value']===null && $overflow['stage_compressor_sums']['compressor_cop']['value']===null,'Aggregate overflow escaped as a usable sum');
$check(json_encode($overflow,JSON_THROW_ON_ERROR)!=='','Aggregate contains a nonfinite JSON value');
$unknown=$evaluate($bundle,machineStageRequest(),function($e) {
	$e['conditions']=clone $e['conditions'];$e['conditions']->speed_rpm=null;return $e;
});
$check($unknown['stage_compressor_sums']['refrigerant_mass_flow']['value']===null && $unknown['stage_compressor_sums']['cooling_capacity']['value']===null,'Incomplete source condition basis');
echo "PASS: $checks machine-stage mathematical/structural assertions (1/1, 2/1, 4/2, 8/4, distinct curves/points/stages, sums, COP, shared components and blocked evidence)\n";
