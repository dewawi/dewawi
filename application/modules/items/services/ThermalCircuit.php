<?php

class Items_Service_ThermalCircuit
{
	public const VERSION = 'synthetic_single_circuit_nested_bisection_v1';

	// Resolution and compressor callbacks must use the same authorized, pinned snapshot.
	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, callable $compressor, ?string $calculationBlocker = null): array
	{
		return $this->evaluateModel($machine, $request, $resolve, $compressor, $calculationBlocker, null, null);
	}

	// A section-pair result is not a complete shared-component solution; ThermalMachine verifies the junctions.
	public function evaluateAssignedCircuit(stdClass $machine, stdClass $request, stdClass $model, string $circuitKey, callable $resolve, callable $compressor, ?string $calculationBlocker = null): array
	{
		return $this->evaluateModel($machine, $request, $resolve, $compressor, $calculationBlocker, $model, $circuitKey);
	}

	private function evaluateModel(stdClass $machine, stdClass $request, callable $resolve, callable $compressor, ?string $calculationBlocker, ?stdClass $assignedModel, ?string $circuitKey): array
	{
		$schema = new Items_Service_TechnicalSchema(); $machine = $schema->dataset($machine);
		if($machine->dataset_type !== 'machine_configuration') throw new InvalidArgumentException('A machine configuration is required');
		if(($machine->data->configuration_version ?? 1) !== 1) throw new InvalidArgumentException('Versioned mode configuration requires the operating mode evaluator');
		$input = $schema->fields($request, ['stage_key' => 'key', 'operating_mode' => ['cooling'], 'evaporator' => 'object', 'condenser' => 'object'], ['compressor_contexts' => 'object', 'temperature_offset_probe' => 'object']);
		$data = $machine->data;
		$stage = array_values(array_filter($data->stages, fn($s) => $s->stage_key === $input->stage_key));
		if(count($stage) !== 1) throw new InvalidArgumentException('Machine stage unavailable');
		$result = ['result_version' => 1, 'calculation_version' => self::VERSION, 'scope' => 'synthetic_thermal_coupling_only', 'status' => 'review_required', 'eligible' => false,
			'original_request' => json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR), 'operating_mode' => $input->operating_mode, 'stage_key' => $input->stage_key,
			'machine_revision' => ['dataset_key' => $machine->dataset_key, 'revision' => $machine->revision], 'model' => null, 'exchangers' => [], 'solution' => null,
			'convergence' => ['status' => 'not_started', 'outer_iterations' => 0, 'inner_iterations' => 0, 'evaluations' => 0, 'trace' => []], 'uniqueness' => null,
			'useful_machine_cooling_capacity' => ['status' => 'not_available', 'value' => null, 'unit' => 'kW'], 'machine_efficiency' => ['status' => 'not_available', 'value' => null],
			'assumptions' => ['synthetic_models_only', 'constant_explicit_cp_and_density', 'steady_state_no_external_compressor_heat_loss', 'no_auxiliary_power_estimate'],
			'reasons' => ['synthetic_models_only', 'machine_physics_not_released', 'expansion_system_evidence_missing', 'control_release_missing', 'auxiliary_power_unknown']];
		if(isset($input->temperature_offset_probe)) $result['temperature_offset_probe'] = $this->offsetProbe($input->temperature_offset_probe, $data->cooling_kind);
		if($circuitKey === null && $data->circuit_count !== 1) {
			$result['reasons'][] = 'multiple_circuit_thermal_coupling_not_supported';
			foreach($data->shared_components as $shared) if(count($shared->shared_by_circuits ?? []) > 1) $result['reasons'][] = 'shared_exchanger_air_or_liquid_coupling_not_supported';
			return $result;
		}
		if($assignedModel === null && ($data->thermal_model_ref ?? null) === null) { $result['reasons'][] = 'thermal_balance_model_missing'; return $result; }
		$model = $assignedModel === null ? $this->dataset($resolve, $data->thermal_model_ref, 'thermal_balance_model') : $schema->dataset($assignedModel);
		if($model->dataset_type !== 'thermal_balance_model' || $model->data->contract_version !== 1) throw new InvalidArgumentException('A single-circuit thermal model is required');
		$result['model'] = $model;
		if(!$this->synthetic($machine) || !$this->synthetic($model)) { $result['reasons'][] = 'real_machine_use_of_synthetic_models_forbidden'; return $result; }
		$selected = array_values(array_filter($data->circuits, fn($c) => $circuitKey === null || $c->circuit_key === $circuitKey));
		if(count($selected) !== 1) throw new InvalidArgumentException('Thermal circuit unavailable');
		$circuit = $selected[0]; $contract = $model->data;
		if($circuitKey !== null) $result['scope'] = 'synthetic_thermal_section_pair_only';
		if($contract->circuit_key !== $circuit->circuit_key) throw new InvalidArgumentException('Thermal model circuit mismatch');
		if($circuitKey === null) foreach($data->shared_components as $shared) if(in_array($shared->instance_key, [$circuit->evaporator_section->instance_key, $circuit->condenser_instance], true)) { $result['reasons'][] = 'shared_exchanger_coupling_not_supported'; return $result; }
		foreach($data->hydraulic_loops as $loop) {
			$uses = [];
			foreach($loop->edges as $edge) foreach($edge as $port) foreach([$circuit->evaporator_section->instance_key, $circuit->condenser_instance] as $instance) if(str_starts_with($port, $instance.'.')) $uses[$instance] = true;
			if(count($uses) > 1) { $result['reasons'][] = 'shared_secondary_liquid_loop_not_supported'; return $result; }
		}
		$curves = []; $maps = []; $sections = [];
		foreach($data->components as $component) {
			$active = in_array($component->instance_key, $stage[0]->active_compressors, true) && in_array($component->instance_key, $circuit->compressor_instances, true);
			$side = $component->instance_key === $circuit->evaporator_section->instance_key ? 'evaporator' : ($component->instance_key === $circuit->condenser_instance ? 'condenser' : null);
			if(!$active && $side === null) continue;
			$profile = $schema->profile($resolve('itemtechnicalprofile', $component->profile_key, $component->profile_revision));
			if($profile->profile_key !== $component->profile_key || $profile->revision !== $component->profile_revision || $profile->role !== $component->role || $profile->item_ref !== $component->component_key) throw new InvalidArgumentException('Thermal component profile mismatch');
			$record = $this->dataset($resolve, $profile->dataset_ref);
			if(!$this->synthetic($record)) { $result['reasons'][] = 'real_component_use_of_synthetic_models_forbidden'; return $result; }
			if($active) {
				if($record->dataset_type === 'component_spec') {
					$ref = $record->data->specification->polynomial_ref;
					if($ref === null) { $result['reasons'][] = 'compressor_polynomial_missing'; return $result; }
					$record = $this->dataset($resolve, $ref, 'compressor_polynomial');
				}
				if($record->dataset_type !== 'compressor_polynomial' || !$this->synthetic($record) || $record->data->refrigerant !== $circuit->refrigerant) throw new InvalidArgumentException('Thermal compressor curve mismatch');
				if($record->data->operating_envelope === null) { $result['reasons'][] = 'compressor_operating_envelope_required'; return $result; }
				$curves[] = $record;
			} else {
				$expectedRole = $side === 'evaporator' ? 'evaporator' : ($data->cooling_kind === 'air' ? 'air_condenser' : 'water_condenser');
				if($record->dataset_type !== 'component_spec' || $record->data->role !== $expectedRole) throw new InvalidArgumentException('Thermal exchanger role mismatch');
				$ref = $record->data->specification->thermal_map_ref;
				if($ref === null) { $result['reasons'][] = $side.':thermal_map_missing'; return $result; }
				$map = $this->dataset($resolve, $ref, 'heat_exchanger_map');
				foreach(['component_key', 'model', 'role', 'refrigerant'] as $key) if($map->data->$key !== $record->data->$key) throw new InvalidArgumentException('Thermal exchanger binding mismatch');
				if($map->data->refrigerant !== $circuit->refrigerant) throw new InvalidArgumentException('Thermal exchanger refrigerant mismatch');
				if(!$this->synthetic($map)) { $result['reasons'][] = 'synthetic_map_provenance_required'; return $result; }
				if($circuitKey === null && count($map->data->sections) !== 1) { $result['reasons'][] = 'multiple_exchanger_sections_coupling_not_supported'; return $result; }
				$sections[$side] = $side === 'evaporator' ? $circuit->evaporator_section->section_key : ($circuit->condenser_section_key ?? $map->data->sections[0]->section_key);
				$maps[$side] = $map;
				$result['exchangers'][$side] = ['instance_key' => $component->instance_key, 'profile_key' => $component->profile_key, 'profile_revision' => $component->profile_revision, 'map' => $map, 'section_key' => $sections[$side]];
			}
		}
		if(!$curves || count($maps) !== 2) { $result['reasons'][] = 'thermal_component_data_incomplete'; return $result; }
		$hx = new Items_Service_HeatExchangerMap(); $prepared = [];
		foreach(['evaporator' => 'Te', 'condenser' => 'Tc'] as $side => $axis) {
			$prepared[$side] = $hx->prepare($maps[$side], $sections[$side], $input->$side);
			$result['exchangers'][$side]['boundary'] = $prepared[$side];
			foreach($prepared[$side]['reasons'] as $reason) $result['reasons'][] = $side.':'.$reason;
			if($prepared[$side]['status'] === 'unsuitable') $result['status'] = 'unsuitable';
			if($prepared[$side]['conductance_kW_K'] === null) return $result;
			if($prepared[$side]['section']->pressure_drop_kPa === null) $result['reasons'][] = $side.':pressure_loss_evidence_missing';
			foreach(['min', 'max'] as $bound) if(!$hx->inside($contract->search_bounds->$axis->$bound, $prepared[$side]['section']->limits->saturation_temperature)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'search_bounds_outside_exchanger_map'; return $result; }
			foreach($curves as $curve) if($curve->data->temperature_basis->$axis !== $maps[$side]->data->temperature_basis) { $result['reasons'][] = 'saturation_temperature_basis_mismatch'; return $result; }
		}
		foreach(['Te', 'Tc'] as $axis) foreach(['min', 'max'] as $bound) {
			$value = $contract->search_bounds->$axis->$bound;
			if(Items_Service_TechnicalJson::compareDecimal($value, json_encode((float)$value, JSON_THROW_ON_ERROR)) !== 0) { $result['reasons'][] = 'search_boundary_precision_unconfirmed'; return $result; }
		}
		$polynomial = new Items_Service_CompressorPolynomial();
		foreach($curves as $curve) {
			$vertices = $curve->data->operating_envelope->vertices;
			$teValues = array_unique(array_map(fn($v) => $v->Te, $vertices)); $tcValues = array_unique(array_map(fn($v) => $v->Tc, $vertices));
			if(count($vertices) !== 4 || count($teValues) !== 2 || count($tcValues) !== 2) { $result['reasons'][] = 'compressor_rectangular_search_domain_not_proven'; return $result; }
			foreach(['min', 'max'] as $teBound) foreach(['min', 'max'] as $tcBound) {
				$region = $polynomial->operatingRegion($curve->data->operating_envelope, (object)['Te_degC' => $contract->search_bounds->Te->$teBound, 'Tc_degC' => $contract->search_bounds->Tc->$tcBound, 'temperature_unit' => 'degC']);
				if($region['status'] !== 'inside') { $result['status'] = $region['status'] === 'outside' ? 'unsuitable' : 'review_required'; $result['reasons'][] = $region['status'] === 'outside' ? 'search_bounds_outside_compressor_envelope' : $region['reason']; return $result; }
			}
		}
		if($calculationBlocker !== null) { $result['reasons'][] = $calculationBlocker; return $result; }
		try {
			$certificate = $this->uniqueness($curves, $contract, $prepared);
			$result['uniqueness'] = $certificate;
			if(!$certificate['certified']) { $result['convergence']['status'] = 'ambiguous_or_uncertified'; $result['reasons'][] = 'solution_uniqueness_not_proven'; return $result; }
			if(!$certificate['inner_bracket_certified']) { $result['reasons'][] = 'evaporator_root_not_bracketed_across_search'; return $result; }
			$stageRequest = (object)['stage_key' => $input->stage_key, 'operating_mode' => $input->operating_mode];
			if(isset($input->compressor_contexts)) $stageRequest->compressor_contexts = $input->compressor_contexts;
			$failure = null;
			$evaluate = function(float $te, float $tc) use ($machine, $stageRequest, $circuit, $circuitKey, $compressor, $prepared, $hx, $curves, $certificate, $contract, &$result, &$failure): ?array {
				$result['convergence']['evaluations']++;
				$point = (object)['Te_degC' => json_encode($te, JSON_THROW_ON_ERROR), 'Tc_degC' => json_encode($tc, JSON_THROW_ON_ERROR), 'temperature_unit' => 'degC'];
				$stageRequest->circuit_points = [(object)['circuit_key' => $circuit->circuit_key, 'point' => $point]];
				$stage = $circuitKey === null ? (new Items_Service_MachineStage())->evaluate($machine, $stageRequest, $compressor) : (new Items_Service_MachineStage())->evaluateCircuit($machine, $circuitKey, $stageRequest, $compressor);
				$sum = $stage['stage_compressor_sums'];
				$evap = $hx->atPoint($prepared['evaporator'], $point->Te_degC); $cond = $hx->atPoint($prepared['condenser'], $point->Tc_degC);
				if($sum['cooling_capacity']['value'] === null || $sum['power_input']['value'] === null || $evap['mathematical_status'] !== 'calculated' || $cond['mathematical_status'] !== 'calculated') {
					$failure = ['status' => in_array('unsuitable', [$stage['status'], $evap['status'], $cond['status']], true) ? 'unsuitable' : 'review_required', 'reasons' => array_values(array_unique(array_merge($stage['reasons'], $evap['reasons'], $cond['reasons'])))];
					return null;
				}
				$q = $sum['cooling_capacity']['value']; $p = $sum['power_input']['value']; $required = $q + $p;
				$re = $q - $evap['heat_flow_kW']; $rc = $required - $cond['heat_flow_kW'];
				if(!is_finite($required) || !is_finite($re) || !is_finite($rc)) throw new OverflowException('thermal_numeric_out_of_range');
				$bounds = $this->residualBounds($curves, $prepared, $te, $tc);
				$uncertainty = [max(abs($re - $bounds[0][0]), abs($re - $bounds[0][1])), max(abs($rc - $bounds[1][0]), abs($rc - $bounds[1][1]))];
				$roundingFloor = $this->temperatureError($uncertainty, $certificate);
				if(max($uncertainty) > (float)$contract->numerics->heat_tolerance_kW / 4.0 || max($roundingFloor) > (float)$contract->numerics->temperature_tolerance_K / 4.0) {
					$failure = ['status' => 'review_required', 'reasons' => ['thermal_arithmetic_precision_unconfirmed']]; return null;
				}
				$innerError = $this->nextUp(max(abs($bounds[0][0]), abs($bounds[0][1])) / $certificate['evaporator_residual_dTe'][0]);
				$margin = $this->nextUp(max(array_map('abs', $certificate['condenser_required_heat_dTe'])) * $innerError);
				$rootResidual = $this->add($bounds[1], [-$margin, $margin]);
				$error = $this->temperatureError([max(abs($bounds[0][0]), abs($bounds[0][1])), max(abs($bounds[1][0]), abs($bounds[1][1]))], $certificate);
				return ['point' => $point, 'compressor_stage' => $stage, 'evaporator' => $evap, 'condenser' => $cond, 'energy_balance' => ['compressor_cooling_kW' => $q, 'compressor_power_kW' => $p, 'required_condenser_heat_kW' => $required, 'external_heat_loss_kW' => 0.0, 'residual_evaporator_kW' => $re, 'residual_condenser_kW' => $rc], 'residual_enclosures_kW' => $bounds, 'condenser_root_residual_enclosure_kW' => $rootResidual, 'temperature_error_bound_K' => ['Te' => $error[0], 'Tc' => $error[1]]];
			};
			$solution = $this->solve($contract, $evaluate, $certificate['outer_sign'], $result['convergence']);
			if($failure !== null) { $result['status'] = $failure['status']; $result['reasons'] = array_merge($result['reasons'], $failure['reasons']); }
			if($result['convergence']['status'] === 'no_solution_in_search_domain') { $result['status'] = 'unsuitable'; $result['reasons'][] = 'no_coupled_solution_in_validated_search_domain'; }
			if($solution !== null) { $result['solution'] = $solution; $result['reasons'] = array_values(array_unique(array_merge($result['reasons'], $contract->missing_evidence))); }
			else $result['reasons'][] = 'thermal_solution_not_converged';
		} catch(OverflowException $e) { $result['convergence']['status'] = 'numeric_failure'; $result['reasons'][] = 'thermal_numeric_out_of_range'; }
		return $result;
	}

	public function mixParallel(array $branches, string $inlet, string $totalFlow, string $side, stdClass $numerics): array
	{
		$result = ['scope' => 'synthetic_secondary_junction_only', 'eligible' => false, 'status' => 'review_required', 'mathematical_status' => 'not_available', 'mixed_outlet_degC' => null, 'reasons' => []];
		try {
			$capacity = $weighted = $heat = $compressorHeat = 0.0; $cBox = $wBox = $hBox = $rBox = [0.0, 0.0]; $flow = '0'; $outlets = [];
			foreach($branches as $key => $branch) {
				$p = $branch['prepared']; $value = $branch['result']; $c = $p['capacity_rate_kW_K'];
				if($c === null || $value['mathematical_status'] !== 'calculated') { $result['reasons'][] = 'secondary_branch_incomplete'; return $result; }
				$flow = Items_Service_TechnicalJson::addNonnegativeDecimals($flow, $p['inputs']->volume_flow_m3_h);
				$outlets[$key] = $value['secondary_outlet_degC']; $capacity += $c; $heat += $value['heat_flow_kW']; $compressorHeat += $branch['compressor_heat_kW'];
				$cBox = $this->add($cBox, $this->bound([$c])); $hBox = $this->add($hBox, $this->bound([$value['heat_flow_kW']]));
				$rBox = $this->add($rBox, $branch['residual_enclosure_kW']);
				$delta = $value['secondary_outlet_degC'] - (float)$inlet;
				$weighted += $c * $delta;
				// The registered parked branch is exactly pass-through; no heat/standby estimate is made.
				$outletError = $this->nextUp($branch['outlet_error_bound_K']);
				$outletBox = $this->add($this->bound([$value['secondary_outlet_degC']]), [-$outletError, $outletError]);
				$deltaBox = $branch['active'] ? $this->add($outletBox, $this->bound([-(float)$inlet])) : [0.0, 0.0];
				$wBox = $this->add($wBox, $this->multiply($this->bound([$c]), $deltaBox));
			}
			if($flow !== Items_Service_TechnicalJson::decimal($totalFlow)) { $result['reasons'][] = 'secondary_flow_total_mismatch'; return $result; }
			if($capacity <= 0.0 || $cBox[0] <= 0.0 || !is_finite($capacity) || !is_finite($weighted) || !is_finite($heat) || !is_finite($compressorHeat)) throw new OverflowException('thermal_numeric_out_of_range');
			$mix = (float)$inlet + $weighted / $capacity;
			$deltaBox = $this->multiply($wBox, $this->bound([1.0 / $cBox[1], 1.0 / $cBox[0]]));
			$mixBox = $this->add($this->bound([(float)$inlet]), $deltaBox);
			$temperatureError = max(abs($mix - $mixBox[0]), abs($mix - $mixBox[1]));
			$direction = $side === 'evaporator' ? -1.0 : 1.0;
			$secondary = $direction * $capacity * ($mix - (float)$inlet);
			$secondaryBox = $this->multiply([$direction, $direction], $this->multiply($cBox, $this->add($this->bound([$mix]), $this->bound([-(float)$inlet]))));
			$residualBox = $this->add($secondaryBox, [-$hBox[1], -$hBox[0]]);
			if(!is_finite($mix) || !is_finite($secondary)) throw new OverflowException('thermal_numeric_out_of_range');
			if($temperatureError > (float)$numerics->temperature_tolerance_K || max(array_map('abs', array_merge($residualBox, $rBox))) > (float)$numerics->heat_tolerance_kW) { $result['reasons'][] = 'shared_secondary_balance_or_precision_unconfirmed'; return $result; }
			return array_replace($result, ['mathematical_status' => 'calculated', 'inlet_degC' => $inlet, 'total_volume_flow_m3_h' => $totalFlow, 'branch_volume_flow_sum_m3_h' => $flow, 'volume_flow_residual_m3_h' => '0',
				'capacity_rate_kW_K' => $capacity, 'mixed_outlet_degC' => $mix, 'mixed_outlet_error_bound_K' => $temperatureError, 'section_outlets_degC' => $outlets, 'heat_flow_sum_kW' => $heat,
				'secondary_heat_kW' => $secondary, 'compressor_heat_sum_kW' => $compressorHeat, 'secondary_heat_residual_kW' => $secondary - $heat, 'compressor_heat_residual_kW' => $compressorHeat - $heat,
				'secondary_heat_residual_enclosure_kW' => $residualBox, 'compressor_heat_residual_enclosure_kW' => $rBox]);
		} catch(OverflowException $e) { $result['reasons'][] = 'shared_secondary_numeric_out_of_range'; return $result; }
	}

	public function sumThermalBalances(array $circuits, stdClass $numerics): array
	{
		$result = ['scope' => 'synthetic_thermal_balances_only', 'eligible' => false, 'mathematical_status' => 'not_available', 'reasons' => []];
		try {
			$totals = array_fill_keys(['compressor_cooling_kW', 'compressor_power_kW', 'evaporator_heat_kW', 'condenser_heat_kW'], 0.0); $boxes = array_fill_keys(array_keys($totals), [0.0, 0.0]);
			foreach($circuits as $circuit) {
				$values = ['compressor_cooling_kW' => $circuit['energy_balance']['compressor_cooling_kW'], 'compressor_power_kW' => $circuit['energy_balance']['compressor_power_kW'], 'evaporator_heat_kW' => $circuit['evaporator']['heat_flow_kW'], 'condenser_heat_kW' => $circuit['condenser']['heat_flow_kW']];
				foreach($values as $key => $value) { $totals[$key] += $value; $boxes[$key] = $this->add($boxes[$key], $this->bound([$value])); }
			}
			foreach($totals as $value) if(!is_finite($value)) throw new OverflowException('thermal_numeric_out_of_range');
			$residual = $totals['condenser_heat_kW'] - $totals['evaporator_heat_kW'] - $totals['compressor_power_kW'];
			$box = $this->add($this->add($boxes['condenser_heat_kW'], [-$boxes['evaporator_heat_kW'][1], -$boxes['evaporator_heat_kW'][0]]), [-$boxes['compressor_power_kW'][1], -$boxes['compressor_power_kW'][0]]);
			if(max(array_map('abs', $box)) > (float)$numerics->heat_tolerance_kW) { $result['reasons'][] = 'global_thermal_balance_unconfirmed'; return $result; }
			return array_replace($result, $totals, ['energy_residual_kW' => $residual, 'energy_residual_enclosure_kW' => $box, 'mathematical_status' => 'calculated']);
		} catch(OverflowException $e) { $result['reasons'][] = 'global_thermal_numeric_out_of_range'; return $result; }
	}

	private function solve(stdClass $model, callable $evaluate, int $sign, array &$convergence): ?array
	{
		$n = $model->numerics; $tolerance = (float)$n->heat_tolerance_kW; $temperature = (float)$n->temperature_tolerance_K;
		$inner = function(float $tc) use ($model, $evaluate, $n, $temperature, $tolerance, &$convergence): ?array {
			$lo = (float)$model->search_bounds->Te->min; $hi = (float)$model->search_bounds->Te->max;
			for($i = 1; $i <= $n->max_inner_iterations; $i++) {
				$convergence['inner_iterations']++; $mid = $lo + ($hi - $lo) / 2.0;
				$sample = $evaluate($mid, $tc); if($sample === null) { $convergence['status'] = 'trial_point_blocked'; return null; }
				$residual = $sample['energy_balance']['residual_evaporator_kW'];
				if($residual === 0.0) $lo = $hi = $mid;
				if($hi - $lo <= $temperature && abs($residual) <= $tolerance) { $sample['search_brackets_degC']['Te'] = [$lo, $hi]; return $sample; }
				if($residual > 0.0) $hi = $mid; else $lo = $mid;
			}
			$convergence['status'] = 'inner_iteration_limit'; return null;
		};
		$lo = (float)$model->search_bounds->Tc->min; $hi = (float)$model->search_bounds->Tc->max;
		$left = $inner($lo); if($left === null) return null;
		$right = $inner($hi); if($right === null) return null;
		$rl = $left['energy_balance']['residual_condenser_kW']; $rh = $right['energy_balance']['residual_condenser_kW'];
		$convergence['boundary_residual_condenser_kW'] = [$rl, $rh];
		$lb = $left['condenser_root_residual_enclosure_kW']; $rb = $right['condenser_root_residual_enclosure_kW'];
		$convergence['boundary_root_residual_enclosures_kW'] = [$lb, $rb];
		if($sign < 0) { $lb = [-$lb[1], -$lb[0]]; $rb = [-$rb[1], -$rb[0]]; }
		if($lb[0] > 0.0 || $rb[1] < 0.0) { $convergence['status'] = 'no_solution_in_search_domain'; return null; }
		if($lb[1] >= 0.0 || $rb[0] <= 0.0) { $convergence['status'] = 'boundary_sign_uncertain'; return null; }
		for($i = 1; $i <= $n->max_outer_iterations; $i++) {
			$convergence['outer_iterations'] = $i; $mid = $lo + ($hi - $lo) / 2.0;
			$sample = $inner($mid); if($sample === null) return null;
			$balance = $sample['energy_balance']; $residual = $balance['residual_condenser_kW'];
			if($residual === 0.0) $lo = $hi = $mid;
			$convergence['trace'][] = ['iteration' => $i, 'point' => $sample['point'], 'residual_evaporator_kW' => $balance['residual_evaporator_kW'], 'residual_condenser_kW' => $residual, 'Tc_bracket_degC' => [$lo, $hi], 'Te_bracket_degC' => $sample['search_brackets_degC']['Te']];
			if($hi - $lo <= $temperature && max(abs($residual), abs($balance['residual_evaporator_kW'])) <= $tolerance && max($sample['temperature_error_bound_K']) <= $temperature && max(array_map('abs', array_merge(...$sample['residual_enclosures_kW']))) <= $tolerance) { $convergence['status'] = 'converged'; $sample['search_brackets_degC']['Tc'] = [$lo, $hi]; return $sample; }
			if($residual * $sign > 0.0) $hi = $mid; else $lo = $mid;
		}
		$convergence['status'] = 'outer_iteration_limit'; return null;
	}

	private function dataset(callable $resolve, stdClass $ref, ?string $type = null): stdClass
	{
		$r = (new Items_Service_TechnicalSchema())->dataset($resolve('technicaldataset', $ref->dataset_key, $ref->revision));
		if($r->dataset_key !== $ref->dataset_key || $r->revision !== $ref->revision || ($type !== null && $r->dataset_type !== $type)) throw new InvalidArgumentException('Thermal reference mismatch');
		return $r;
	}

	private function synthetic(stdClass $record): bool
	{
		return ($record->import_context->template_kind ?? null) === 'synthetic_thermal_test_v1' && ($record->import_context->production_import_allowed ?? null) === false;
	}

	private function offsetProbe(stdClass $request, string $coolingKind): array
	{
		if($coolingKind !== 'air') throw new InvalidArgumentException('Air offset probe cannot be used for a water condenser');
		$p = (new Items_Service_TechnicalSchema())->fields($request, ['model_id' => ['fixed_temperature_offsets_v1'], 'ambient_degC' => 'number', 'supply_degC' => 'number', 'condensing_offset_K' => 'positive', 'evaporator_approach_K' => 'positive', 'water_delta_T_K' => 'positive']);
		$te = (float)$p->supply_degC - (float)$p->evaporator_approach_K; $tc = (float)$p->ambient_degC + (float)$p->condensing_offset_K;
		if((float)$p->ambient_degC <= -273.15 || (float)$p->supply_degC <= -273.15 || !is_finite($te) || !is_finite($tc) || $te <= -273.15 || $te >= $tc) throw new InvalidArgumentException('Invalid offset probe');
		return ['scope' => 'requested_offset_probe_only_not_solver_input', 'eligible' => false, 'status' => 'review_required', 'inputs' => $p, 'Te_degC' => $te, 'Tc_degC' => $tc];
	}

	private function uniqueness(array $curves, stdClass $model, array $prepared): array
	{
		$te = [(float)$model->search_bounds->Te->min, (float)$model->search_bounds->Te->max]; $tc = [(float)$model->search_bounds->Tc->min, (float)$model->search_bounds->Tc->max];
		$q = $p = [[0.0, 0.0], [0.0, 0.0], [0.0, 0.0]]; $low = $high = [0.0, 0.0];
		foreach($curves as $curve) {
			foreach(['cooling_capacity' => 'q', 'power_input' => 'p'] as $quantity => $target) {
				$bounds = $this->polynomialBounds($curve->data->quantities->$quantity, $te, $tc);
				foreach($bounds as $i => $bound) ${$target}[$i] = $this->add(${$target}[$i], $bound);
			}
			$low = $this->add($low, $this->polynomialBounds($curve->data->quantities->cooling_capacity, [$te[0], $te[0]], $tc)[0]);
			$high = $this->add($high, $this->polynomialBounds($curve->data->quantities->cooling_capacity, [$te[1], $te[1]], $tc)[0]);
		}
		$ke = $this->bound([$prepared['evaporator']['conductance_kW_K']]); $kc = $this->bound([$prepared['condenser']['conductance_kW_K']]);
		$fTe = $this->add($q[1], $ke);
		$gTc = $this->add($this->add($q[2], $p[2]), $this->multiply([-1.0, -1.0], $kc));
		$det = $this->add($this->multiply($gTc, $fTe), $this->multiply([-1.0, -1.0], $this->multiply($this->add($q[1], $p[1]), $q[2])));
		$tin = (float)$prepared['evaporator']['inputs']->inlet_degC;
		$low = $this->add($low, $this->multiply($ke, $this->bound([$te[0] - $tin])));
		$high = $this->add($high, $this->multiply($ke, $this->bound([$te[1] - $tin])));
		$sign = $det[1] < 0.0 ? -1 : ($det[0] > 0.0 ? 1 : 0);
		return ['method' => 'outward_binary64_cubic_derivative_intervals_v1', 'certified' => $fTe[0] > 0.0 && $sign !== 0,
			'inner_bracket_certified' => $low[1] <= 0.0 && $high[0] >= 0.0, 'outer_sign' => $sign,
			'evaporator_residual_dTe' => $fTe, 'compressor_dQ_dTc' => $q[2], 'condenser_required_heat_dTe' => $this->add($q[1], $p[1]), 'coupled_jacobian_determinant' => $det, 'evaporator_lower_edge' => $low, 'evaporator_upper_edge' => $high];
	}

	private function residualBounds(array $curves, array $prepared, float $te, float $tc): array
	{
		$q = $p = [0.0, 0.0];
		foreach($curves as $curve) {
			$q = $this->add($q, $this->polynomialBounds($curve->data->quantities->cooling_capacity, [$te, $te], [$tc, $tc], false)[0]);
			$p = $this->add($p, $this->polynomialBounds($curve->data->quantities->power_input, [$te, $te], [$tc, $tc], false)[0]);
		}
		$heat = [];
		foreach(['evaporator' => $te, 'condenser' => $tc] as $side => $sat) {
			$inlet = $this->bound([(float)$prepared[$side]['inputs']->inlet_degC]);
			$difference = $this->add($inlet, [-$sat, -$sat]);
			if($side === 'condenser') $difference = [-$difference[1], -$difference[0]];
			$heat[$side] = $this->multiply($this->bound([$prepared[$side]['conductance_kW_K']]), $difference);
		}
		return [$this->add($q, [-$heat['evaporator'][1], -$heat['evaporator'][0]]), $this->add($this->add($q, $p), [-$heat['condenser'][1], -$heat['condenser'][0]])];
	}

	private function temperatureError(array $residuals, array $certificate): array
	{
		$innerSlope = $certificate['evaporator_residual_dTe'][0];
		$outerSlope = -$this->nextUp(-min(array_map('abs', $certificate['coupled_jacobian_determinant'])) / $certificate['evaporator_residual_dTe'][1]);
		if($outerSlope <= 0.0 || !is_finite($outerSlope)) throw new OverflowException('thermal_numeric_out_of_range');
		$teError = $this->nextUp($residuals[0] / $innerSlope);
		$propagated = $this->nextUp(max(array_map('abs', $certificate['condenser_required_heat_dTe'])) * $teError);
		$tcError = $this->nextUp($this->nextUp($residuals[1] + $propagated) / $outerSlope);
		$coupling = $this->nextUp(max(array_map('abs', $certificate['compressor_dQ_dTc'])) / $innerSlope);
		$teError = $this->nextUp($teError + $this->nextUp($coupling * $tcError));
		if(!is_finite($teError) || !is_finite($tcError)) throw new OverflowException('thermal_numeric_out_of_range');
		return [$teError, $tcError];
	}

	private function polynomialBounds(stdClass $quantity, array $te, array $tc, bool $derivatives = true): array
	{
		$one = [1.0, 1.0]; $zero = [0.0, 0.0];
		$te2 = $this->multiply($te, $te); $tc2 = $this->multiply($tc, $tc); $tet = $this->multiply($te, $tc);
		$terms = [$one, $te, $tc, $te2, $tet, $tc2, $this->multiply($te2, $te), $this->multiply($te2, $tc), $this->multiply($te, $tc2), $this->multiply($tc2, $tc)];
		$dte = [$zero, $one, $zero, $this->multiply([2.0, 2.0], $te), $tc, $zero, $this->multiply([3.0, 3.0], $te2), $this->multiply([2.0, 2.0], $tet), $tc2, $zero];
		$dtc = [$zero, $zero, $one, $zero, $te, $this->multiply([2.0, 2.0], $tc), $zero, $te2, $this->multiply([2.0, 2.0], $tet), $this->multiply([3.0, 3.0], $tc2)];
		$result = [];
		foreach($derivatives ? [$terms, $dte, $dtc] : [$terms] as $basis) {
			$sum = $zero;
			foreach($quantity->coefficients_C0_to_C9 as $i => $coefficient) {
				if($coefficient === '0') continue;
				$c = $this->bound([(float)$coefficient]);
				if($quantity->unit === 'W') $c = $this->multiply($c, $this->bound([0.001]));
				$sum = $this->add($sum, $this->multiply($c, $basis[$i]));
			}
			$result[] = $sum;
		}
		return $result;
	}

	private function add(array $a, array $b): array { return $this->bound([$a[0] + $b[0], $a[1] + $b[1]]); }
	private function multiply(array $a, array $b): array { return $this->bound([$a[0] * $b[0], $a[0] * $b[1], $a[1] * $b[0], $a[1] * $b[1]]); }
	private function bound(array $values): array
	{
		foreach($values as $value) if(!is_finite($value)) throw new OverflowException('thermal_numeric_out_of_range');
		$lo = -$this->nextUp(-min($values)); $hi = $this->nextUp(max($values));
		if(!is_finite($lo) || !is_finite($hi)) throw new OverflowException('thermal_numeric_out_of_range');
		return [$lo, $hi];
	}

	// Advance one IEEE-754 binary64 value; explicit little-endian packing is platform independent.
	private function nextUp(float $value): float
	{
		if($value === 0.0) return unpack('e', "\x01\x00\x00\x00\x00\x00\x00\x00")[1];
		$bytes = pack('e', $value); $change = $value > 0.0 ? 1 : -1;
		for($i = 0; $i < 8; $i++) {
			$byte = ord($bytes[$i]) + $change; $bytes[$i] = chr(($byte + 256) % 256);
			if($byte >= 0 && $byte <= 255) break;
		}
		return unpack('e', $bytes)[1];
	}
}
