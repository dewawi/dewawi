<?php

function technicalSource(): stdClass
{
	return (object)['filename' => 'synthetic.csv', 'sha256' => hash('sha256', 'synthetic test data'), 'locator' => 'fixture:v1'];
}
function technicalDraft(string $key, string $type, stdClass $data): stdClass
{
	return (object)['dataset_key' => $key, 'revision' => 1, 'dataset_type' => $type, 'schema_version' => 1, 'requested_status' => 'draft', 'manufacturer_name' => 'Synthetic test manufacturer', 'manufacturer_revision' => 'fixture-v1', 'data_owner' => 'Synthetic fixtures', 'source' => technicalSource(), 'data' => $data, 'blockers' => []];
}
function technicalFamily(): stdClass
{
	return technicalDraft('fixture:family', 'machine_family', (object)['family_key' => 'fixture:family', 'title' => 'Synthetic fixture', 'chassis' => [(object)['chassis_key' => 'fixture:chassis', 'dimensions' => (object)['length_mm' => '1000', 'width_mm' => '800', 'height_mm' => '1200'], 'installation_kinds' => ['outdoor'], 'structural_limits_dataset' => null]]]);
}
function technicalPolynomial(): stdClass
{
	$quantities = new stdClass();
	foreach(['cooling_capacity' => 'W', 'power_input' => 'W', 'current' => 'A', 'refrigerant_mass_flow' => 'kg/h', 'discharge_temperature' => 'degC'] as $q => $unit) $quantities->$q = (object)['unit' => $unit, 'coefficients_C0_to_C9' => ['123.456789012345678901234567890123456789', '2', '-3', '0', '0', '0', '0', '0', '0', '0'], 'source_range' => 'synthetic:C0:C9'];
	return technicalDraft('fixture:compressor', 'compressor_polynomial', (object)['model' => 'SYNTHETIC-COMPRESSOR', 'refrigerant' => 'fixture-fluid', 'equation_id' => 'te_tc_cubic_c0_c9_v1', 'temperature_unit' => 'degC', 'temperature_basis' => (object)['Te' => 'pure_saturation', 'Tc' => 'pure_saturation'], 'conditions' => (object)['speed_rpm' => '2900', 'frequency_Hz' => '50', 'superheat_K' => '10', 'return_gas_temperature_degC' => null, 'subcooling_K' => '0', 'suction_condition_mode' => 'superheat'], 'electrical' => (object)['voltage_V' => '400', 'phases' => 3, 'execution_code' => 'SYNTHETIC-400-3'], 'operating_envelope' => (object)['kind' => 'te_tc_polygon', 'unit' => 'degC', 'vertices' => [(object)['Te' => '-10', 'Tc' => '30'], (object)['Te' => '10', 'Tc' => '30'], (object)['Te' => '10', 'Tc' => '60'], (object)['Te' => '-10', 'Tc' => '60']], 'source_locator' => 'synthetic test region'], 'quantities' => $quantities]);
}
function technicalComponent(string $key, string $role, stdClass $ports, ?string $refrigerant, stdClass $spec): stdClass
{
	return technicalDraft('fixture:'.$key, 'component_spec', (object)['component_key' => 'fixture:'.$key, 'role' => $role, 'model' => 'SYNTHETIC-'.strtoupper($key), 'refrigerant' => $refrigerant, 'electrical' => null, 'ports' => $ports, 'specification' => $spec]);
}
function technicalProfile(string $key, string $role, stdClass $dataset): stdClass
{
	return (object)['profile_key' => 'fixture:profile:'.$key, 'revision' => 1, 'item_ref' => 'fixture:'.$key, 'dataset_ref' => (object)['dataset_key' => $dataset->dataset_key, 'revision' => $dataset->revision], 'role' => $role, 'binding' => (object)['model' => $dataset->data->model, 'manufacturersku' => 'SYNTHETIC-'.$key, 'electrical' => $dataset->data->electrical, 'included_option_refs' => [], 'extra_option_refs' => [], 'source_locator' => 'synthetic crosswalk', 'scope_key' => 'fixture:'.$key]];
}
function technicalBundle(int $circuitCount = 2): stdClass
{
	$family = technicalFamily(); $polynomial = technicalPolynomial();
	$datasets = [$family, $polynomial];
	$profiles = [technicalProfile('compressor', 'compressor', $polynomial)];
	$components = $circuits = $shared = $allCompressors = [];
	$evapPorts = (object)['water_in' => 'liquid_in', 'water_out' => 'liquid_out'];
	$sections = [];
	for($i = 1; $i <= $circuitCount; $i++) {
		$r = 'r'.$i; $sections[] = $r;
		$evapPorts->{$r.'_in'} = 'refrigerant_in'; $evapPorts->{$r.'_out'} = 'refrigerant_out';
		$cc = [];
		for($j = 1; $j <= 2; $j++) {
			$key = $r.'.compressor'.$j; $cc[] = $key; $allCompressors[] = $key;
			$components[] = (object)['instance_key' => $key, 'role' => 'compressor', 'component_key' => 'fixture:compressor', 'profile_key' => 'fixture:profile:compressor', 'profile_revision' => 1, 'ports' => (object)['suction' => 'refrigerant_in', 'discharge' => 'refrigerant_out']];
		}
		$components[] = (object)['instance_key' => 'cond.'.$r, 'role' => 'air_condenser', 'component_key' => 'fixture:condenser', 'profile_key' => 'fixture:profile:condenser', 'profile_revision' => 1, 'ports' => (object)['in' => 'refrigerant_in', 'out' => 'refrigerant_out', 'air_in' => 'air_in', 'air_out' => 'air_out']];
		$circuits[] = (object)['circuit_key' => $r, 'refrigerant' => 'fixture-fluid', 'compressor_instances' => $cc, 'topology_kind' => 'parallel_compressors_single_evaporator_single_condenser_v1', 'refrigerant_groups' => [(object)['group_key' => 'suction', 'members' => array_merge(['evap.'.$r.'_out'], array_map(fn($c) => $c.'.suction', $cc))], (object)['group_key' => 'discharge', 'members' => array_merge(array_map(fn($c) => $c.'.discharge', $cc), ['cond.'.$r.'.in'])], (object)['group_key' => 'liquid', 'members' => ['cond.'.$r.'.out', 'exp.'.$r.'.in']], (object)['group_key' => 'expanded', 'members' => ['exp.'.$r.'.out', 'evap.'.$r.'_in']]], 'expansion_device' => (object)['device_key' => 'exp.'.$r, 'kind' => 'unconfirmed', 'component_key' => null, 'profile_key' => null, 'ports' => (object)['in' => 'refrigerant_in', 'out' => 'refrigerant_out']], 'evaporator_section' => (object)['instance_key' => 'evap', 'section_key' => $r], 'condenser_instance' => 'cond.'.$r, 'control_dataset' => null];
	}
	$hx = (object)['exchanger_type' => 'plate', 'sections' => $sections, 'thermal_map_ref' => null, 'pressure_loss_ref' => null, 'flow_min_m3_h' => null, 'flow_max_m3_h' => null, 'pressure_max_kPa' => null];
	$evap = technicalComponent('evaporator', 'evaporator', $evapPorts, 'fixture-fluid', $hx);
	$cond = technicalComponent('condenser', 'air_condenser', $components[2]->ports, 'fixture-fluid', (object)array_replace((array)$hx, ['exchanger_type' => 'finned_coil', 'sections' => ['main']]));
	$pump = technicalComponent('pump', 'pump', (object)['in' => 'liquid_in', 'out' => 'liquid_out'], null, (object)['curve_ref' => null, 'curve_definition' => 'gross', 'speed_rpm' => '2900', 'frequency_Hz' => '50', 'fluid_ref' => null, 'efficiency_map_ref' => null, 'power_input_ref' => null]);
	$tank = technicalComponent('tank', 'tank', (object)['in' => 'liquid_in', 'out' => 'liquid_out'], null, (object)['tank_kind' => 'pressurized', 'nominal_volume_L' => '100', 'usable_volume_L' => '80', 'pressure_max_kPa' => '300', 'material' => 'synthetic material']);
	$fan = technicalComponent('fan', 'fan', (object)['in' => 'air_in', 'out' => 'air_out'], null, (object)['air_curve_ref' => null, 'power_input_ref' => null, 'noise_dataset_ref' => null, 'control_modes' => ['fixed']]);
	foreach([$evap, $cond, $pump, $tank, $fan] as $component) {
		$datasets[] = $component;
		$key = substr($component->dataset_key, 8);
		$profiles[] = technicalProfile($key, $component->data->role, $component);
		if($key !== 'condenser') $components[] = (object)['instance_key' => $key === 'evaporator' ? 'evap' : $key, 'role' => $component->data->role, 'component_key' => 'fixture:'.$key, 'profile_key' => 'fixture:profile:'.$key, 'profile_revision' => 1, 'ports' => $component->data->ports];
	}
	if($circuitCount > 1) {
		$shared[] = (object)['instance_key' => 'evap', 'shared_by_circuits' => $sections, 'refrigerant_sections_are_isolated' => true, 'hydraulic_loop_key' => 'load'];
		$shared[] = (object)['instance_key' => 'fan', 'shared_by_circuits' => $sections, 'air_path_key' => 'ambient'];
	}
	$shared[] = (object)['instance_key' => 'pump', 'hydraulic_loop_key' => 'load']; $shared[] = (object)['instance_key' => 'tank', 'hydraulic_loop_key' => 'load'];
	$machine = technicalDraft('fixture:machine:'.$circuitCount, 'machine_configuration', (object)['family_ref' => (object)['dataset_key' => $family->dataset_key, 'revision' => 1], 'chassis_key' => 'fixture:chassis', 'machine_item_ref' => 'fixture:machine', 'base_item_ref' => 'fixture:base', 'refrigerant' => 'fixture-fluid', 'circuit_count' => $circuitCount, 'installation_kind' => 'outdoor', 'cooling_kind' => 'air', 'components' => $components, 'circuits' => $circuits, 'shared_components' => $shared, 'hydraulic_loops' => [(object)['loop_key' => 'load', 'fluid_dataset' => null, 'boundary_in' => 'return_water', 'boundary_out' => 'supply_water', 'edges' => [['return_water', 'tank.in'], ['tank.out', 'pump.in'], ['pump.out', 'evap.water_in'], ['evap.water_out', 'supply_water']], 'tank_kind' => 'pressurized', 'external_pressure_definition' => 'external_to_machine', 'pressure_loss_dataset' => null]], 'air_paths' => [(object)['path_key' => 'ambient', 'topology_kind' => 'parallel_coils_common_fan', 'ambient_in' => 'outside_air', 'coil_instances' => array_map(fn($r) => 'cond.'.$r, $sections), 'fan_instance' => 'fan', 'flow_distribution_dataset' => null]], 'stages' => [(object)['stage_key' => 'full', 'active_compressors' => $allCompressors, 'controls_ref' => null, 'release_status' => 'draft']], 'approved_stage_keys' => [], 'mode_topologies' => [(object)['mode' => 'cooling', 'release_status' => 'draft', 'circuit_keys' => $sections, 'load_loop_key' => 'load', 'topology_ref' => 'self:cooling'], (object)['mode' => 'reversible_heating', 'release_status' => 'unsupported', 'topology_ref' => null]], 'selected_equipment' => [], 'variant_signature' => (object)['included_option_refs' => [], 'extra_option_refs' => []], 'approximation' => (object)['enabled' => false, 'model_id' => 'fixed_temperature_offsets_v1', 'condensing_air_offset_K' => '15', 'evaporating_supply_offset_K' => '5', 'water_delta_T_K' => null, 'scope' => 'synthetic test only', 'source' => 'synthetic assumption', 'release_status' => 'draft']]);
	$datasets[] = $machine;
	$point = (object)['reference_key' => 'fixture:point', 'dataset_ref' => (object)['dataset_key' => $polynomial->dataset_key, 'revision' => 1], 'reference_kind' => 'mathematical_polynomial', 'inputs' => (object)['Te_degC' => '5', 'Tc_degC' => '50'], 'quantity' => 'cooling_capacity', 'expected_value' => '1', 'unit' => 'W', 'absolute_tolerance' => '0.000001', 'relative_tolerance' => null, 'tolerance_origin' => 'synthetic test tolerance', 'source' => technicalSource(), 'revision' => 1, 'engineering_approval' => null];
	$datasets[] = technicalDraft('fixture:references', 'reference_points', (object)['records' => [$point]]);
	return (object)['format' => 'dewawi-technical-import', 'format_version' => 1, 'datasets' => $datasets, 'profiles' => $profiles];
}
