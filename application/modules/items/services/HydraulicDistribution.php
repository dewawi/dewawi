<?php

class Items_Service_HydraulicDistribution
{
	public const VERSION = 'fixed_speed_two_parallel_sections_bisection_v1';

	public function evaluate(stdClass $machine, stdClass $request, callable $resolve, ?string $blocker = null): array
	{
		$schema = new Items_Service_TechnicalSchema(); $machine = $schema->dataset($machine);
		if($machine->dataset_type !== 'machine_configuration') throw new InvalidArgumentException('Distribution machine required');
		$input = $schema->fields($request, ['loop_key' => 'key', 'composition' => 'object', 'temperature_degC' => 'number', 'speed_rpm' => 'positive', 'frequency_Hz' => 'positive', 'operating_mode' => 'key']);
		$result = ['result_version' => 1, 'calculation_version' => self::VERSION, 'scope' => 'fixed_temperature_hydraulic_operating_point_only', 'status' => 'review_required', 'eligible' => false,
			'machine_revision' => $this->identity($machine), 'loop_key' => $input->loop_key, 'conditions' => $input, 'path_revision' => null, 'fluid_revision' => null, 'pump' => null, 'components' => [],
			'solution' => null, 'thermal_inputs' => null, 'fully_coupled' => false, 'mathematical_status' => 'blocked', 'missing_evidence' => [],
			'assumptions' => ['prescribed_uniform_temperature', 'fixed_documented_pump_speed', 'closed_dissipative_path', 'two_existing_exchanger_sections', 'no_inferred_losses_or_valve_states'],
			'reasons' => ['machine_physics_not_released', 'thermal_feedback_not_solved'],
			'convergence' => ['method' => 'branch_curve_inverse_common_pressure_bisection_v1', 'status' => 'not_started', 'iterations' => 0, 'evaluations' => 0, 'maximum_evaluations' => 0, 'trace' => []]];
		if($blocker !== null) { $result['reasons'][] = $blocker; return $result; }
		try {
			$prepared = $this->prepare($machine->data, $input, $resolve, $result);
			$this->solve($prepared, $result);
		} catch(DomainException $e) { $result['reasons'][] = $e->getMessage(); if(in_array($result['convergence']['status'], ['not_started', 'searching'], true)) $result['convergence']['status'] = 'blocked'; }
		return $result;
	}

	private function prepare(stdClass $machine, stdClass $input, callable $resolve, array &$result): array
	{
		$h = new Items_Service_FluidHydraulics(); $f = new Items_Service_FluidProperties();
		$loops = array_values(array_filter($machine->hydraulic_loops, fn($l) => $l->loop_key === $input->loop_key));
		if(count($loops) !== 1) throw new InvalidArgumentException('Unknown hydraulic loop'); $loop = $loops[0];
		if($loop->fluid_dataset === null || $loop->pressure_loss_dataset === null) throw new DomainException('fluid_or_distribution_path_missing');
		$path = $h->resolve($resolve, $loop->pressure_loss_dataset, 'hydraulic_path'); $d = $path->data;
		if($d->contract_version !== 2) throw new DomainException('versioned_two_branch_distribution_required');
		$h->distributionBinding($machine, $loop, $path, $resolve);
		if($loop->tank_kind === 'open') throw new DomainException('open_loop_static_head_not_supported');
		$fluid = $h->resolve($resolve, $loop->fluid_dataset, 'fluid_properties'); $state = $f->state($fluid->data, $input->composition, $input->temperature_degC);
		$result['path_revision'] = $this->identity($path); $result['fluid_revision'] = $this->identity($fluid);
		$result['missing_evidence'] = array_values(array_unique(array_merge($d->missing_evidence, $fluid->data->missing_evidence)));
		$components = []; foreach($machine->components as $c) $components[$c->instance_key] = $c;
		$pumpComponent = $components[$d->pump_instance]; $pumpSpec = $h->component($pumpComponent, $resolve); $ps = $pumpSpec->data->specification;
		if($ps->curve_ref === null || $ps->curve_definition !== 'gross' || $ps->speed_rpm === null || $ps->frequency_Hz === null || $ps->fluid_ref === null) throw new DomainException('pump_curve_or_conditions_missing');
		$pump = $h->resolve($resolve, $ps->curve_ref, 'pump_curve'); $h->pumpBinding($pumpSpec, $pump); $h->sameFluid($ps->fluid_ref, $fluid); $h->sameFluid($pump->data->fluid_ref, $fluid);
		$condition = (object)['temperature_degC' => $input->temperature_degC, 'speed_rpm' => $input->speed_rpm, 'frequency_Hz' => $input->frequency_Hz, 'operating_mode' => $input->operating_mode];
		$result['pump'] = ['instance_key' => $d->pump_instance, 'profile_key' => $pumpComponent->profile_key, 'profile_revision' => $pumpComponent->profile_revision, 'specification' => $this->identity($pumpSpec), 'curve' => $this->identity($pump)];
		$result['missing_evidence'] = array_merge($result['missing_evidence'], $pump->data->missing_evidence);
		$pumpFlows = array_map(fn($p) => $p->volume_flow_m3_h, $pump->data->points); $totalMin = $pumpFlows[0]; $totalMax = end($pumpFlows);
		if(isset($loop->flow_min_m3_h)) $totalMin = $this->maximum($totalMin, $loop->flow_min_m3_h);
		if(isset($loop->flow_max_m3_h)) $totalMax = $this->minimum($totalMax, $loop->flow_max_m3_h);
		$branches = []; $common = [];
		foreach($d->component_instances as $key) {
			$component = $components[$key]; $spec = $h->component($component, $resolve); $ref = $spec->data->specification->pressure_loss_ref ?? null;
			if($ref === null) throw new DomainException('component_pressure_loss_missing');
			$map = $h->resolve($resolve, $ref, 'pressure_loss_map'); $h->mapBinding($spec, $map); $h->sameFluid($map->data->fluid_ref, $fluid);
			$result['components'][$key] = ['profile_key' => $component->profile_key, 'profile_revision' => $component->profile_revision, 'specification' => $this->identity($spec), 'pressure_map' => $this->identity($map)];
			$result['missing_evidence'] = array_merge($result['missing_evidence'], $map->data->missing_evidence);
			$limits = $spec->data->specification;
			if(isset($limits->flow_min_m3_h)) $totalMin = $this->maximum($totalMin, $limits->flow_min_m3_h);
			if(isset($limits->flow_max_m3_h)) $totalMax = $this->minimum($totalMax, $limits->flow_max_m3_h);
			$pressure = fn($section, $flow) => $h->pressure($map, $fluid, $section, $input->composition, $input->temperature_degC, $flow)['pressure_loss_Pa'];
			if($key === $d->parallel_instance) {
				$parallelMap = $map;
				foreach($d->parallel_sections as $section) {
					$min = $this->maximum($section->flow_min_m3_h, $map->data->volume_flows_m3_h[0]); $max = $this->minimum($section->flow_max_m3_h, end($map->data->volume_flows_m3_h));
					$curve = $this->curve($map->data->volume_flows_m3_h, $min, $max, fn($q) => $pressure($section->section_key, $q));
					$this->monotone($curve, 'increasing'); $branches[$section->section_key] = $curve;
				}
			} else {
				$common[$key] = ['map' => $map, 'section' => $map->data->sections[0]->section_key];
				$totalMin = $this->maximum($totalMin, $map->data->volume_flows_m3_h[0]); $totalMax = $this->minimum($totalMax, end($map->data->volume_flows_m3_h));
			}
		}
		if($d->external_loss_ref === null) throw new DomainException('external_pressure_loss_missing');
		$external = $h->resolve($resolve, $d->external_loss_ref, 'pressure_loss_map'); $h->sameFluid($external->data->fluid_ref, $fluid);
		if($external->data->role !== 'external' || array_map(fn($s) => $s->section_key, $external->data->sections) !== ['whole']) throw new InvalidArgumentException('External loss boundary mismatch');
		$result['external_map'] = $this->identity($external); $result['missing_evidence'] = array_merge($result['missing_evidence'], $external->data->missing_evidence);
		$common['external'] = ['map' => $external, 'section' => 'whole'];
		$result['missing_evidence'] = array_values(array_unique($result['missing_evidence']));
		if($result['missing_evidence']) throw new DomainException('hydraulic_operating_evidence_incomplete');
		$totalMin = $this->maximum($totalMin, $external->data->volume_flows_m3_h[0]); $totalMax = $this->minimum($totalMax, end($external->data->volume_flows_m3_h));
		foreach($d->pipe_segments as $pipe) {
			// Re < 2300 is the registered D5A pipe domain; stay strictly inside its upper edge.
			$maximum = 2300 * M_PI * (float)$pipe->internal_diameter_m * $state['dynamic_viscosity_Pa_s'] / (4 * $state['density_kg_m3']) * 3600 * (1 - 1e-12);
			$totalMax = $this->minimum($totalMax, $f::decimal($maximum));
		}
		$this->range($totalMin, $totalMax);
		$pumpValue = function(string $flow) use ($h, $pump, $fluid, $input, $condition): float { $q = clone $condition; $q->volume_flow_m3_h = $flow; return $h->pump($pump, $fluid, $input->composition, $q, 0.0)['available_pressure_Pa']; };
		$pumpCurve = $this->curve($pumpFlows, $totalMin, $totalMax, $pumpValue); $this->monotone($pumpCurve, 'nonincreasing');
		foreach($common as $key => $entry) {
			$map = $entry['map']; $section = $entry['section'];
			$curve = $this->curve($map->data->volume_flows_m3_h, $totalMin, $totalMax, fn($q) => $h->pressure($map, $fluid, $section, $input->composition, $input->temperature_degC, $q)['pressure_loss_Pa']);
			$this->monotone($curve, 'nondecreasing');
		}
		$pressureMin = max(array_map(fn($b) => $b[0][1], $branches)); $pressureMax = min(array_map(fn($b) => end($b)[1], $branches));
		if($pressureMin >= $pressureMax) throw new DomainException('parallel_pressure_domains_do_not_overlap');
		$knots = [$pressureMin, $pressureMax];
		foreach($branches as $branch) foreach($branch as [$flow, $pressure]) if($pressure > $pressureMin && $pressure < $pressureMax) $knots[] = $pressure;
		sort($knots, SORT_NUMERIC); $knots = array_values(array_unique($knots, SORT_REGULAR)); $network = [];
		foreach($knots as $pressure) $network[] = [$pressure, array_sum(array_map(fn($b) => $this->interpolate($b, $pressure, true), $branches))];
		$this->monotone($network, 'increasing');
		$lowFlow = max((float)$totalMin, $network[0][1]); $highFlow = min((float)$totalMax, end($network)[1]);
		if($lowFlow >= $highFlow) throw new DomainException('total_flow_domains_do_not_overlap');
		$low = $this->interpolate($network, $lowFlow, true); $high = $this->interpolate($network, $highFlow, true);
		$result['valid_domains'] = ['total_flow_m3_h' => [$lowFlow, $highFlow], 'parallel_pressure_Pa' => [$low, $high], 'branch_flow_m3_h' => array_map(fn($b) => [$b[0][0], end($b)[0]], $branches)];
		$result['uniqueness'] = ['status' => 'proven_for_registered_piecewise_curves', 'branch_losses' => 'strictly_increasing', 'common_losses' => 'nondecreasing', 'pump_pressure' => 'nonincreasing', 'residual_pressure_slope_upper_bound' => -1.0];
		return compact('h', 'f', 'input', 'loop', 'path', 'fluid', 'state', 'condition', 'pump', 'branches', 'parallelMap', 'common', 'low', 'high');
	}

	private function solve(array $p, array &$result): void
	{
		$n = $p['path']->data->numerics; $low = $p['low']; $high = $p['high'];
		$result['convergence']['maximum_evaluations'] = $n->max_iterations + 2;
		$left = $this->point($p, $low); $right = $this->point($p, $high); $result['convergence']['evaluations'] = 2;
		if($left['pump_residual_Pa'] < 0 || $right['pump_residual_Pa'] > 0) throw new DomainException('pump_operating_point_not_reachable_in_valid_domains');
		$result['convergence']['status'] = 'searching';
		for($i = 1; $i <= $n->max_iterations; $i++) {
			$pressure = $low + ($high - $low) / 2; $point = $this->point($p, $pressure);
			$result['convergence']['iterations'] = $i; $result['convergence']['evaluations']++;
			$lower = $this->flows($p['branches'], $low); $upper = $this->flows($p['branches'], $high);
			$width = max(array_map(fn($key) => $upper[$key] - $lower[$key], array_keys($lower))); $totalWidth = array_sum($upper) - array_sum($lower);
			$roundoff = 64 * PHP_FLOAT_EPSILON * max(1.0, abs($point['pump']['available_pressure_Pa']) + abs($point['required_pressure_Pa']));
			$result['convergence']['trace'][] = ['iteration' => $i, 'parallel_pressure_Pa' => $pressure, 'pump_residual_Pa' => $point['pump_residual_Pa'], 'pressure_bracket_Pa' => [$low, $high], 'total_flow_width_m3_h' => $totalWidth];
			if(abs($point['pump_residual_Pa']) + $roundoff <= (float)$n->pressure_tolerance_Pa && max(abs($point['branch_residual_Pa']), abs($point['inverse_residual_Pa'])) + $roundoff <= (float)$n->pressure_tolerance_Pa && abs($point['mass_residual_kg_s']) <= (float)$n->mass_tolerance_kg_s && abs($point['volume_residual_m3_h']) <= (float)$n->flow_tolerance_m3_h && max($width, $totalWidth) <= (float)$n->flow_tolerance_m3_h) {
				if(isset($p['loop']->external_requirement_kPa) && abs($point['shared_component_losses_Pa']['external'] - (float)$p['loop']->external_requirement_kPa * 1000) > (float)$n->pressure_tolerance_Pa) throw new DomainException('external_pressure_requirement_conflict');
				$point['pump']['duty_status'] = 'balanced_within_tolerance';
				$branches = []; $total = '0';
				foreach($point['branches'] as $key => $branch) { $flow = Items_Service_FluidProperties::decimal($branch['volume_flow_m3_h']); $branches[] = (object)['section_key' => $key, 'volume_flow_m3_h' => $flow]; $total = Items_Service_TechnicalJson::addNonnegativeDecimals($total, $flow); }
				$result['thermal_inputs'] = ['contract_version' => 1, 'source' => self::VERSION, 'eligible' => false, 'fully_coupled' => false, 'instance_key' => $p['path']->data->parallel_instance, 'loop_key' => $p['loop']->loop_key, 'fluid_revision' => $result['fluid_revision'], 'inlet_degC' => $p['input']->temperature_degC, 'total_volume_flow_m3_h' => $total, 'branches' => $branches];
				$result['convergence']['status'] = 'converged'; $result['convergence']['flow_error_bound_m3_h'] = max($width, $totalWidth); $result['convergence']['roundoff_allowance_Pa'] = $roundoff;
				$result['solution'] = $point; $result['mathematical_status'] = 'calculated';
				return;
			}
			if($pressure === $low || $pressure === $high) break;
			if($point['pump_residual_Pa'] >= 0) $low = $pressure; else $high = $pressure;
		}
		$result['convergence']['status'] = 'not_converged'; throw new DomainException('hydraulic_distribution_not_converged');
	}

	private function point(array $p, float $pressure): array
	{
		$h = $p['h']; $f = $p['f']; $flows = $this->flows($p['branches'], $pressure); $total = array_sum($flows); $branches = [];
		foreach($flows as $key => $flow) {
			$converted = $f->flow($p['fluid']->data, $p['input']->composition, (object)['value' => $f::decimal($flow), 'unit' => 'm3/h', 'at_degC' => $p['input']->temperature_degC]);
			$branches[$key] = ['volume_flow_m3_h' => $flow, 'mass_flow_kg_s' => $converted['mass_flow_kg_s'], 'pressure_loss_Pa' => $h->pressure($p['parallelMap'], $p['fluid'], $key, $p['input']->composition, $p['input']->temperature_degC, $f::decimal($flow))['pressure_loss_Pa']];
		}
		$totalMass = $f->flow($p['fluid']->data, $p['input']->composition, (object)['value' => $f::decimal($total), 'unit' => 'm3/h', 'at_degC' => $p['input']->temperature_degC])['mass_flow_kg_s'];
		$shared = []; foreach($p['common'] as $key => $entry) $shared[$key] = $h->pressure($entry['map'], $p['fluid'], $entry['section'], $p['input']->composition, $p['input']->temperature_degC, $f::decimal($total))['pressure_loss_Pa'];
		$parallel = array_sum(array_column($branches, 'pressure_loss_Pa')) / count($branches);
		$pipes = []; foreach($p['path']->data->pipe_segments as $pipe) $pipes[$pipe->segment_key] = $h->pipe($pipe, $p['state'], $total);
		$required = array_sum($shared) + array_sum(array_column($pipes, 'pressure_loss_Pa')) + $parallel;
		$condition = clone $p['condition']; $condition->volume_flow_m3_h = $f::decimal($total); $pump = $h->pump($p['pump'], $p['fluid'], $p['input']->composition, $condition, $required);
		$r = ['eligible' => false, 'total_volume_flow_m3_h' => $total, 'total_mass_flow_kg_s' => $totalMass, 'branches' => $branches, 'parallel_pressure_Pa' => $parallel,
			'shared_component_losses_Pa' => $shared, 'pipe_losses' => $pipes, 'shared_pressure_Pa' => $required - $parallel, 'required_pressure_Pa' => $required, 'pump' => $pump,
			'pump_residual_Pa' => $pump['available_pressure_Pa'] - $required, 'inverse_residual_Pa' => max(array_map(fn($branch) => abs($branch['pressure_loss_Pa'] - $pressure), $branches)), 'branch_residual_Pa' => max(array_column($branches, 'pressure_loss_Pa')) - min(array_column($branches, 'pressure_loss_Pa')),
			'volume_residual_m3_h' => $total - array_sum(array_column($branches, 'volume_flow_m3_h')), 'mass_residual_kg_s' => $totalMass - array_sum(array_column($branches, 'mass_flow_kg_s')), 'fluid_state' => $p['state']];
		$f->finite($r); return $r;
	}

	private function curve(array $axis, string $min, string $max, callable $value): array
	{
		$this->range($min, $max); $xs = [$min];
		foreach($axis as $x) if(Items_Service_TechnicalJson::compareDecimal($x, $min) > 0 && Items_Service_TechnicalJson::compareDecimal($x, $max) < 0) $xs[] = $x;
		$xs[] = $max; $curve = []; foreach($xs as $x) $curve[] = [(float)$x, $value($x)]; return $curve;
	}

	private function monotone(array $curve, string $kind): void
	{
		foreach($curve as $i => [$x, $y]) {
			if(!is_finite($x) || !is_finite($y) || ($i && $x <= $curve[$i - 1][0])) throw new DomainException('hydraulic_curve_numeric_domain_invalid');
			if(!$i) continue; $previous = $curve[$i - 1][1];
			if(($kind === 'increasing' && $y - $previous <= 16 * PHP_FLOAT_EPSILON * max(1.0, abs($y), abs($previous))) || ($kind === 'nondecreasing' && $y < $previous) || ($kind === 'nonincreasing' && $y > $previous)) throw new DomainException('hydraulic_curve_monotonicity_not_proven');
		}
	}

	private function flows(array $branches, float $pressure): array { return array_map(fn($b) => $this->interpolate($b, $pressure, true), $branches); }

	private function interpolate(array $curve, float $value, bool $inverse = false): float
	{
		$x = $inverse ? 1 : 0; $y = $inverse ? 0 : 1;
		if($value < $curve[0][$x] || $value > end($curve)[$x]) throw new DomainException('hydraulic_curve_extrapolation_forbidden');
		foreach($curve as $i => $point) {
			if($value === $point[$x]) return $point[$y];
			if($value < $point[$x]) { $a = $curve[$i - 1]; return Items_Service_FluidProperties::lerp($a[$y], $point[$y], ($value - $a[$x]) / ($point[$x] - $a[$x])); }
		}
		throw new DomainException('hydraulic_curve_position_invalid');
	}

	private function range(string $min, string $max): void { if(Items_Service_TechnicalJson::compareDecimal($min, $max) >= 0 || (float)$min >= (float)$max) throw new DomainException('hydraulic_flow_domains_do_not_overlap'); }
	private function maximum(string $a, string $b): string { return Items_Service_TechnicalJson::compareDecimal($a, $b) >= 0 ? $a : $b; }
	private function minimum(string $a, string $b): string { return Items_Service_TechnicalJson::compareDecimal($a, $b) <= 0 ? $a : $b; }
	private function identity(stdClass $record): array { return ['dataset_key' => $record->dataset_key, 'revision' => $record->revision, 'dataset_type' => $record->dataset_type]; }
}
