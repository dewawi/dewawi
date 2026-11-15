<?php

require_once __DIR__.'/technical.php';

function machineStageBundle(int $circuits = 2, int $compressorsPerCircuit = 2, string $prefix = 'fixture:d4'): stdClass
{
	$bundle = technicalBundle($circuits);
	$curve = $bundle->datasets[1];
	foreach(['cooling_capacity' => ['10000', '100', '-10'], 'power_input' => ['2000', '10'], 'current' => ['5'], 'refrigerant_mass_flow' => ['100'], 'discharge_temperature' => ['80']] as $q => $coefficients) $curve->data->quantities->$q->coefficients_C0_to_C9 = array_pad($coefficients, 10, '0');
	$second = unserialize(serialize($curve));
	$second->dataset_key = 'fixture:compressor-b'; $second->data->model = 'SYNTHETIC-COMPRESSOR-B';
	foreach(['cooling_capacity' => ['20000', '200', '-20'], 'power_input' => ['5000', '20'], 'current' => ['9'], 'refrigerant_mass_flow' => ['200'], 'discharge_temperature' => ['90']] as $q => $coefficients) $second->data->quantities->$q->coefficients_C0_to_C9 = array_pad($coefficients, 10, '0');
	$bundle->datasets[] = $second;
	$bundle->profiles[] = technicalProfile('compressor-b', 'compressor', $second);
	foreach($bundle->datasets as $dataset) if($dataset->dataset_type === 'machine_configuration') $machine = $dataset;
	$data = $machine->data;
	$removed = [];
	foreach($data->components as $component) if($component->role === 'compressor' && str_ends_with($component->instance_key, 'compressor2')) {
		if($compressorsPerCircuit === 1) $removed[] = $component->instance_key;
		else { $component->component_key = 'fixture:compressor-b'; $component->profile_key = 'fixture:profile:compressor-b'; }
	}
	$data->components = array_values(array_filter($data->components, fn($c) => !in_array($c->instance_key, $removed, true)));
	foreach($data->circuits as $circuit) {
		$circuit->compressor_instances = array_values(array_diff($circuit->compressor_instances, $removed));
		foreach($circuit->refrigerant_groups as $group) $group->members = array_values(array_filter($group->members, function($port) use ($removed) {
			foreach($removed as $key) if(str_starts_with($port, $key.'.')) return false;
			return true;
		}));
	}
	$all = array_values(array_filter($data->stages[0]->active_compressors, fn($key) => !in_array($key, $removed, true)));
	usort($all, fn($a, $b) => strcmp(substr($a, strpos($a, '.')), substr($b, strpos($b, '.'))) ?: strcmp($a, $b));
	$data->stages = [];
	foreach($all as $index => $key) $data->stages[] = (object)['stage_key' => 'stage'.($index + 1), 'active_compressors' => array_slice($all, 0, $index + 1), 'controls_ref' => null, 'release_status' => 'draft'];
	$data->stages[] = (object)['stage_key' => 'full', 'active_compressors' => $all, 'controls_ref' => null, 'release_status' => 'draft'];
	// Dataset/profile identities are unique per fixture; article mapping symbols stay explicit.
	$rename = function($value) use (&$rename, $prefix): void {
		if(is_object($value) || is_array($value)) foreach($value as $key => &$child) {
			if(in_array($key, ['dataset_key', 'profile_key'], true) && is_string($child)) $child = $prefix.substr($child, strlen('fixture'));
			else $rename($child);
		}
	};
	$rename($bundle);
	return $bundle;
}

function machineStageRequest(int $circuits = 2, string $stage = 'full'): stdClass
{
	$points = [];
	for($i = 1; $i <= $circuits; $i++) $points[] = (object)['circuit_key' => 'r'.$i, 'point' => (object)['Te_degC' => '5', 'Tc_degC' => '50', 'temperature_unit' => 'degC']];
	return (object)['stage_key' => $stage, 'operating_mode' => 'cooling', 'circuit_points' => $points];
}
