<?php

class Items_Service_ThermohydraulicCircuit
{
	public const VERSION = 'synthetic_two_liquid_loops_interval_bisection_v1';
	private Items_Service_ThermalCircuit $interval;
	private Items_Service_FluidProperties $fluid;
	private Items_Service_FluidHydraulics $hydraulic;
	public function __construct() { $this->interval = new Items_Service_ThermalCircuit(); $this->fluid = new Items_Service_FluidProperties(); $this->hydraulic = new Items_Service_FluidHydraulics(); }

	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, callable $compressor, ?string $blocker = null): array
	{
		$s = new Items_Service_TechnicalSchema(); $machine = $s->dataset($machine);
		$q = $s->fields($request, ['operating_mode' => Items_Service_TechnicalSchema::OPERATING_MODES, 'stage_key' => 'key', 'valve_states' => 'list:object', 'secondary_boundaries' => 'object'], ['compressor_contexts' => 'object']);
		$q->secondary_boundaries = $s->fields($q->secondary_boundaries, ['evaporator' => 'object', 'condenser' => 'object']);
		foreach($q->secondary_boundaries as &$boundary) $boundary = $s->fields($boundary, ['inlet_degC' => 'number']); unset($boundary);
		foreach($q->valve_states as &$state) $state = $s->fields($state, ['instance_key' => 'key', 'state_key' => 'key']); unset($state);
		$r = ['result_version' => 1, 'calculation_version' => self::VERSION, 'scope' => 'synthetic_two_liquid_loops_declared_thermostatted_boundary_only', 'status' => 'review_required', 'eligible' => false, 'fully_coupled' => false, 'mathematical_converged' => false,
			'original_request' => json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR), 'operating_mode' => $q->operating_mode, 'stage_key' => $q->stage_key, 'machine_revision' => ['dataset_key' => $machine->dataset_key, 'revision' => $machine->revision], 'model' => null, 'solution' => null,
			'convergence' => [], 'assumptions' => ['constant_compressor_coefficients_certified', 'passive_components_adiabatic_no_storage', 'auxiliary_and_standby_heat_outside_refrigerant_and_secondary_streams', 'pump_and_dissipation_heat_removed_by_external_thermostats', 'pressure_tables_for_exact_fluid_revision_no_viscosity_correction', 'no_refrigerant_state_function'], 'reasons' => ['synthetic_model_only', 'real_machine_release_missing', 'refrigerant_state_and_control_release_missing', 'system_efficiency_not_available']];
		if($blocker !== null) { $r['reasons'][] = $blocker; return $r; }
		try {
			if($machine->dataset_type !== 'machine_configuration' || ($machine->data->configuration_version ?? 1) !== 2 || $machine->data->circuit_count !== 1 || $machine->data->air_paths) throw new DomainException('coupled_single_liquid_circuit_required');
			if(!in_array($q->operating_mode, ['cooling', 'heat_pump'], true)) throw new DomainException('recovery_coupling_not_supported');
			$resolveSynthetic = function($table, $key, $revision) use($resolve) { $v = $resolve($table, $key, $revision); if(isset($v->dataset_type) && ($v->import_context->production_import_allowed ?? null) !== false) throw new DomainException('coupled_synthetic_provenance_required'); return $v; };
			if(($machine->import_context->production_import_allowed ?? null) !== false) throw new DomainException('coupled_synthetic_provenance_required');
			$contracts = (new Items_Service_OperatingModeSchema())->machineBinding($machine->data, $resolveSynthetic); $model = $contracts[$q->operating_mode] ?? null;
			if($model === null || $model->data->contract_version !== 2) throw new DomainException('coupled_operating_model_required'); $d = $model->data; $r['model'] = $model;
			if(!in_array($q->stage_key, $d->stage_keys, true)) throw new DomainException('coupled_stage_unavailable');
			$states = function($list) { $a = []; foreach($list as $v) { if(isset($a[$v->instance_key])) throw new InvalidArgumentException('Conflicting coupled valve states'); $a[$v->instance_key] = $v->state_key; } ksort($a); return $a; };
			if($states($q->valve_states) !== $states($d->valve_states)) throw new DomainException('coupled_valve_state_mismatch');
			$binding = (new Items_Service_OperatingModeSchema())->binding($machine->data, $d, $resolveSynthetic); $bounds = $binding['boundary']->data->search_bounds;
			$stage = array_values(array_filter($machine->data->stages, fn($v) => $v->stage_key === $q->stage_key))[0]; $sourceHeat = ['cooling_capacity' => [0.0,0.0], 'power_input' => [0.0,0.0]];
			foreach($stage->active_compressors as $key) {
				$c = $binding['components'][$key]; $profile = $s->profile($resolveSynthetic('itemtechnicalprofile', $c->profile_key, $c->profile_revision));
				if($profile->profile_key !== $c->profile_key || $profile->revision !== $c->profile_revision || $profile->item_ref !== $c->component_key || $profile->role !== 'compressor') throw new InvalidArgumentException('Coupled compressor profile identity mismatch');
				$curve = $s->dataset($resolveSynthetic('technicaldataset', $profile->dataset_ref->dataset_key, $profile->dataset_ref->revision));
				if($curve->dataset_type === 'component_spec') { $ref = $curve->data->specification->polynomial_ref; if($ref === null) throw new DomainException('coupled_compressor_curve_missing'); $curve = $this->hydraulic->resolve($resolveSynthetic, $ref, 'compressor_polynomial'); }
				if($curve->dataset_type !== 'compressor_polynomial' || $curve->data->refrigerant !== $machine->data->refrigerant || $curve->data->temperature_basis->Te !== 'pure_saturation' || $curve->data->temperature_basis->Tc !== 'pure_saturation') throw new DomainException('coupled_compressor_basis_not_supported');
				foreach(['cooling_capacity', 'power_input'] as $quantity) if(!isset($curve->data->quantities->$quantity) || array_filter(array_slice($curve->data->quantities->$quantity->coefficients_C0_to_C9, 1), fn($v) => $v !== '0')) throw new DomainException('coupled_constant_compressor_certificate_required');
				foreach(array_keys($sourceHeat) as $quantity) $sourceHeat[$quantity] = $this->interval->add($sourceHeat[$quantity], $this->interval->polynomialBounds($curve->data->quantities->$quantity, [(float)$bounds->Te->min,(float)$bounds->Te->max], [(float)$bounds->Tc->min,(float)$bounds->Tc->max], false)[0]);
				$envelope = $curve->data->operating_envelope; if($envelope === null || count($envelope->vertices) !== 4 || count(array_unique(array_column($envelope->vertices, 'Te'))) !== 2 || count(array_unique(array_column($envelope->vertices, 'Tc'))) !== 2) throw new DomainException('coupled_rectangular_compressor_envelope_required');
				foreach(['min','max'] as $te) foreach(['min','max'] as $tc) if((new Items_Service_CompressorPolynomial())->operatingRegion($envelope, $this->point((float)$bounds->Te->$te, (float)$bounds->Tc->$tc))['status'] !== 'inside') throw new DomainException('coupled_search_outside_compressor_envelope');
			}
			$compress = function($point) use($machine,$d,$q,$compressor) { $input = (object)['operating_mode' => $q->operating_mode, 'stage_key' => $q->stage_key, 'circuit_points' => [(object)['circuit_key' => $d->circuit_key, 'point' => $point]]]; if(isset($q->compressor_contexts)) $input->compressor_contexts = $q->compressor_contexts; $v = (new Items_Service_MachineStage())->evaluateOperatingCircuit($machine, $d, $input, $compressor); if($v['violations'] || $v['stage_compressor_sums']['cooling_capacity']['value'] === null || $v['stage_compressor_sums']['power_input']['value'] === null) throw new DomainException('coupled_compressor_conditions_or_limits'); return $v; };
			$initial = $compress($this->point(((float)$bounds->Te->min+(float)$bounds->Te->max)/2, ((float)$bounds->Tc->min+(float)$bounds->Tc->max)/2));
			$cold = $initial['stage_compressor_sums']['cooling_capacity']['value']; $power = $initial['stage_compressor_sums']['power_input']['value'];
			if(!is_finite($cold+$power) || min($cold,$power) <= 0) throw new DomainException('coupled_positive_compressor_heat_required');
			$sides = [];
			foreach(['evaporator' => 'Te', 'condenser' => 'Tc'] as $side => $axis) {
				$sides[$side] = $this->prepare($machine, $d->$side, $binding, $q->secondary_boundaries->$side->inlet_degC, $bounds->$axis, $side === 'evaporator' ? $cold : $cold+$power, $side === 'evaporator' ? $sourceHeat['cooling_capacity'] : $this->interval->add($sourceHeat['cooling_capacity'],$sourceHeat['power_input']), $side, $resolveSynthetic);
				$solved = $this->solve($sides[$side], $d->numerics, $r['convergence'][$side]);
				if($solved === null) throw new DomainException('coupled_solution_not_certified_or_converged'); $solutions[$side] = $solved;
			}
			$point = $this->point($solutions['evaporator']['saturation_degC'], $solutions['condenser']['saturation_degC']); $final = $compress($point);
			$thermal = ['evaporator_kW' => $final['stage_compressor_sums']['cooling_capacity']['value'] - $solutions['evaporator']['heat_flow_kW'], 'condenser_kW' => $final['stage_compressor_sums']['cooling_capacity']['value'] + $final['stage_compressor_sums']['power_input']['value'] - $solutions['condenser']['heat_flow_kW']];
			$thermal['refrigerant_energy_kW'] = $solutions['evaporator']['heat_flow_kW'] + $power - $solutions['condenser']['heat_flow_kW'];
			foreach($thermal as $residual) if(abs($residual) > (float)$d->numerics->heat_tolerance_kW) throw new DomainException('coupled_final_energy_residual');
			$hydraulicRequest = (object)['loops' => array_column($solutions, 'hydraulic_input')]; $hydraulics = $this->hydraulic->evaluate($machine, $hydraulicRequest, $resolveSynthetic);
			if($hydraulics['solution'] === null) throw new DomainException('coupled_final_hydraulics_missing');
			foreach($solutions as $side => $value) {
				$loop = $sides[$side]['loop']; $pump = $hydraulics['solution']['loops'][$loop->loop_key]['pump'];
				if($pump === null || $pump['electrical_input_W'] === null || abs($pump['reserve_Pa']) > (float)$d->numerics->pressure_tolerance_Pa) throw new DomainException('coupled_final_pressure_or_pump_power');
				(new Items_Service_FluidThermalAudit())->temperatures($machine->data, $loop, $value['hydraulic_input'], $sides[$side]['path']->data, $d->$side->instance_key, (float)$sides[$side]['inlet'], $value['outlet_degC']);
			}
			$r['solution'] = ['point' => $point, 'compressor_stage' => $final, 'evaporator_heat_kW' => $cold, 'condenser_heat_kW' => $cold+$power, 'compressor_input_kW' => $power, 'useful_heat_kW' => $q->operating_mode === 'heat_pump' ? $cold+$power : 0.0, 'secondary_sides' => $solutions, 'hydraulics' => $hydraulics['solution'], 'thermal_residuals_kW' => $thermal,
				'energy_boundary' => $binding['boundary'], 'secondary_heat_boundary' => $d->coupling, 'pump_heat_removed_to_external_thermostats_W' => array_sum(array_map(fn($v) => $v['pump']['electrical_input_W'], $hydraulics['solution']['loops'])), 'eligible' => false, 'fully_coupled' => true];
			$this->fluid->finite($r['solution']); $r['mathematical_converged'] = true; $r['fully_coupled'] = true; $r['reasons'] = array_values(array_unique(array_merge($r['reasons'], $d->missing_evidence)));
		} catch(DomainException|OverflowException $e) { $r['reasons'][] = $e->getMessage(); $r['solution'] = null; }
		return $r;
	}

	private function prepare(stdClass $machine, stdClass $assignment, array $binding, string $inlet, stdClass $bounds, float $heat, array $heatBox, string $side, callable $resolve): array
	{
		$h = $this->hydraulic; $spec = $binding['specs'][$assignment->instance_key]; $ref = $spec->data->specification->thermal_map_ref;
		if($ref === null) throw new DomainException('coupled_exchanger_missing'); $map = $h->resolve($resolve,$ref,'heat_exchanger_map');
		if($map->data->contract_version !== 3 || count($map->data->sections) !== 1) throw new DomainException('coupled_fluid_exchanger_required');
		$loop = array_values(array_filter($machine->data->hydraulic_loops, fn($v) => $v->loop_key === $assignment->loop_key))[0];
		if(count($machine->data->hydraulic_loops) !== 2 || $loop->fluid_dataset === null || $loop->pressure_loss_dataset === null || $loop->tank_kind === 'open') throw new DomainException('coupled_two_complete_closed_loops_required');
		$fluid = $h->resolve($resolve, $loop->fluid_dataset, 'fluid_properties'); $h->sameFluid($map->data->fluid_ref,$fluid); $composition = $this->fluid->composition($fluid->data,$map->data->fluid_composition);
		$path = $h->resolve($resolve,$loop->pressure_loss_dataset,'hydraulic_path'); $h->pathBinding($machine->data,$loop,$path);
		if($path->data->contract_version !== 1 || $path->data->pump_instance === null || $path->data->pipe_segments || $path->data->missing_evidence || $path->data->external_loss_ref === null) throw new DomainException('coupled_serial_path_or_loss_evidence_missing');
		$losses = []; $locations = []; $edges = []; foreach($loop->edges as $edge) $edges[$edge[0]] = $edge[1]; $cursor = $loop->boundary_in; $after = false;
		while(($next = $edges[$cursor]) !== $loop->boundary_out) {
			$key = substr($next,0,strrpos($next,'.')); $component = $binding['components'][$key]; $locations[$key] = $after;
			if($component->role !== 'pump') {
				$p = $binding['specs'][$key]; $lossRef = $p->data->specification->pressure_loss_ref ?? null; if($lossRef === null) throw new DomainException('coupled_component_pressure_loss_missing');
				$loss = $h->resolve($resolve,$lossRef,'pressure_loss_map'); $h->mapBinding($p,$loss); $h->sameFluid($loss->data->fluid_ref,$fluid);
				if(count($loss->data->sections) !== 1 || $loss->data->missing_evidence) throw new DomainException('coupled_single_section_pressure_evidence_required'); $losses[$key] = $loss;
				if($key !== $assignment->instance_key && $component->role !== 'tank') throw new DomainException('coupled_additional_thermal_component_unsupported');
			}
			if($key === $assignment->instance_key) $after = true;
			foreach($component->ports as $port => $kind) if($kind === 'liquid_out') $cursor = $key.'.'.$port;
		}
		$pumpSpec = $binding['specs'][$path->data->pump_instance]; $p = $pumpSpec->data->specification;
		if($p->curve_ref === null || $p->fluid_ref === null || $p->curve_definition !== 'gross' || $p->speed_rpm === null || $p->frequency_Hz === null) throw new DomainException('coupled_pump_curve_missing');
		$pump = $h->resolve($resolve,$p->curve_ref,'pump_curve'); $h->pumpBinding($pumpSpec,$pump); $h->sameFluid($p->fluid_ref,$fluid);
		$external = $h->resolve($resolve,$path->data->external_loss_ref,'pressure_loss_map'); $h->sameFluid($external->data->fluid_ref,$fluid);
		if($external->data->role !== 'external' || count($external->data->sections) !== 1 || $external->data->sections[0]->section_key !== 'whole' || $external->data->missing_evidence || $fluid->data->missing_evidence || $pump->data->missing_evidence) throw new DomainException('coupled_complete_fluid_pump_external_evidence_required');
		$section = $map->data->sections[0]; $epsilon = (float)$section->effectiveness; $hx = new Items_Service_HeatExchangerMap();
		foreach(['min','max'] as $end) if(!$hx->inside($bounds->$end,$section->limits->saturation_temperature)) throw new DomainException('coupled_search_outside_exchanger');
		$domain = [(float)$inlet+$epsilon*((float)$bounds->min-(float)$inlet),(float)$inlet+$epsilon*((float)$bounds->max-(float)$inlet)];
		if($domain[0] <= (float)$inlet && $domain[1] >= (float)$inlet) throw new DomainException('coupled_zero_temperature_spread');
		if(($side === 'evaporator') !== ($domain[1] < (float)$inlet)) throw new DomainException('coupled_heat_direction_invalid');
		return compact('machine','assignment','binding','inlet','heat','heatBox','map','loop','fluid','composition','path','losses','locations','pump','pumpSpec','external','epsilon','domain','resolve','side');
	}

	private function sample(array $a, float $outlet): array
	{
		$f = $this->fluid; $tin = $a['inlet']; $tout = $f::decimal($outlet); $m = $a['heat']*1000/abs($f->enthalpyDifference($a['fluid']->data,$a['composition'],$tin,$tout));
		$saturation = (float)$tin+($outlet-(float)$tin)/$a['epsilon']; $states = [];
		$flow = function($temperature) use($a,$f,$m) { $p = $f->state($a['fluid']->data,$a['composition'],$temperature); return $f::decimal(3600*$m/$p['density_kg_m3']); };
		foreach($a['losses'] as $key => $record) { $t = $a['locations'][$key] ? $tout : $tin; $states[] = (object)['instance_key' => $key, 'sections' => [(object)['section_key' => $record->data->sections[0]->section_key, 'temperature_degC' => $t, 'volume_flow_m3_h' => $flow($t)]]]; }
		$pumpTemperature = $a['locations'][$a['path']->data->pump_instance] ? $tout : $tin; $p = $a['pumpSpec']->data->specification;
		$input = (object)['loop_key' => $a['loop']->loop_key, 'composition' => $a['composition'], 'flow' => (object)['value' => $f::decimal($m), 'unit' => 'kg/s', 'at_degC' => $tin], 'component_states' => $states, 'pipe_states' => [], 'external_state' => (object)['temperature_degC' => $tout, 'volume_flow_m3_h' => $flow($tout)], 'pump_condition' => (object)['temperature_degC' => $pumpTemperature, 'speed_rpm' => $p->speed_rpm, 'frequency_Hz' => $p->frequency_Hz, 'operating_mode' => $a['pump']->data->operating_mode]];
		// Each trial runs the original D5 path with its newly determined local temperatures and mass-conserving flows.
		$loop = $this->hydraulic->loop($a['machine']->data,$a['loop'],$input,$a['resolve']); $hx = (new Items_Service_HeatExchangerMap())->atFluidPoint($a['map'],$a['assignment']->section_key,$f::decimal($saturation),$tin,$f::decimal($m),$a['fluid'],$a['side']);
		if($hx['mathematical_status'] !== 'calculated' || $loop['pump']['electrical_input_W'] === null) throw new DomainException('coupled_thermal_or_electrical_trial_invalid');
		foreach($a['locations'] as $key => $after) { $limit = $a['binding']['specs'][$key]->data->specification; $actualFlow = (float)$flow($after ? $tout : $tin); if((isset($limit->flow_min_m3_h) && $actualFlow < (float)$limit->flow_min_m3_h) || (isset($limit->flow_max_m3_h) && $actualFlow > (float)$limit->flow_max_m3_h)) throw new DomainException('coupled_component_flow_limit'); }
		$secondaryHeat = abs($m*$f->enthalpyDifference($a['fluid']->data,$a['composition'],$tin,$f::decimal($hx['secondary_outlet_degC']))/1000);
		$roundoff = 256*PHP_FLOAT_EPSILON*max(1.0,abs($a['heat']),$m*$hx['inlet_state']['specific_heat_J_kg_K']*max(abs((float)$tin),abs($outlet))/1000);
		return ['pump_revision' => ['dataset_key' => $a['pump']->dataset_key, 'revision' => $a['pump']->revision], 'hydraulic_path_revision' => ['dataset_key' => $a['path']->dataset_key, 'revision' => $a['path']->revision], 'secondary_energy_residual_kW' => $a['heat']-$secondaryHeat, 'heat_roundoff_allowance_kW' => $roundoff, 'outlet_degC' => $outlet, 'saturation_degC' => $saturation, 'mass_flow_kg_s' => $m, 'heat_flow_kW' => $hx['heat_flow_kW'], 'heat_residual_kW' => $a['heat']-$hx['heat_flow_kW'], 'pressure_residual_Pa' => $loop['pump']['reserve_Pa'], 'exchanger' => $hx, 'hydraulic_input' => $input, 'hydraulic_trial' => $loop];
	}

	private function solve(array $a, stdClass $n, ?array &$diagnostics): ?array
	{
		[$lo,$hi] = $a['domain']; $diagnostics = ['status' => 'certifying', 'certification_segments' => 64, 'trial_count' => 0, 'iterations' => 0, 'derivative_bounds_Pa_K' => [], 'trace' => []];
		$sign = null; $brackets = 0; $previous = null; $certified = true;
		for($i=0;$i<=64;$i++) {
			$x = $i === 64 ? $hi : $lo+($hi-$lo)*$i/64; $v = $this->sample($a,$x); $diagnostics['trial_count']++;
			if($previous !== null) {
				if($previous['pressure_residual_Pa']*$v['pressure_residual_Pa'] < 0 || $v['pressure_residual_Pa'] === 0.0) $brackets++;
				$derivative = $this->residualDerivative($a,[$previous['outlet_degC'],$x]); $diagnostics['derivative_bounds_Pa_K'][] = $derivative;
				$direction = $derivative[0] > 0 ? 1 : ($derivative[1] < 0 ? -1 : 0);
				if(!$direction || ($sign !== null && $sign !== $direction)) $certified = false; if($direction) $sign = $direction;
			}
			$previous = $v;
		}
		$diagnostics['observed_root_brackets'] = $brackets;
		if(!$certified || $brackets > 1) { $diagnostics['status'] = $brackets > 1 ? 'multiple_solutions_detected' : 'uniqueness_not_certified'; return null; }
		$diagnostics['uniqueness'] = 'strict_global_derivative_interval_certificate';
		$left = $this->residualBox($a,[$lo,$lo])[0]; $right = $this->residualBox($a,[$hi,$hi])[0];
		$diagnostics['endpoint_residual_enclosures_Pa'] = [$left,$right];
		if(!(($left[1] < 0 && $right[0] > 0) || ($left[0] > 0 && $right[1] < 0))) {
			$diagnostics['status'] = ($left[0] > 0 && $right[0] > 0) || ($left[1] < 0 && $right[1] < 0) ? 'no_solution_in_domain' : 'endpoint_bracket_not_certified'; return null;
		}
		for($i=0;$i<$n->max_iterations;$i++) {
			$x = $lo+($hi-$lo)/2; $v = $this->sample($a,$x); $diagnostics['trial_count']++; $diagnostics['iterations'] = $i+1;
			$slopes = array_merge(...$diagnostics['derivative_bounds_Pa_K']); $minimumSlope = min(array_map('abs',$slopes));
			$error = ($hi-$lo)/$a['epsilon']; $diagnostics['trace'][] = ['outlet_degC' => $x, 'pressure_residual_Pa' => $v['pressure_residual_Pa'], 'heat_residual_kW' => $v['heat_residual_kW'], 'saturation_interval_K' => $error];
			$box = $this->residualBox($a,[$x,$x])[0]; $residualBound = max(array_map('abs',$box)); if($v['pressure_residual_Pa'] < $box[0] || $v['pressure_residual_Pa'] > $box[1]) throw new DomainException('coupled_pressure_interval_consistency_failure'); $outletError = min($hi-$lo,$this->interval->bound([$residualBound/$minimumSlope])[1]);
			$outletBox = $this->interval->bound([$x-$outletError,$x+$outletError]);
			$saturationBox = $this->plus($this->constant((float)$a['inlet']),$this->divide($this->plus([$outletBox,[0.0,0.0]],$this->constant(-(float)$a['inlet'])),$this->constant($a['epsilon'])))[0];
			$error = $this->interval->bound([max(abs($v['saturation_degC']-$saturationBox[0]),abs($v['saturation_degC']-$saturationBox[1]))])[1];
			if(max(abs($v['heat_residual_kW']),abs($v['secondary_energy_residual_kW']))+$v['heat_roundoff_allowance_kW'] <= (float)$n->heat_tolerance_kW && $residualBound <= (float)$n->pressure_tolerance_Pa && $error <= (float)$n->temperature_tolerance_K) { $diagnostics['status'] = 'converged'; $diagnostics['pressure_residual_enclosure_Pa'] = $box; $diagnostics['saturation_error_bound_K'] = $error; return $v; }
			// Only an enclosed sign can discard half of the certified domain.
			if($box[0] <= 0 && $box[1] >= 0) { $diagnostics['status'] = 'numeric_precision_unconfirmed'; return null; }
			if(($sign > 0 && $box[1] < 0) || ($sign < 0 && $box[0] > 0)) $lo=$x; else $hi=$x;
		}
		$diagnostics['status'] = 'not_converged'; return null;
	}

	private function residualDerivative(array $a, array $outlet): array { return $this->residualBox($a,$outlet)[1]; }

	// Interval automatic differentiation certifies every temperature in the declared domain, including table corners.
	private function residualBox(array $a, array $outlet): array
	{
		$t = [$outlet,[1.0,1.0]]; $tin = $this->constant((float)$a['inlet']); $delta = $this->enthalpyBox($a,$outlet);
		$cp = $this->property($a,$t,'specific_heat_J_kg_K'); $direction = $outlet[1] < (float)$a['inlet'] ? -1 : 1;
		$energy = [$delta,$this->interval->multiply([$direction,$direction],$cp[0])];
		$mass = $this->divide([$this->interval->multiply($a['heatBox'],[1000.0,1000.0]),[0.0,0.0]],$energy);
		$volume = function($temp) use($a,$mass) { return $this->divide($this->times($this->constant(3600),$mass),$this->property($a,$temp,'density_kg_m3')); };
		$total = $this->constant(0);
		foreach($a['losses'] as $key => $record) { $temp = $a['locations'][$key] ? $t : $tin; $total = $this->plus($total,$this->pressureBox($record,$a['composition'],$temp,$volume($temp))); }
		$total = $this->plus($total,$this->pressureBox($a['external'],$a['composition'],$t,$volume($t)));
		$temp = $a['locations'][$a['path']->data->pump_instance] ? $t : $tin; $pump = $a['pump']->data;
		$ordinate = $this->table([array_map(fn($p) => (float)$p->volume_flow_m3_h,$pump->points)],array_map(fn($p) => $this->interval->bound([(float)$p->ordinate]),$pump->points),[$volume($temp)]);
		$available = $pump->ordinate_unit === 'm' ? $this->times($this->times($ordinate,$this->property($a,$temp,'density_kg_m3')),$this->constant(Items_Service_FluidHydraulics::GRAVITY_M_S2)) : $this->times($ordinate,$this->constant(['Pa'=>1,'kPa'=>1000,'bar'=>100000][$pump->ordinate_unit]));
		return $this->plus($available,$this->times($this->constant(-1),$total));
	}

	private function enthalpyBox(array $a, array $outlet): array
	{
		$ends = [];
		foreach($outlet as $end) {
			$lo = min((float)$a['inlet'],$end); $hi = max((float)$a['inlet'],$end); $nodes = [$lo]; foreach($a['fluid']->data->temperatures_degC as $t) if((float)$t > $lo && (float)$t < $hi) $nodes[] = (float)$t; $nodes[] = $hi; $sum = [0.0,0.0];
			for($i=1;$i<count($nodes);$i++) {
				$p = $this->property($a,$this->constant($nodes[$i-1]),'specific_heat_J_kg_K')[0]; $q = $this->property($a,$this->constant($nodes[$i]),'specific_heat_J_kg_K')[0];
				$width = $this->interval->add($this->interval->bound([$nodes[$i]]),$this->interval->bound([-$nodes[$i-1]]));
				$sum = $this->interval->add($sum,$this->interval->multiply($width,$this->interval->multiply([0.5,0.5],$this->interval->add($p,$q))));
			}
			$ends = array_merge($ends,$sum);
		}
		return $this->interval->bound($ends);
	}

	private function property(array $a, array $temperature, string $key): array
	{
		$d = $a['fluid']->data; $values = $this->concentrate($d->concentrations,$a['composition']->fraction,array_map(fn($row) => array_map(fn($v) => $v->$key,$row),$d->properties));
		return $this->table([array_map('floatval',$d->temperatures_degC)],$values,[$temperature]);
	}

	private function pressureBox(stdClass $record, stdClass $composition, array $temperature, array $volume): array
	{
		$d = $record->data; $values = $this->concentrate($d->concentrations,$composition->fraction,$d->sections[0]->pressure_losses);
		return $this->times($this->table([array_map('floatval',$d->temperatures_degC),array_map('floatval',$d->volume_flows_m3_h)],$values,[$temperature,$volume]),$this->constant(['Pa'=>1,'kPa'=>1000,'bar'=>100000][$d->pressure_unit]));
	}

	private function concentrate(array $axis, string $value, array $grid): array
	{
		[$a,$b] = $this->fluid::bracket($axis,$value);
		$w = $a === $b ? $this->constant(0) : $this->divide($this->plus($this->constant((float)$value),$this->constant(-(float)$axis[$a])),$this->plus($this->constant((float)$axis[$b]),$this->constant(-(float)$axis[$a])));
		$blend = function($x,$y) use(&$blend,$w) { if(is_array($x)) return array_map(fn($v,$i) => $blend($v,$y[$i]),$x,array_keys($x)); return $this->plus($this->constant((float)$x),$this->times($w,$this->plus($this->constant((float)$y),$this->constant(-(float)$x))))[0]; };
		return $blend($grid[$a],$grid[$b]);
	}

	private function table(array $axes, array $grid, array $variables): array
	{
		$axis = $axes[0]; $x = $variables[0]; if($x[0][0] < $axis[0] || $x[0][1] > end($axis)) throw new DomainException('coupled_interval_extrapolation_forbidden'); $results = [];
		for($i=0;$i<count($axis)-1;$i++) if($axis[$i+1] >= $x[0][0] && $axis[$i] <= $x[0][1]) {
			$part = [[max($axis[$i],$x[0][0]),min($axis[$i+1],$x[0][1])],$x[1]];
			$w = $this->divide($this->plus($part,$this->constant(-$axis[$i])),$this->plus($this->constant($axis[$i+1]),$this->constant(-$axis[$i])));
			if(count($axes) === 1) { $left = [$grid[$i],[0.0,0.0]]; $right = [$grid[$i+1],[0.0,0.0]]; }
			else { $left = $this->table(array_slice($axes,1),$grid[$i],array_slice($variables,1)); $right = $this->table(array_slice($axes,1),$grid[$i+1],array_slice($variables,1)); }
			$results[] = $this->plus($left,$this->times($w,$this->plus($right,$this->times($this->constant(-1),$left))));
		}
		if(!$results) throw new DomainException('coupled_interval_table_incomplete');
		return [$this->interval->bound(array_merge(...array_column($results,0))),$this->interval->bound(array_merge(...array_column($results,1)))];
	}

	private function constant(float $value): array { return [$this->interval->bound([$value]),[0.0,0.0]]; }
	private function plus(array $a, array $b): array { return [$this->interval->add($a[0],$b[0]),$this->interval->add($a[1],$b[1])]; }
	private function times(array $a, array $b): array { return [$this->interval->multiply($a[0],$b[0]),$this->interval->add($this->interval->multiply($a[0],$b[1]),$this->interval->multiply($a[1],$b[0]))]; }
	private function divide(array $a, array $b): array
	{
		if($b[0][0] <= 0 && $b[0][1] >= 0) throw new DomainException('coupled_interval_division_unresolved');
		$inverse = $this->interval->bound([1/$b[0][0],1/$b[0][1]]);
		return $this->times($a,[$inverse,$this->interval->multiply([-1.0,-1.0],$this->interval->multiply($b[1],$this->interval->multiply($inverse,$inverse)))]);
	}
	private function point(float $te, float $tc): stdClass { return (object)['Te_degC' => $this->fluid::decimal($te), 'Tc_degC' => $this->fluid::decimal($tc), 'temperature_unit' => 'degC']; }
}
