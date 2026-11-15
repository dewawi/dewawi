<?php

class Items_Service_MachineOperatingMode
{
	public const VERSION = 'synthetic_operating_mode_enthalpy_balance_v1';

	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, callable $compressor, ?string $blocker = null): array
	{
		$s = new Items_Service_TechnicalSchema(); $machine = $s->dataset($machine);
		if($machine->dataset_type !== 'machine_configuration') throw new InvalidArgumentException('Operating machine required');
		if(($machine->data->configuration_version ?? 1) === 1 && ($request->operating_mode ?? '') === 'cooling') {
			$result = (new Items_Service_ThermalMachine())->evaluate($machine, $request, $resolve, $compressor, $blocker); $result['fully_coupled'] = false; return $result;
		}
		$input = $s->fields($request, ['operating_mode' => Items_Service_TechnicalSchema::OPERATING_MODES, 'stage_key' => 'key', 'point' => 'object', 'valve_states' => 'list:object'], ['compressor_contexts' => 'object', 'minimum_hot_outlet_degC' => 'number', 'hydraulics' => 'object', 'require_fully_coupled' => 'bool']);
		$input->point = (new Items_Service_CompressorPolynomial())->point($input->point);
		$states = []; foreach($input->valve_states as &$state) { $state = $s->fields($state, ['instance_key' => 'key', 'state_key' => 'key']); if(isset($states[$state->instance_key])) throw new InvalidArgumentException('Contradictory requested valve states'); $states[$state->instance_key] = $state->state_key; }
		unset($state);
		$result = ['result_version' => 1, 'calculation_version' => self::VERSION, 'scope' => 'synthetic_declared_operating_point_energy_balance_only', 'status' => 'review_required', 'eligible' => false, 'fully_coupled' => false,
			'operating_mode' => $input->operating_mode, 'stage_key' => $input->stage_key, 'machine_revision' => $this->identity($machine), 'original_request' => json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR), 'model' => null, 'solution' => null,
			'mathematical_status' => 'blocked', 'hydraulics' => ['status' => 'missing', 'result' => null], 'reasons' => ['synthetic_mode_only', 'machine_physics_not_released', 'thermohydraulic_feedback_not_solved']];
		if($blocker !== null) { $result['reasons'][] = $blocker; return $result; }
		if(($machine->data->configuration_version ?? 1) !== 2) { $result['reasons'][] = 'versioned_operating_topology_required'; return $result; }
		try {
			$contracts = (new Items_Service_OperatingModeSchema())->machineBinding($machine->data, $resolve);
			if(!isset($contracts[$input->operating_mode])) throw new DomainException('operating_mode_not_available');
			$model = $contracts[$input->operating_mode]; $d = $model->data; $result['model'] = $model;
			if(!$this->synthetic($machine) || !$this->synthetic($model)) throw new DomainException('real_use_of_synthetic_operating_model_forbidden');
			$expected = []; foreach($d->valve_states as $state) $expected[$state->instance_key] = $state->state_key; ksort($expected); ksort($states);
			if($expected !== $states) throw new DomainException('requested_valve_state_mismatch');
			$points = array_values(array_filter($d->points, fn($p) => $p->stage_key === $input->stage_key && $p->Te_degC === $input->point->Te_degC && $p->Tc_degC === $input->point->Tc_degC));
			if(count($points) !== 1) throw new DomainException('operating_point_not_tabulated'); $point = $points[0];
			$binding = (new Items_Service_OperatingModeSchema())->binding($machine->data, $d, $resolve); $h = new Items_Service_FluidHydraulics();
			foreach(array_merge(array_values($binding['specs']), [$binding['boundary']]) as $record) if(!$this->synthetic($record)) throw new DomainException('synthetic_operating_component_provenance_required');
			$hx = new Items_Service_HeatExchangerMap();
			foreach(['Te','Tc'] as $axis) if(!$hx->inside($input->point->{$axis.'_degC'}, $binding['boundary']->data->search_bounds->$axis)) throw new DomainException('operating_point_outside_energy_boundary');
			$stageRequest = (object)['stage_key' => $input->stage_key, 'operating_mode' => $input->operating_mode, 'circuit_points' => [(object)['circuit_key' => $d->circuit_key, 'point' => $input->point]]];
			if(isset($input->compressor_contexts)) $stageRequest->compressor_contexts = $input->compressor_contexts;
			$stage = (new Items_Service_MachineStage())->evaluateOperatingCircuit($machine, $d, $stageRequest, $compressor); $sums = $stage['stage_compressor_sums'];
			foreach(['cooling_capacity','power_input','refrigerant_mass_flow'] as $q) if($sums[$q]['value'] === null) throw new DomainException('compressor_operating_point_or_mass_flow_unavailable');
			if($stage['violations']) throw new DomainException('compressor_operating_limits_violated');
			$qCold = $sums['cooling_capacity']['value']; $power = $sums['power_input']['value']; $mass = $sums['refrigerant_mass_flow']['value'];
			$exchangers = [];
			foreach(['evaporator','condenser','recovery'] as $side) if($d->$side !== null) {
				$assignment = $d->$side; $spec = $binding['specs'][$assignment->instance_key]; $ref = $spec->data->specification->thermal_map_ref;
				if($ref === null) throw new DomainException('operating_exchanger_map_missing');
				$map = $h->resolve($resolve, $ref, 'heat_exchanger_map'); $s->exchangerBinding($spec->data, $map->data);
				if($map->data->contract_version !== 2 || !$this->synthetic($spec) || !$this->synthetic($map)) throw new DomainException('synthetic_operating_exchanger_points_required');
				$exchangers[$side] = $this->exchanger($map, $assignment, $side, $point, $input, $mass, $d->numerics, $resolve);
				$loop = array_values(array_filter($machine->data->hydraulic_loops, fn($l) => $l->loop_key === $assignment->loop_key))[0];
				$flow = (float)$exchangers[$side]['point']->secondary->volume_flow_m3_h;
				foreach([$spec->data->specification, $loop] as $limits) if((isset($limits->flow_min_m3_h) && $flow < (float)$limits->flow_min_m3_h) || (isset($limits->flow_max_m3_h) && $flow > (float)$limits->flow_max_m3_h)) throw new DomainException('operating_secondary_component_or_loop_flow_limit');
			}
			$e = $exchangers['evaporator']['point']; $c = $exchangers['condenser']['point']; $r = $exchangers['recovery']['point'] ?? null;
			$first = $r ?? $c;
			if($r !== null) $this->sameState($r->outlet_state, $c->inlet_state);
			if(Items_Service_TechnicalJson::compareDecimal($c->outlet_state->enthalpy_kJ_kg, $e->inlet_state->enthalpy_kJ_kg) !== 0) throw new DomainException('expansion_enthalpy_balance_violated');
			if((float)$c->inlet_state->pressure_kPa <= (float)$e->outlet_state->pressure_kPa) throw new DomainException('refrigerant_pressure_order_invalid');
			$this->compressorConditions($stage, $e, $c, $first);
			$qRecovery = $r === null ? 0.0 : (float)$r->heat_flow_kW;
			$qRemaining = in_array($input->operating_mode, ['full_heat_recovery','heat_pump'], true) ? 0.0 : (float)$c->heat_flow_kW;
			$hot = (float)$c->heat_flow_kW + $qRecovery;
			$useful = $input->operating_mode === 'cooling' ? 0.0 : ($input->operating_mode === 'partial_heat_recovery' ? $qRecovery : (float)$c->heat_flow_kW);
			$work = $mass * ((float)$first->inlet_state->enthalpy_kJ_kg - (float)$e->outlet_state->enthalpy_kJ_kg);
			$roundoff = 128 * PHP_FLOAT_EPSILON * max(1.0, abs($qCold) + abs($power) + abs($hot) + $mass * (abs((float)$first->inlet_state->enthalpy_kJ_kg) + abs((float)$e->outlet_state->enthalpy_kJ_kg)));
			if(!is_finite($roundoff) || $roundoff > (float)$d->numerics->heat_tolerance_kW || $work <= 0) throw new DomainException('operating_balance_numeric_precision_unconfirmed');
			$residuals = ['evaporator_kW' => $qCold - (float)$e->heat_flow_kW, 'compressor_work_kW' => $power - $work,
				'energy_kW' => $qCold + $power - $hot, 'mass_kg_s' => max(array_map(fn($x) => abs((float)$x['point']->mass_flow_kg_s - $mass), $exchangers))];
			foreach($residuals as $key => $value) if(abs($value) + (str_ends_with($key, 'kg_s') ? 0.0 : $roundoff) > (float)$d->numerics->{str_ends_with($key, 'kg_s') ? 'mass_tolerance_kg_s' : 'heat_tolerance_kW'}) throw new DomainException('operating_energy_or_mass_balance_violated');
			$hotPoint = $input->operating_mode === 'partial_heat_recovery' ? $r : $c;
			if(isset($input->minimum_hot_outlet_degC) && ($useful <= 0 || (float)$hotPoint->secondary->outlet_degC < (float)$input->minimum_hot_outlet_degC)) throw new DomainException('required_hot_water_temperature_not_reachable');
			$electrical = $this->electrical($point->power_boundary, $power);
			if(isset($input->hydraulics)) {
				$result['hydraulics'] = $this->hydraulics($machine, $d, $point, $exchangers, $input->hydraulics, $resolve);
				if($result['hydraulics']['status'] !== 'checked_at_declared_states') throw new DomainException('mode_hydraulic_evidence_incomplete');
			} else $result['reasons'][] = 'hydraulic_evidence_missing';
			if($input->require_fully_coupled ?? false) throw new DomainException('fully_coupled_operating_mode_not_supported');
			$copCooling = $electrical['complete'] && $input->operating_mode !== 'heat_pump' ? $qCold / $electrical['total_kW'] : null;
			$copHeating = $electrical['complete'] && in_array($input->operating_mode, ['heat_pump','full_heat_recovery'], true) ? $useful / $electrical['total_kW'] : null;
			if(!$electrical['complete']) $result['reasons'][] = 'electrical_boundary_incomplete';
			foreach([$copCooling, $copHeating] as $cop) if($cop !== null && (!is_finite($cop) || $cop <= 0)) throw new DomainException('operating_cop_numeric_range_invalid');
			$result['solution'] = ['eligible' => false, 'fully_coupled' => false, 'circuit_key' => $d->circuit_key, 'compressor_stage' => $stage, 'exchangers' => $exchangers,
				'energy_boundary' => $binding['boundary'], 'evaporator_heat_kW' => $qCold, 'compressor_input_kW' => $power, 'recovery_heat_kW' => $input->operating_mode === 'full_heat_recovery' ? $useful : $qRecovery,
				'remaining_rejected_heat_kW' => $qRemaining, 'total_hot_side_heat_kW' => $hot, 'useful_heat_at_declared_hx_boundary_kW' => $useful, 'electrical_boundary' => $electrical,
				'cop_boundary' => 'synthetic_hx_heat_all_declared_electrical_inputs_only', 'cooling_cop' => $copCooling, 'heating_cop' => $copHeating, 'roundoff_allowance_kW' => $roundoff, 'residuals' => $residuals];
			(new Items_Service_FluidProperties())->finite($result['solution']); $result['mathematical_status'] = 'calculated';
			$result['reasons'] = array_values(array_unique(array_merge($result['reasons'], $d->missing_evidence, ['real_oem_mode_release_missing'])));
		} catch(DomainException $e) { $result['solution'] = null; $result['reasons'][] = $e->getMessage(); }
		return $result;
	}

	private function exchanger(stdClass $record, stdClass $assignment, string $side, stdClass $point, stdClass $input, float $mass, stdClass $numerics, callable $resolve): array
	{
		$d = $record->data; $h = new Items_Service_FluidHydraulics(); $f = new Items_Service_FluidProperties(); $hx = new Items_Service_HeatExchangerMap();
		$sections = array_values(array_filter($d->sections, fn($s) => $s->section_key === $assignment->section_key));
		if(count($sections) !== 1) throw new DomainException('operating_exchanger_section_missing'); $section = $sections[0];
		$points = array_values(array_filter($section->points, fn($p) => $p->point_key === $point->point_key && $p->mode === $input->operating_mode && $p->stage_key === $input->stage_key && $p->Te_degC === $input->point->Te_degC && $p->Tc_degC === $input->point->Tc_degC));
		if(count($points) !== 1) throw new DomainException('operating_exchanger_point_missing'); $p = $points[0];
		if($p->inlet_state === null || $p->outlet_state === null) throw new DomainException('refrigerant_state_evidence_missing');
		if($p->operating_role !== ($side === 'recovery' ? 'desuperheater' : $side)) throw new DomainException('exchanger_operating_role_mismatch');
		foreach(['Te' => $p->Te_degC, 'Tc' => $p->Tc_degC, 'secondary_inlet' => $p->secondary->inlet_degC, 'secondary_outlet' => $p->secondary->outlet_degC, 'volume_flow' => $p->secondary->volume_flow_m3_h] as $key => $value) if(!$hx->inside($value, $section->limits->$key)) throw new DomainException('operating_exchanger_limits_violated');
		foreach([$p->inlet_state, $p->outlet_state] as $state) if(!$hx->inside($state->temperature_degC, $section->limits->refrigerant_temperature) || !$hx->inside($state->pressure_kPa, $section->limits->refrigerant_pressure)) throw new DomainException('refrigerant_state_limits_violated');
		if(abs((float)$p->inlet_state->pressure_kPa - (float)$p->outlet_state->pressure_kPa) > 1e-8) throw new DomainException('synthetic_exchanger_pressure_boundary_violated');
		$evap = $p->operating_role === 'evaporator';
		if(($evap && (abs((float)$p->inlet_state->temperature_degC - (float)$p->Te_degC) > 1e-8 || (float)$p->outlet_state->temperature_degC < (float)$p->Te_degC || (float)$p->outlet_state->temperature_degC >= (float)$p->secondary->inlet_degC)) || ($p->operating_role === 'condenser' && ((float)$p->inlet_state->temperature_degC <= (float)$p->Tc_degC || (float)$p->outlet_state->temperature_degC <= (float)$p->secondary->inlet_degC))) throw new DomainException('refrigerant_secondary_temperature_approach_invalid');
		if(($evap && ($p->inlet_state->phase !== 'two_phase' || $p->outlet_state->phase !== 'superheated_vapor' || (float)$p->secondary->inlet_degC <= (float)$p->secondary->outlet_degC || (float)$p->secondary->outlet_degC <= (float)$p->Te_degC)) || (!$evap && ((float)$p->secondary->outlet_degC <= (float)$p->secondary->inlet_degC || ($p->operating_role === 'condenser' && (float)$p->secondary->outlet_degC >= (float)$p->Tc_degC)))) throw new DomainException('operating_exchanger_heat_direction_invalid');
		if($p->operating_role === 'desuperheater' && ($p->inlet_state->phase !== 'superheated_vapor' || $p->outlet_state->phase !== 'superheated_vapor' || (float)$p->outlet_state->temperature_degC <= (float)$p->Tc_degC || (float)$p->inlet_state->temperature_degC <= (float)$p->outlet_state->temperature_degC || (float)$p->secondary->outlet_degC >= (float)$p->inlet_state->temperature_degC || (float)$p->secondary->inlet_degC >= (float)$p->outlet_state->temperature_degC)) throw new DomainException('desuperheating_state_evidence_invalid');
		if($p->operating_role === 'condenser' && ($p->inlet_state->phase !== 'superheated_vapor' || $p->outlet_state->phase !== 'liquid')) throw new DomainException('condensing_state_evidence_invalid');
		$heat = (float)$p->mass_flow_kg_s * ((float)$p->outlet_state->enthalpy_kJ_kg - (float)$p->inlet_state->enthalpy_kJ_kg) * ($evap ? 1 : -1);
		$fluid = $h->resolve($resolve, $d->fluid_ref, 'fluid_properties'); if(!$this->synthetic($fluid)) throw new DomainException('synthetic_fluid_evidence_required');
		$thermal = $f->evaluate($fluid, (object)['operation' => 'heat', 'composition' => $d->fluid_composition, 'inlet_degC' => $p->secondary->inlet_degC, 'outlet_degC' => $p->secondary->outlet_degC, 'flow' => (object)['value' => $p->secondary->volume_flow_m3_h, 'unit' => 'm3/h', 'at_degC' => $p->secondary->inlet_degC]]);
		if($thermal['calculation'] === null) throw new DomainException('secondary_fluid_heat_or_freezing_evidence_invalid');
		$secondary = $thermal['calculation']['heat_W'] / 1000 * ($evap ? -1 : 1);
		$roundoff = 64 * PHP_FLOAT_EPSILON * max(1.0, (float)$p->mass_flow_kg_s * (abs((float)$p->inlet_state->enthalpy_kJ_kg) + abs((float)$p->outlet_state->enthalpy_kJ_kg)), abs($secondary));
		if(!is_finite($roundoff) || $roundoff > (float)$numerics->heat_tolerance_kW || $heat <= 0 || $secondary <= 0) throw new DomainException('exchanger_heat_numeric_precision_unconfirmed');
		if(abs((float)$p->mass_flow_kg_s - $mass) > (float)$numerics->mass_tolerance_kg_s || abs($heat - (float)$p->heat_flow_kW) + $roundoff > (float)$numerics->heat_tolerance_kW || abs($secondary - (float)$p->heat_flow_kW) + $roundoff > (float)$numerics->heat_tolerance_kW) throw new DomainException('exchanger_refrigerant_secondary_balance_violated');
		return ['instance_key' => $assignment->instance_key, 'section_key' => $assignment->section_key, 'loop_key' => $assignment->loop_key, 'map' => $this->identity($record), 'fluid' => $fluid, 'point' => $p, 'secondary_balance' => $thermal['calculation'], 'heat_residual_kW' => $heat - $secondary, 'missing_evidence' => $d->missing_evidence];
	}

	private function compressorConditions(array $stage, stdClass $evap, stdClass $cond, stdClass $firstHot): void
	{
		$discharge = []; $active = 0;
		foreach($stage['compressors'] as $compressor) if($compressor['active']) {
			$active++; $temperature = $compressor['evaluation']['calculation']['quantities']['discharge_temperature'] ?? null;
			if(($temperature['status'] ?? null) === 'calculated') $discharge[] = $temperature['value'];
			$e = $compressor['evaluation']; $c = $e['conditions'];
			if($e['temperature_basis']->Te !== 'pure_saturation' || $e['temperature_basis']->Tc !== 'pure_saturation') throw new DomainException('synthetic_mode_state_temperature_basis_not_supported');
			$suction = $c->suction_condition_mode === 'superheat' ? (float)$evap->Te_degC + (float)$c->superheat_K : (float)$c->return_gas_temperature_degC;
			if(abs((float)$evap->outlet_state->temperature_degC - $suction) > 1e-8 || abs((float)$cond->outlet_state->temperature_degC - ((float)$cond->Tc_degC - (float)$c->subcooling_K)) > 1e-8) throw new DomainException('refrigerant_states_contradict_compressor_reference_conditions');
		}
		if(count($discharge) === $active && ((float)$firstHot->inlet_state->temperature_degC < min($discharge) - 1e-8 || (float)$firstHot->inlet_state->temperature_degC > max($discharge) + 1e-8)) throw new DomainException('refrigerant_discharge_temperature_contradiction');
	}

	private function sameState(stdClass $a, stdClass $b): void
	{
		foreach(['temperature_degC', 'pressure_kPa', 'enthalpy_kJ_kg'] as $key) if(abs((float)$a->$key - (float)$b->$key) > 1e-8) throw new DomainException('serial_refrigerant_state_discontinuity');
		if($a->phase !== $b->phase) throw new DomainException('serial_refrigerant_phase_discontinuity');
	}

	private function electrical(stdClass $boundary, float $compressor): array
	{
		$complete = $boundary->external_auxiliary_kW !== null; $sum = $complete ? (float)$boundary->external_auxiliary_kW : 0.0;
		foreach($boundary->inputs as $p) { if($p->electrical_power_kW === null) $complete = false; else $sum += (float)$p->electrical_power_kW; }
		return ['scope' => $boundary->model_id, 'complete' => $complete, 'compressor_kW' => $compressor, 'auxiliary_and_standby_kW' => $complete ? $sum : null, 'total_kW' => $complete ? $compressor + $sum : null, 'source' => $boundary];
	}

	private function hydraulics(stdClass $machine, stdClass $model, stdClass $point, array $exchangers, stdClass $request, callable $resolve): array
	{
		$h = new Items_Service_FluidHydraulics(); $schema = new Items_Service_TechnicalSchema(); $request = $schema->fields($request, ['loops' => 'list:object']);
		$inputs = []; foreach($request->loops as $loop) { if(!isset($loop->loop_key) || isset($inputs[$loop->loop_key])) throw new InvalidArgumentException('Invalid mode hydraulic loop request'); $inputs[$loop->loop_key] = $loop; }
		foreach($exchangers as $x) {
			$loop = array_values(array_filter($machine->data->hydraulic_loops, fn($l) => $l->loop_key === $x['loop_key']))[0]; $input = $inputs[$x['loop_key']] ?? null; $p = $x['point'];
			if($input === null || $loop->fluid_dataset === null || $loop->fluid_dataset->dataset_key !== $x['fluid']->dataset_key || $loop->fluid_dataset->revision !== $x['fluid']->revision) throw new DomainException('mode_hydraulic_fluid_revision_mismatch');
			$state = array_values(array_filter($input->component_states ?? [], fn($s) => $s->instance_key === $x['instance_key']));
			if(count($state) !== 1 || count($state[0]->sections) !== 1 || $state[0]->sections[0]->section_key !== $x['section_key'] || Items_Service_TechnicalJson::compareDecimal($state[0]->sections[0]->temperature_degC, $p->secondary->inlet_degC) !== 0 || Items_Service_TechnicalJson::compareDecimal($state[0]->sections[0]->volume_flow_m3_h, $p->secondary->volume_flow_m3_h) !== 0) throw new DomainException('mode_hydraulic_flow_or_temperature_mismatch');
			$mapComposition = $x['secondary_balance']['inlet']['composition']; $composition = (new Items_Service_FluidProperties())->composition($x['fluid']->data, $input->composition);
			if(Items_Service_TechnicalJson::encode($composition) !== Items_Service_TechnicalJson::encode($mapComposition)) throw new DomainException('mode_hydraulic_composition_mismatch');
		}
		$r = $h->evaluate($machine, $request, $resolve); if($r['solution'] === null) return ['status' => 'blocked', 'result' => $r];
		foreach($exchangers as $x) {
			$loop = array_values(array_filter($machine->data->hydraulic_loops, fn($l) => $l->loop_key === $x['loop_key']))[0]; $path = $h->resolve($resolve, $loop->pressure_loss_dataset, 'hydraulic_path');
			(new Items_Service_FluidThermalAudit())->temperatures($machine->data, $loop, $inputs[$loop->loop_key], $path->data, $x['instance_key'], (float)$x['point']->secondary->inlet_degC, (float)$x['point']->secondary->outlet_degC);
			if($path->data->pump_instance !== null) {
				$power = $r['solution']['loops'][$loop->loop_key]['pump']['electrical_input_W'];
				$declared = array_values(array_filter($point->power_boundary->inputs, fn($p) => $p->instance_key === $path->data->pump_instance));
				if($power === null || count($declared) !== 1 || $declared[0]->electrical_power_kW === null || abs($power / 1000 - (float)$declared[0]->electrical_power_kW) > (float)$model->numerics->heat_tolerance_kW) throw new DomainException('mode_hydraulic_pump_power_boundary_mismatch');
			}
		}
		return ['status' => 'checked_at_declared_states', 'fully_coupled' => false, 'result' => $r];
	}

	private function identity(stdClass $record): array { return ['dataset_key' => $record->dataset_key, 'revision' => $record->revision, 'dataset_type' => $record->dataset_type]; }
	private function synthetic(stdClass $record): bool { return ($record->import_context->production_import_allowed ?? null) === false; }
}
