<?php

class Items_Service_MachineStage
{
	public const VERSION = 'machine_stage_compressor_sums_v1';

	// The callback supplies D3 results from an already authorized, pinned dependency graph.
	public function evaluate(stdClass $record, stdClass $request, callable $compressor): array
	{
		$schema = new Items_Service_TechnicalSchema();
		$record = $schema->dataset($record);
		if($record->dataset_type !== 'machine_configuration') throw new InvalidArgumentException('A machine configuration is required');
		$input = $schema->fields($request, ['stage_key' => 'key', 'operating_mode' => ['cooling'], 'circuit_points' => 'list:object'], ['compressor_contexts' => 'object']);
		$data = $record->data;
		$stages = array_values(array_filter($data->stages, fn($s) => $s->stage_key === $input->stage_key));
		$modes = array_values(array_filter($data->mode_topologies, fn($m) => $m->mode === $input->operating_mode && $m->release_status !== 'unsupported'));
		if(count($stages) !== 1 || count($modes) !== 1) throw new InvalidArgumentException('Machine stage or operating mode unavailable');
		$stage = $stages[0]; $mode = $modes[0]; $points = [];
		foreach($input->circuit_points as $entry) {
			$entry = $schema->fields($entry, ['circuit_key' => 'key', 'point' => 'object']);
			if(isset($points[$entry->circuit_key])) throw new InvalidArgumentException('Duplicate circuit operating point');
			$points[$entry->circuit_key] = (new Items_Service_CompressorPolynomial())->point($entry->point);
		}
		$expected = array_map(fn($c) => $c->circuit_key, $data->circuits);
		$actual = array_keys($points); sort($actual); sort($expected);
		if($expected !== $actual) throw new InvalidArgumentException('Explicit operating points required for every circuit');
		$contexts = (array)($input->compressor_contexts ?? new stdClass());
		if(array_diff(array_keys($contexts), $stage->active_compressors)) throw new InvalidArgumentException('Operating context requires an active compressor instance');
		foreach($contexts as $context) if(!$context instanceof stdClass) throw new InvalidArgumentException('Invalid compressor operating context');
		$inventory = []; $owners = [];
		foreach($data->circuits as $circuit) foreach($circuit->compressor_instances as $key) $owners[$key] = $circuit->circuit_key;
		$instances = []; $circuits = []; $reasons = ['machine_physics_not_released', 'thermal_coupling_missing', 'heat_exchanger_performance_missing', 'expansion_system_evidence_missing', 'auxiliary_power_unknown'];
		if($stage->release_status !== 'approved' || $stage->controls_ref === null) $reasons[] = 'stage_control_release_missing';
		if($mode->release_status !== 'approved') $reasons[] = 'operating_mode_release_missing';
		foreach($data->components as $component) {
			$inventory[$component->instance_key] = $component;
			if($component->role !== 'compressor') continue;
			$key = $component->instance_key; $circuitKey = $owners[$key];
			$active = in_array($key, $stage->active_compressors, true);
			$evaluation = $active ? $compressor($component, $points[$circuitKey], $contexts[$key] ?? null) : null;
			$instances[$key] = ['instance_key' => $key, 'circuit_key' => $circuitKey, 'active' => $active, 'profile_key' => $component->profile_key, 'profile_revision' => $component->profile_revision, 'evaluation' => $evaluation];
			if($active) foreach($evaluation['reasons'] as $reason) $reasons[] = $key.':'.$reason;
		}
		$violations = [];
		foreach($data->circuits as $circuit) {
			$active = array_values(array_filter($circuit->compressor_instances, fn($key) => $instances[$key]['active']));
			$evaluations = array_map(fn($key) => $instances[$key]['evaluation'], $active);
			$missing = ['heat_exchanger_performance_missing', 'expansion_system_evidence_missing'];
			if(count($circuit->compressor_instances) > 1) $missing = array_merge($missing, ['parallel_operation_release_missing', 'oil_management_and_stage_limits_missing', 'common_pressure_evidence_missing']);
			if($circuit->control_dataset === null) $missing[] = 'circuit_control_evidence_missing';
			foreach($missing as $reason) $reasons[] = $circuit->circuit_key.':'.$reason;
			foreach($active as $key) {
				$evaluation = $instances[$key]['evaluation'];
				if(($evaluation['operating_region']['status'] ?? null) === 'outside') $violations[] = $key.':outside_operating_envelope';
				foreach($evaluation['violations'] ?? [] as $reason) if($reason !== 'operating_context_required') $violations[] = $key.':'.$reason;
			}
			$circuits[$circuit->circuit_key] = ['circuit_key' => $circuit->circuit_key, 'refrigerant' => $circuit->refrigerant, 'point' => $points[$circuit->circuit_key],
				'compressor_instances' => $circuit->compressor_instances, 'active_compressors' => $active, 'evaporator_section' => $circuit->evaporator_section,
				'condenser_instance' => $circuit->condenser_instance, 'missing_evidence' => $missing, 'compressor_sums' => $this->aggregate($evaluations)];
		}
		$active = array_values(array_filter($instances, fn($instance) => $instance['active']));
		$sums = $this->aggregate(array_column($active, 'evaluation'));
		foreach($circuits as $key => $circuit) foreach($circuit['compressor_sums']['reasons'] as $reason) $reasons[] = $key.':'.$reason;
		return ['result_version' => 1, 'calculation_version' => self::VERSION, 'compressor_calculation_version' => Items_Service_CompressorPolynomial::VERSION,
			'scope' => 'selected_stage_compressor_contributions_only', 'status' => $violations ? 'unsuitable' : 'review_required', 'eligible' => false,
			'original_request' => json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR), 'operating_mode' => $input->operating_mode, 'stage_key' => $stage->stage_key, 'stage_release_status' => $stage->release_status,
			'components' => $inventory, 'physical_component_count' => count($inventory), 'shared_components' => $data->shared_components,
			'compressors' => $instances, 'circuits' => $circuits, 'stage_compressor_sums' => $sums,
			'useful_machine_cooling_capacity' => ['status' => 'not_available', 'value' => null, 'unit' => 'kW'],
			'auxiliary_power' => ['status' => 'not_available', 'value' => null, 'unit' => 'kW'],
			'assumptions' => ['explicit_Te_Tc_per_circuit', 'source_curve_conditions_only_no_scaling', 'inactive_compressors_excluded_no_standby_estimate', 'no_thermal_coupling', 'no_machine_efficiency_or_condenser_rating'],
			'violations' => $violations, 'reasons' => array_values(array_unique(array_merge($reasons, $sums['reasons'])))];
	}

	private function aggregate(array $evaluations): array
	{
		$result = ['scope' => 'mathematical_compressor_sum_only', 'active_compressor_count' => count($evaluations), 'reasons' => []];
		foreach(['cooling_capacity' => 'kW', 'power_input' => 'kW', 'compressor_cop' => '1', 'refrigerant_mass_flow' => 'kg/s'] as $quantity => $unit) $result[$quantity] = ['status' => 'not_available', 'value' => null, 'unit' => $unit];
		foreach(['current', 'discharge_temperature'] as $quantity) $result[$quantity] = ['status' => 'not_aggregated', 'value' => null, 'reason' => 'individual_compressor_results_only'];
		if(!$evaluations) { $result['reasons'][] = 'no_active_compressors'; return $result; }
		$basis = []; $massBasis = []; $complete = true;
		foreach($evaluations as $evaluation) {
			if(($evaluation['calculation']['mathematical_status'] ?? null) !== 'calculated') $complete = false;
			$conditions = $evaluation['conditions'] ?? null; $temperatures = $evaluation['temperature_basis'] ?? null;
			if($conditions === null || $temperatures === null || in_array(null, (array)$temperatures, true) || ($conditions->subcooling_K ?? null) === null || ($conditions->frequency_Hz ?? null) === null || ($conditions->speed_rpm ?? null) === null) $complete = false;
			$signature = [$evaluation['curve']['refrigerant'] ?? null, $temperatures, $conditions];
			$basis[] = Items_Service_TechnicalJson::encode($signature);
			$massBasis[] = Items_Service_TechnicalJson::encode([$signature, $evaluation['inputs'] ?? null]);
		}
		if(!$complete || count(array_unique($basis)) !== 1) {
			$result['reasons'][] = $complete ? 'incompatible_curve_conditions' : 'incomplete_compressor_results_or_basis';
			return $result;
		}
		foreach(['cooling_capacity', 'power_input', 'refrigerant_mass_flow'] as $quantity) {
			if($quantity === 'refrigerant_mass_flow' && count(array_unique($massBasis)) !== 1) { $result['reasons'][] = 'mass_flow_basis_differs'; continue; }
			$values = [];
			foreach($evaluations as $evaluation) {
				$value = $evaluation['calculation']['quantities'][$quantity];
				if($value['status'] !== 'calculated') break;
				$values[] = $quantity === 'refrigerant_mass_flow' ? ($value['unit'] === 'kg/h' ? $value['value'] / 3600.0 : $value['value']) : $value['normalized']['value'];
			}
			$sum = count($values) === count($evaluations) ? $this->sum($values) : null;
			if($sum !== null && $sum > 0.0) $result[$quantity] = array_replace($result[$quantity], ['status' => 'calculated', 'value' => $sum]);
			else $result['reasons'][] = $quantity.':incomplete_or_out_of_range';
		}
		if($result['cooling_capacity']['value'] !== null && $result['power_input']['value'] !== null) {
			$cop = $result['cooling_capacity']['value'] / $result['power_input']['value'];
			if(is_finite($cop) && $cop > 0.0) $result['compressor_cop'] = array_replace($result['compressor_cop'], ['status' => 'calculated', 'value' => $cop]);
			else $result['reasons'][] = 'compressor_cop:out_of_range';
		}
		return $result;
	}

	private function sum(array $values): ?float
	{
		$sum = 0.0; $correction = 0.0;
		foreach($values as $value) {
			$next = $sum + $value;
			if(!is_finite($value) || !is_finite($next)) return null;
			$correction += abs($sum) >= abs($value) ? ($sum - $next) + $value : ($value - $next) + $sum;
			$sum = $next;
		}
		$result = $sum + $correction;
		return is_finite($result) ? $result : null;
	}
}
