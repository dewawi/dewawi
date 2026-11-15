<?php

require_once __DIR__.'/machine-stage.php';

function thermalRangeFixture(string $min, string $max, string $unit = 'degC'): stdClass
{
	return (object)['min' => $min, 'max' => $max, 'unit' => $unit];
}

function thermalBundle(string $cooling = 'air', string $prefix = 'fixture:thermal', int $compressors = 1): stdClass
{
	$b = machineStageBundle(1, $compressors, $prefix);
	foreach($b->datasets as $record) {
		if($record->dataset_type === 'machine_configuration') $machine = $record;
		if($record->dataset_type === 'component_spec' && $record->data->role === 'evaporator') $evap = $record;
		if($record->dataset_type === 'component_spec' && $record->data->role === 'air_condenser') $cond = $record;
	}
	if($cooling === 'water') {
		$machine->data->cooling_kind = 'water'; $machine->data->air_paths = [];
		$machine->data->components = array_values(array_filter($machine->data->components, fn($c) => $c->role !== 'fan'));
		$cond->data->role = 'water_condenser'; $cond->data->specification->exchanger_type = 'plate';
		$cond->data->ports = (object)['in' => 'refrigerant_in', 'out' => 'refrigerant_out', 'water_in' => 'liquid_in', 'water_out' => 'liquid_out'];
		foreach($machine->data->components as $component) if($component->instance_key === 'cond.r1') { $component->role = 'water_condenser'; $component->ports = $cond->data->ports; }
		foreach($b->profiles as $profile) if($profile->role === 'air_condenser') $profile->role = 'water_condenser';
		$machine->data->hydraulic_loops[] = (object)['loop_key' => 'source', 'fluid_dataset' => null, 'boundary_in' => 'source_in', 'boundary_out' => 'source_out', 'edges' => [['source_in', 'cond.r1.water_in'], ['cond.r1.water_out', 'source_out']], 'tank_kind' => null, 'external_pressure_definition' => 'external_to_machine', 'pressure_loss_dataset' => null];
	}
	foreach(['evaporator' => $evap, 'condenser' => $cond] as $side => $component) {
		$air = $component->data->role === 'air_condenser'; $role = $component->data->role;
		$map = technicalDraft($prefix.':'.$side.'-map', 'heat_exchanger_map', (object)[
			'contract_version' => 1, 'usage' => 'synthetic_test_only', 'component_key' => $component->data->component_key, 'model' => $component->data->model, 'role' => $role,
			'exchanger_type' => $component->data->specification->exchanger_type, 'refrigerant' => 'fixture-fluid', 'temperature_basis' => 'pure_saturation',
			'model_id' => 'synthetic_'.$role.'_effectiveness_v1', 'measurement_boundary' => 'secondary_stream_only_no_external_heat_v1', 'interpolation' => 'none_analytic',
			'variables' => (object)['saturation_temperature' => 'degC', 'secondary_inlet' => 'degC', 'secondary_outlet' => 'degC', 'volume_flow' => 'm3/h', 'heat_flow' => 'kW', 'pressure_drop' => 'kPa'], 'refrigerant_sections_are_isolated' => true,
			'media' => [(object)['medium_key' => $air ? 'fixture:dry-air' : 'fixture:liquid', 'phase' => $air ? 'dry_gas' : 'single_phase_liquid', 'composition' => 'Explicit synthetic constant-property medium; no real fluid claim',
				'properties' => (object)['model_id' => 'synthetic_constant_cp_density_v1', 'density_kg_m3' => $air ? '1' : '1000', 'specific_heat_kJ_kg_K' => $air ? '1' : '4', 'temperature_range' => thermalRangeFixture('-20', '90'), 'source_locator' => 'synthetic constant properties']]],
			'sections' => [(object)['section_key' => $side === 'evaporator' ? 'r1' : 'main', 'effectiveness' => '0.5', 'limits' => (object)[
				'saturation_temperature' => $side === 'evaporator' ? thermalRangeFixture('0', '9') : thermalRangeFixture('40', '60'), 'secondary_inlet' => thermalRangeFixture('0', '45'), 'secondary_outlet' => thermalRangeFixture('0', '80'), 'volume_flow' => thermalRangeFixture('0.1', '10000', 'm3/h')], 'pressure_drop_kPa' => null]],
			'fan_condition' => $air ? (object)['execution_code' => 'SYNTHETIC-FAN', 'speed_rpm' => '1500'] : null, 'restrictions' => ['synthetic_constant_effectiveness_only'], 'missing_evidence' => ['oem_performance_not_provided']]);
		$b->datasets[] = $map; $component->data->specification->thermal_map_ref = (object)['dataset_key' => $map->dataset_key, 'revision' => 1];
	}
	$model = technicalDraft($prefix.':thermal-model', 'thermal_balance_model', (object)['contract_version' => 1, 'usage' => 'synthetic_test_only', 'circuit_key' => 'r1',
		'energy_balance' => (object)['model_id' => 'synthetic_hermetic_no_external_losses_v1', 'boundary' => 'evaporator_plus_electrical_input_to_condenser_v1', 'motor_heat_to_refrigerant_fraction' => '1', 'external_heat_loss_kW' => '0', 'transient_storage_kW' => '0', 'other_heat_input_kW' => '0'],
		'search_bounds' => (object)['Te' => thermalRangeFixture('0', '9'), 'Tc' => thermalRangeFixture('40', '60')],
		'numerics' => (object)['heat_tolerance_kW' => '0.0000001', 'temperature_tolerance_K' => '0.00000001', 'max_outer_iterations' => 60, 'max_inner_iterations' => 60], 'missing_evidence' => ['real_compressor_energy_boundary_unverified']]);
	$b->datasets[] = $model; $machine->data->thermal_model_ref = (object)['dataset_key' => $model->dataset_key, 'revision' => 1];
	$b->template_kind = 'synthetic_thermal_test_v1'; $b->production_import_allowed = false; $b->provenance_note = 'Synthetic thermal acceptance fixture only';
	foreach($b->datasets as $record) $record->import_context = (object)['template_kind' => $b->template_kind, 'provenance_note' => $b->provenance_note, 'production_import_allowed' => false];
	return $b;
}

function thermalRequest(string $cooling = 'air'): stdClass
{
	$evap = (object)['medium_key' => 'fixture:liquid', 'inlet_degC' => '10', 'volume_flow_m3_h' => '3.6', 'fluid_state' => 'single_phase_liquid'];
	$cond = (object)['medium_key' => $cooling === 'air' ? 'fixture:dry-air' : 'fixture:liquid', 'inlet_degC' => '35', 'volume_flow_m3_h' => $cooling === 'air' ? '5784' : '1.446', 'fluid_state' => $cooling === 'air' ? 'dry_gas' : 'single_phase_liquid'];
	if($cooling === 'air') $cond->fan = (object)['execution_code' => 'SYNTHETIC-FAN', 'speed_rpm' => '1500'];
	return (object)['stage_key' => 'full', 'operating_mode' => 'cooling', 'evaporator' => $evap, 'condenser' => $cond];
}

function thermalCallbacks(stdClass $bundle): array
{
	$schema = new Items_Service_TechnicalSchema(); $records = [];
	foreach($bundle->datasets as $r) { $r = $schema->dataset($r); $records['technicaldataset:'.$r->dataset_key.'@'.$r->revision] = $r; if($r->dataset_type === 'machine_configuration') $machine = $r; }
	foreach($bundle->profiles as $p) { $p = $schema->profile($p); $records['itemtechnicalprofile:'.$p->profile_key.'@'.$p->revision] = $p; }
	$resolve = function($table, $key, $revision) use ($records): stdClass { return $records[$table.':'.$key.'@'.$revision] ?? throw new InvalidArgumentException('Missing fixture reference'); };
	$compressor = function($component, $point, $context) use ($resolve) {
		$profile = $resolve('itemtechnicalprofile', $component->profile_key, $component->profile_revision); $ref = $profile->dataset_ref; $r = $resolve('technicaldataset', $ref->dataset_key, $ref->revision);
		$core = new Items_Service_CompressorPolynomial(); $region = $core->operatingRegion($r->data->operating_envelope, $point);
		return ['status' => 'review_required', 'eligible' => false, 'inputs' => $point, 'curve' => ['refrigerant' => $r->data->refrigerant], 'conditions' => $r->data->conditions, 'temperature_basis' => $r->data->temperature_basis, 'electrical' => $r->data->electrical,
			'operating_region' => $region, 'violations' => [], 'reasons' => ['mathematical_reference_only'], 'calculation' => $region['status'] === 'inside' ? $core->evaluate($r, $point) : null];
	};
	return [$machine, $resolve, $compressor];
}
