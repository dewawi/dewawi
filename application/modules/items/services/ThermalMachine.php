<?php

class Items_Service_ThermalMachine
{
	public const VERSION = 'synthetic_fixed_inlet_parallel_machine_v1';

	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, callable $compressor, ?string $calculationBlocker = null): array
	{
		$schema = new Items_Service_TechnicalSchema(); $machine = $schema->dataset($machine); $data = $machine->data;
		if($machine->dataset_type !== 'machine_configuration') throw new InvalidArgumentException('A machine configuration is required');
		if($data->circuit_count === 1 && isset($request->fluid_hydraulics)) throw new InvalidArgumentException('Fluid thermal audit requires the parallel thermal contract');
		if($data->circuit_count === 1) return (new Items_Service_ThermalCircuit())->evaluate($machine, $request, $resolve, $compressor, $calculationBlocker);
		$input = $schema->fields($request, ['stage_key' => 'key', 'operating_mode' => ['cooling'], 'secondary_boundaries' => 'list:object'], ['compressor_contexts' => 'object', 'fluid_hydraulics' => 'object']);
		$stages = array_values(array_filter($data->stages, fn($s) => $s->stage_key === $input->stage_key));
		$modes = array_values(array_filter($data->mode_topologies, fn($m) => $m->mode === $input->operating_mode && $m->release_status !== 'unsupported'));
		if(count($stages) !== 1 || count($modes) !== 1) throw new InvalidArgumentException('Machine stage or mode unavailable');
		$result = ['result_version' => 1, 'calculation_version' => self::VERSION, 'scope' => 'synthetic_parallel_thermal_machine_only', 'status' => 'review_required', 'eligible' => false,
			'original_request' => json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR), 'stage_key' => $input->stage_key, 'operating_mode' => $input->operating_mode,
			'machine_revision' => ['dataset_key' => $machine->dataset_key, 'revision' => $machine->revision], 'model' => null, 'solution' => null, 'diagnostics' => [],
			'convergence' => ['method' => 'fixed_inlet_parallel_block_diagonal_v1', 'status' => 'not_started', 'evaluations' => 0, 'maximum_evaluations' => 0, 'junction_passes' => 0],
			'useful_machine_cooling_capacity' => ['status' => 'not_available', 'value' => null, 'unit' => 'kW'], 'machine_efficiency' => ['status' => 'not_available', 'value' => null],
			'assumptions' => ['fixed_common_inlet_no_recirculation', 'explicit_fixed_branch_flows', 'synthetic_constant_properties_only', 'declared_inactive_pass_through', 'no_auxiliary_estimates'],
			'reasons' => ['synthetic_models_only', 'machine_physics_not_released', 'expansion_system_evidence_missing', 'control_release_missing', 'auxiliary_power_unknown']];
		if($data->circuit_count !== 2) { $result['reasons'][] = 'thermal_parallel_circuit_count_not_supported'; return $result; }
		if(($data->thermal_model_ref ?? null) === null) { $result['reasons'][] = 'secondary_distribution_model_missing'; return $result; }
		$model = $this->dataset($resolve, $data->thermal_model_ref, 'thermal_balance_model'); $result['model'] = $model;
		if($model->data->contract_version !== 2) { $result['reasons'][] = 'explicit_parallel_secondary_contract_required'; return $result; }
		$schema->thermalMachineBinding($data, $model->data);
		if(!$this->synthetic($machine) || !$this->synthetic($model)) { $result['reasons'][] = 'real_machine_use_of_synthetic_models_forbidden'; return $result; }
		$contract = $model->data; $stage = $stages[0]; $boundaries = []; $models = [];
		$contexts = (array)($input->compressor_contexts ?? new stdClass());
		if(array_diff(array_keys($contexts), $stage->active_compressors)) throw new InvalidArgumentException('Context requires an active compressor instance');
		foreach($contexts as $context) if(!$context instanceof stdClass) throw new InvalidArgumentException('Invalid compressor operating context');
		foreach($input->secondary_boundaries as $entry) {
			$entry = $schema->fields($entry, ['group_key' => 'key', 'boundary' => 'object']);
			if(isset($boundaries[$entry->group_key])) throw new InvalidArgumentException('Duplicate secondary boundary');
			$boundaries[$entry->group_key] = $entry->boundary;
		}
		$expected = array_map(fn($g) => $g->group_key, $contract->secondary_groups); $actual = array_keys($boundaries); sort($expected); sort($actual);
		if($actual !== $expected) throw new InvalidArgumentException('Explicit boundaries required for every secondary group');
		foreach($contract->circuits as $assignment) {
			$single = $this->dataset($resolve, $assignment->circuit_model_ref, 'thermal_balance_model');
			if($single->data->contract_version !== 1 || $single->data->circuit_key !== $assignment->circuit_key) throw new InvalidArgumentException('Parallel circuit model mismatch');
			if(!$this->synthetic($single)) { $result['reasons'][] = 'synthetic_circuit_model_provenance_required'; return $result; }
			$models[$assignment->circuit_key] = $single;
		}
		$components = []; foreach($data->components as $component) $components[$component->instance_key] = $component;
		$hx = new Items_Service_HeatExchangerMap(); $assigned = []; $groups = [];
		foreach($contract->secondary_groups as $group) {
			$component = $components[$group->instance_key];
			$profile = $schema->profile($resolve('itemtechnicalprofile', $component->profile_key, $component->profile_revision));
			if($profile->profile_key !== $component->profile_key || $profile->revision !== $component->profile_revision || $profile->item_ref !== $component->component_key || $profile->role !== $component->role) throw new InvalidArgumentException('Parallel exchanger profile mismatch');
			$spec = $this->dataset($resolve, $profile->dataset_ref, 'component_spec'); $ref = $spec->data->specification->thermal_map_ref;
			if($ref === null) { $result['reasons'][] = $group->group_key.':thermal_map_missing'; return $result; }
			$map = $this->dataset($resolve, $ref, 'heat_exchanger_map'); $schema->exchangerBinding($spec->data, $map->data);
			$schema->secondarySectionBinding($group, array_map(fn($section) => $section->section_key, $map->data->sections));
			if(!$this->synthetic($spec) || !$this->synthetic($map)) { $result['reasons'][] = 'synthetic_exchanger_provenance_required'; return $result; }
			if($group->total_volume_flow_m3_h === null || in_array(null, array_map(fn($b) => $b->volume_flow_m3_h, $group->branches), true)) { $result['reasons'][] = 'secondary_split_underdetermined'; return $result; }
			$boundary = $schema->fields($boundaries[$group->group_key], ['medium_key' => 'key', 'inlet_degC' => 'number', 'volume_flow_m3_h' => '?positive', 'fluid_state' => '?text']);
			if($boundary->volume_flow_m3_h === null || $boundary->fluid_state === null) { $result['reasons'][] = 'secondary_boundary_incomplete'; return $result; }
			if($boundary->medium_key !== $group->medium_key || $boundary->volume_flow_m3_h !== $group->total_volume_flow_m3_h) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_boundary_distribution_mismatch'; return $result; }
			if(!$hx->inside($boundary->inlet_degC, $group->limits->secondary_inlet) || !$hx->inside($boundary->volume_flow_m3_h, $group->limits->total_volume_flow)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_group_boundary_outside_limits'; return $result; }
			$groups[$group->group_key] = ['contract' => $group, 'boundary' => $boundary, 'map' => $map, 'profile_key' => $component->profile_key, 'profile_revision' => $component->profile_revision];
			$medium = null;
			foreach($group->branches as $branch) {
				$branchInput = clone $boundary; $branchInput->volume_flow_m3_h = $branch->volume_flow_m3_h;
				$prepared = $hx->prepare($map, $branch->section_key, $branchInput);
				if($prepared['conductance_kW_K'] === null) { $result['status'] = $prepared['status']; $result['reasons'] = array_merge($result['reasons'], $prepared['reasons']); return $result; }
				$signature = Items_Service_TechnicalJson::encode($prepared['medium']);
				if($medium !== null && $medium !== $signature) { $result['reasons'][] = 'secondary_mixing_properties_incompatible'; return $result; }
				$medium = $signature;
				$assigned[$branch->circuit_key][$group->side] = ['group_key' => $group->group_key, 'prepared' => $prepared, 'instance_key' => $group->instance_key, 'section_key' => $branch->section_key];
				foreach($prepared['reasons'] as $reason) $result['reasons'][] = $group->group_key.':'.$reason;
				if($prepared['section']->pressure_drop_kPa === null) $result['reasons'][] = $group->group_key.':pressure_loss_evidence_missing';
			}
		}
		if($calculationBlocker !== null) { $result['reasons'][] = $calculationBlocker; return $result; }
		$core = new Items_Service_ThermalCircuit(); $solutions = []; $compressors = [];
		foreach($data->circuits as $circuit) {
			$key = $circuit->circuit_key; $active = array_values(array_intersect($stage->active_compressors, $circuit->compressor_instances));
			if(!$active) {
				$evap = $hx->parked($assigned[$key]['evaporator']['prepared']); $cond = $hx->parked($assigned[$key]['condenser']['prepared']);
				if($evap['mathematical_status'] !== 'calculated' || $cond['mathematical_status'] !== 'calculated') { $result['status'] = in_array('unsuitable', [$evap['status'], $cond['status']], true) ? 'unsuitable' : 'review_required'; $result['convergence']['status'] = 'inactive_boundary_blocked'; $result['reasons'] = array_merge($result['reasons'], $evap['reasons'], $cond['reasons']); return $result; }
				$solutions[$key] = ['state' => 'inactive', 'eligible' => false, 'point' => null, 'evaporator' => $evap, 'condenser' => $cond, 'residual_enclosures_kW' => [[0.0, 0.0], [0.0, 0.0]],
					'energy_balance' => ['compressor_cooling_kW' => 0.0, 'compressor_power_kW' => 0.0, 'required_condenser_heat_kW' => 0.0]];
				$result['diagnostics'][$key] = ['convergence' => ['status' => 'declared_inactive', 'evaluations' => 0], 'eligible' => false];
			} else {
				$singleRequest = (object)['stage_key' => $input->stage_key, 'operating_mode' => $input->operating_mode, 'evaporator' => $assigned[$key]['evaporator']['prepared']['inputs'], 'condenser' => $assigned[$key]['condenser']['prepared']['inputs']];
				$ownContexts = array_intersect_key($contexts, array_flip($circuit->compressor_instances));
				if($ownContexts) $singleRequest->compressor_contexts = (object)$ownContexts;
				$n = $models[$key]->data->numerics; $result['convergence']['maximum_evaluations'] += ($n->max_outer_iterations + 2) * $n->max_inner_iterations;
				$evaluation = $core->evaluateAssignedCircuit($machine, $singleRequest, $models[$key], $key, $resolve, $compressor);
				$result['diagnostics'][$key] = ['convergence' => $evaluation['convergence'], 'uniqueness' => $evaluation['uniqueness'], 'reasons' => $evaluation['reasons'], 'eligible' => false];
				$result['convergence']['evaluations'] += $evaluation['convergence']['evaluations'];
				if($evaluation['solution'] === null) { $result['status'] = $evaluation['status']; $result['convergence']['status'] = 'circuit_not_converged'; foreach($evaluation['reasons'] as $reason) $result['reasons'][] = $key.':'.$reason; return $result; }
				$solutions[$key] = $evaluation['solution'] + ['state' => 'active', 'eligible' => false];
				$compressors += $evaluation['solution']['compressor_stage']['compressors'];
			}
			$solutions[$key]['sections'] = ['evaporator' => ['instance_key' => $assigned[$key]['evaporator']['instance_key'], 'section_key' => $assigned[$key]['evaporator']['section_key']], 'condenser' => ['instance_key' => $assigned[$key]['condenser']['instance_key'], 'section_key' => $assigned[$key]['condenser']['section_key']]];
			foreach($circuit->compressor_instances as $instance) if(!isset($compressors[$instance])) $compressors[$instance] = ['instance_key' => $instance, 'circuit_key' => $key, 'active' => false, 'evaluation' => null];
		}
		$junctions = [];
		foreach($groups as $key => $group) {
			$contractGroup = $group['contract']; $branches = [];
			foreach($contractGroup->branches as $branch) {
				$c = $solutions[$branch->circuit_key]; $side = $contractGroup->side;
				$branches[$branch->section_key] = ['prepared' => $assigned[$branch->circuit_key][$side]['prepared'], 'result' => $c[$side], 'active' => $c['state'] === 'active',
					'outlet_error_bound_K' => $c['state'] === 'active' ? (float)$assigned[$branch->circuit_key][$side]['prepared']['section']->effectiveness * $c['temperature_error_bound_K'][$side === 'evaporator' ? 'Te' : 'Tc'] : 0.0,
					'compressor_heat_kW' => $c['energy_balance'][$side === 'evaporator' ? 'compressor_cooling_kW' : 'required_condenser_heat_kW'], 'residual_enclosure_kW' => $c['residual_enclosures_kW'][$side === 'evaporator' ? 0 : 1]];
			}
			$mix = $core->mixParallel($branches, $group['boundary']->inlet_degC, $contractGroup->total_volume_flow_m3_h, $contractGroup->side, $contract->numerics);
			if($mix['mathematical_status'] !== 'calculated') { $result['convergence']['status'] = 'junction_balance_not_converged'; $result['reasons'] = array_merge($result['reasons'], $mix['reasons']); return $result; }
			$mixed = json_encode($mix['mixed_outlet_degC'], JSON_THROW_ON_ERROR); $first = reset($branches);
			if(!$hx->inside($mixed, $contractGroup->limits->mixed_outlet) || !$hx->inside($mixed, $first['prepared']['medium']->properties->temperature_range)) { $result['status'] = 'unsuitable'; $result['convergence']['status'] = 'mixed_outlet_outside_limits'; $result['reasons'][] = 'mixed_outlet_outside_limits'; return $result; }
			$junctions[$key] = $group + ['balance' => $mix];
		}
		$totals = $core->sumThermalBalances($solutions, $contract->numerics);
		if($totals['mathematical_status'] !== 'calculated') { $result['convergence']['status'] = 'global_balance_not_converged'; $result['reasons'] = array_merge($result['reasons'], $totals['reasons']); return $result; }
		$result['convergence']['status'] = 'converged'; $result['convergence']['junction_passes'] = 1;
		$result['solution'] = ['scope' => 'synthetic_parallel_thermal_machine_only', 'eligible' => false, 'circuits' => $solutions, 'secondary_groups' => $junctions, 'thermal_totals' => $totals, 'compressors' => $compressors,
			'components' => $components, 'physical_component_count' => count($components)];
		$result['reasons'] = array_values(array_unique(array_merge($result['reasons'], $contract->missing_evidence)));
		if(isset($input->fluid_hydraulics)) $result['fluid_hydraulics'] = (new Items_Service_FluidThermalAudit())->evaluate($machine, $result, $input->fluid_hydraulics, $resolve);
		return $result;
	}

	private function dataset(callable $resolve, stdClass $ref, string $type): stdClass
	{
		$r = (new Items_Service_TechnicalSchema())->dataset($resolve('technicaldataset', $ref->dataset_key, $ref->revision));
		if($r->dataset_key !== $ref->dataset_key || $r->revision !== $ref->revision || $r->dataset_type !== $type) throw new InvalidArgumentException('Parallel thermal reference mismatch');
		return $r;
	}

	private function synthetic(stdClass $record): bool
	{
		return ($record->import_context->template_kind ?? null) === 'synthetic_thermal_test_v1' && ($record->import_context->production_import_allowed ?? null) === false;
	}
}
