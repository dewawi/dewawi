<?php

class Items_Service_OperatingModeSchema
{
	private Items_Service_TechnicalSchema $schema;
	public function __construct() { $this->schema = new Items_Service_TechnicalSchema(); }

	public function model(stdClass $value): stdClass
	{
		$s = $this->schema; $coupled = (string)($value->contract_version ?? '') === '2';
		$d = $s->fields($value, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'mode' => Items_Service_TechnicalSchema::OPERATING_MODES, 'circuit_key' => 'key',
			'topology_kind' => ['evaporator_condenser_v1', 'desuperheater_before_condenser_v1'], 'enabled_components' => 'list:key', 'isolated_components' => 'list:key', 'stage_keys' => 'list:key',
			'evaporator' => 'object', 'condenser' => 'object', 'recovery' => '?object', 'expansion' => 'object', 'valve_states' => 'list:object', 'energy_boundary_ref' => 'ref',
			'pipe_model' => ['synthetic_zero_refrigerant_pipe_pressure_heat_loss_v1'], 'inactive_compressor_model' => ['synthetic_no_refrigerant_flow_v1']] + ($coupled ? ['coupling' => 'object'] : ['points' => 'list:object']) + [
			'numerics' => 'object', 'missing_evidence' => 'list:key']);
		if(!in_array($d->contract_version, [1, 2], true) || !$d->stage_keys || (!$coupled && !$d->points)) throw new InvalidArgumentException('Unsupported or incomplete operating model');
		foreach(['enabled_components', 'isolated_components', 'stage_keys'] as $key) $this->unique($d->$key);
		if(array_intersect($d->enabled_components, $d->isolated_components)) throw new InvalidArgumentException('Contradictory component states');
		foreach(['evaporator', 'condenser', 'recovery'] as $side) if($d->$side !== null) $d->$side = $s->fields($d->$side, ['instance_key' => 'key', 'section_key' => 'key', 'inlet_port' => 'key', 'outlet_port' => 'key', 'loop_key' => 'key']);
		if(($d->mode === 'partial_heat_recovery') !== ($d->recovery !== null) || ($d->mode === 'partial_heat_recovery') !== ($d->topology_kind === 'desuperheater_before_condenser_v1')) throw new InvalidArgumentException('Recovery topology mismatch');
		$d->expansion = $s->fields($d->expansion, ['model_id' => ['synthetic_isenthalpic_no_external_heat_v1'], 'inlet_port' => 'key', 'outlet_port' => 'key']);
		$seen = []; foreach($d->valve_states as &$state) { $state = $s->fields($state, ['instance_key' => 'key', 'state_key' => 'key']); if(isset($seen[$state->instance_key])) throw new InvalidArgumentException('Conflicting valve states'); $seen[$state->instance_key] = true; }
		if($coupled) {
			if(!in_array($d->mode, ['cooling', 'heat_pump'], true) || $d->recovery !== null) throw new InvalidArgumentException('Recovery coupling is not supported');
			$d->coupling = $s->fields($d->coupling, ['model_id' => ['synthetic_constant_compressor_two_liquid_loops_v1'], 'secondary_heat_boundary' => ['synthetic_pump_and_dissipation_heat_removed_by_external_thermostats_v1'], 'source_locator' => 'text']);
			$d->numerics = $s->fields($d->numerics, ['heat_tolerance_kW' => 'positive', 'pressure_tolerance_Pa' => 'positive', 'temperature_tolerance_K' => 'positive', 'max_iterations' => 'int']);
			foreach(['heat_tolerance_kW', 'pressure_tolerance_Pa', 'temperature_tolerance_K'] as $key) if((float)$d->numerics->$key < 1e-9 || (float)$d->numerics->$key > 1e-4) throw new InvalidArgumentException('Unsupported coupled tolerance');
			if($d->numerics->max_iterations > 80) throw new InvalidArgumentException('Coupled iteration budget exceeded');
			return $d;
		}
		$seen = [];
		foreach($d->points as &$point) {
			$point = $s->fields($point, ['point_key' => 'key', 'stage_key' => 'key', 'Te_degC' => 'number', 'Tc_degC' => 'number', 'power_boundary' => 'object']);
			$key = $point->stage_key.'|'.$point->Te_degC.'|'.$point->Tc_degC;
			if(isset($seen[$key]) || !in_array($point->stage_key, $d->stage_keys, true) || (float)$point->Te_degC <= -273.15 || (float)$point->Te_degC >= (float)$point->Tc_degC) throw new InvalidArgumentException('Invalid or ambiguous operating point'); $seen[$key] = true;
			$p = $s->fields($point->power_boundary, ['model_id' => ['synthetic_all_electrical_inputs_hx_flange_useful_heat_v1'], 'external_auxiliary_kW' => '?nonnegative', 'source_locator' => 'text', 'inputs' => 'list:object']);
			$names = []; foreach($p->inputs as &$entry) { $entry = $s->fields($entry, ['instance_key' => 'key', 'electrical_power_kW' => '?nonnegative', 'heat_destination' => ['outside_refrigerant_and_useful_boundary'], 'source_locator' => 'text']); if(isset($names[$entry->instance_key])) throw new InvalidArgumentException('Duplicate electrical boundary input'); $names[$entry->instance_key] = true; }
			$point->power_boundary = $p;
		}
		$stages = array_values(array_unique(array_map(fn($p) => $p->stage_key, $d->points))); $expected = $d->stage_keys; sort($stages); sort($expected);
		if($stages !== $expected) throw new InvalidArgumentException('Operating point stage coverage missing');
		$d->numerics = $s->fields($d->numerics, ['heat_tolerance_kW' => 'positive', 'mass_tolerance_kg_s' => 'positive']);
		foreach($d->numerics as $v) if((float)$v < 1e-10 || (float)$v > 1e-4) throw new InvalidArgumentException('Unsupported operating balance tolerance');
		return $d;
	}

	public function exchanger(stdClass $value): stdClass
	{
		$s = $this->schema;
		$d = $s->fields($value, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'component_key' => 'key', 'model' => 'text', 'role' => ['evaporator', 'water_condenser', 'recovery_exchanger'],
			'exchanger_type' => ['plate', 'shell_tube'], 'refrigerant' => 'key', 'temperature_basis' => ['pure_saturation'], 'model_id' => ['synthetic_refrigerant_enthalpy_points_v1'],
			'state_basis' => ['explicit_synthetic_states_no_eos_v1'], 'enthalpy_reference' => ['synthetic_common_datum_v1'], 'refrigerant_sections_are_isolated' => 'bool',
			'fluid_ref' => 'ref', 'fluid_composition' => 'object', 'units' => 'object', 'sections' => 'list:object', 'missing_evidence' => 'list:key']);
		if($d->contract_version !== 2 || !$d->sections || (count($d->sections) > 1 && !$d->refrigerant_sections_are_isolated)) throw new InvalidArgumentException('Invalid operating exchanger contract');
		$d->fluid_composition = $this->composition($d->fluid_composition);
		$d->units = $s->fields($d->units, ['temperature' => ['degC'], 'pressure' => ['kPa'], 'specific_enthalpy' => ['kJ/kg'], 'mass_flow' => ['kg/s'], 'heat_flow' => ['kW'], 'volume_flow' => ['m3/h']]);
		$seen = [];
		foreach($d->sections as &$section) {
			$section = $s->fields($section, ['section_key' => 'key', 'limits' => 'object', 'points' => 'list:object']);
			if(isset($seen[$section->section_key]) || !$section->points) throw new InvalidArgumentException('Invalid exchanger sections'); $seen[$section->section_key] = true;
			$section->limits = $s->fields($section->limits, ['Te' => 'object', 'Tc' => 'object', 'secondary_inlet' => 'object', 'secondary_outlet' => 'object', 'volume_flow' => 'object', 'refrigerant_temperature' => 'object', 'refrigerant_pressure' => 'object']);
			foreach($section->limits as $key => &$range) $range = $s->thermalRange($range, $key === 'volume_flow' ? 'm3/h' : ($key === 'refrigerant_pressure' ? 'kPa' : 'degC'), in_array($key, ['volume_flow', 'refrigerant_pressure'], true));
			unset($range); $points = [];
			foreach($section->points as &$point) {
				$point = $s->fields($point, ['point_key' => 'key', 'mode' => Items_Service_TechnicalSchema::OPERATING_MODES, 'stage_key' => 'key', 'operating_role' => ['evaporator', 'condenser', 'desuperheater'], 'Te_degC' => 'number', 'Tc_degC' => 'number', 'mass_flow_kg_s' => 'positive', 'inlet_state' => '?object', 'outlet_state' => '?object', 'secondary' => 'object', 'heat_flow_kW' => 'positive']);
				$key = $point->mode.'|'.$point->stage_key.'|'.$point->point_key;
				if(isset($points[$key]) || (float)$point->Te_degC >= (float)$point->Tc_degC) throw new InvalidArgumentException('Duplicate or invalid exchanger point'); $points[$key] = true;
				foreach(['inlet_state', 'outlet_state'] as $side) if($point->$side !== null) $point->$side = $s->fields($point->$side, ['temperature_degC' => 'number', 'pressure_kPa' => 'positive', 'enthalpy_kJ_kg' => 'number', 'phase' => ['superheated_vapor', 'liquid', 'two_phase']]);
				$point->secondary = $s->fields($point->secondary, ['inlet_degC' => 'number', 'outlet_degC' => 'number', 'volume_flow_m3_h' => 'positive']);
			}
		}
		return $d;
	}

	public function coupledExchanger(stdClass $value): stdClass
	{
		$s = $this->schema;
		$d = $s->fields($value, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'component_key' => 'key', 'model' => 'text', 'role' => ['evaporator', 'water_condenser'], 'exchanger_type' => ['plate', 'shell_tube'], 'refrigerant' => 'key', 'temperature_basis' => ['pure_saturation'], 'model_id' => ['synthetic_fluid_enthalpy_effectiveness_v1'], 'measurement_boundary' => ['secondary_stream_only_no_external_heat_v1'], 'refrigerant_sections_are_isolated' => 'bool', 'fluid_ref' => 'ref', 'fluid_composition' => 'object', 'sections' => 'list:object', 'missing_evidence' => 'list:key']);
		if($d->contract_version !== 3 || count($d->sections) !== 1) throw new InvalidArgumentException('Coupled liquid exchanger requires one section');
		$d->fluid_composition = $this->composition($d->fluid_composition);
		foreach($d->sections as &$section) {
			$section = $s->fields($section, ['section_key' => 'key', 'effectiveness' => 'positive', 'limits' => 'object']);
			if((float)$section->effectiveness >= 1) throw new InvalidArgumentException('Invalid coupled effectiveness');
			$section->limits = $s->fields($section->limits, ['saturation_temperature' => 'object', 'secondary_inlet' => 'object', 'secondary_outlet' => 'object', 'volume_flow' => 'object']);
			foreach($section->limits as $key => &$range) $range = $s->thermalRange($range, $key === 'volume_flow' ? 'm3/h' : 'degC', $key === 'volume_flow');
		}
		return $d;
	}

	public function valve(stdClass $value, stdClass $ports): stdClass
	{
		$s = $this->schema; $v = $s->fields($value, ['model_id' => ['synthetic_refrigerant_port_switch_v1'], 'states' => 'list:object']);
		if(!$v->states) throw new InvalidArgumentException('Valve states required'); $seen = [];
		foreach($v->states as &$state) {
			$state = $s->fields($state, ['state_key' => 'key', 'connections' => 'list:list:key', 'closed_ports' => 'list:key']);
			if(isset($seen[$state->state_key])) throw new InvalidArgumentException('Duplicate valve state'); $seen[$state->state_key] = true; $used = $state->closed_ports;
			foreach($state->connections as $edge) { if(count($edge) !== 2 || $edge[0] === $edge[1]) throw new InvalidArgumentException('Invalid valve route'); $used = array_merge($used, $edge); }
			$this->unique($used); $expected = array_keys((array)$ports); sort($expected); sort($used);
			if($expected !== $used) throw new InvalidArgumentException('Valve state must connect or close every physical port');
			foreach($ports as $kind) if($kind !== 'refrigerant_bidirectional') throw new InvalidArgumentException('Valve ports must be explicitly bidirectional');
		}
		return $v;
	}

	public function machineBinding(stdClass $machine, callable $resolve): array
	{
		$models = []; $h = new Items_Service_FluidHydraulics();
		foreach($machine->mode_topologies as $entry) if($entry->release_status !== 'unsupported') {
			$record = $h->resolve($resolve, $entry->operating_model_ref, 'operating_mode_model');
			if($record->data->mode !== $entry->mode) throw new InvalidArgumentException('Operating model identity mismatch');
			$this->binding($machine, $record->data, $resolve); $models[$entry->mode] = $record;
		}
		if(!$models) throw new InvalidArgumentException('At least one defined operating mode required');
		if($machine->refrigerant_reversible) {
			if(!isset($models['cooling'], $models['heat_pump'])) throw new InvalidArgumentException('Reversible construction requires cooling and heat pump paths');
			$c = $models['cooling']->data; $h = $models['heat_pump']->data;
			if($c->evaporator->instance_key !== $h->condenser->instance_key || $c->condenser->instance_key !== $h->evaporator->instance_key || Items_Service_TechnicalJson::encode($c->valve_states) === Items_Service_TechnicalJson::encode($h->valve_states)) throw new InvalidArgumentException('Reversibility requires actual exchanger and valve routing changes');
		}
		return $models;
	}

	public function binding(stdClass $machine, stdClass $model, callable $resolve): array
	{
		if(($machine->configuration_version ?? 1) !== 2 || $machine->circuit_count !== 1 || $model->circuit_key !== $machine->circuits[0]->circuit_key) throw new InvalidArgumentException('Operating model requires its versioned single circuit');
		$h = new Items_Service_FluidHydraulics(); $components = $specs = [];
		foreach($machine->components as $component) {
			$components[$component->instance_key] = $component;
			if($component->role !== 'compressor') {
				$spec = $h->component($component, $resolve);
				if($spec->data->refrigerant !== null && $spec->data->refrigerant !== $machine->refrigerant) throw new InvalidArgumentException('Operating component refrigerant mismatch');
				$specs[$component->instance_key] = $spec;
			}
		}
		$states = array_merge($model->enabled_components, $model->isolated_components); $expected = array_keys($components); sort($states); sort($expected);
		if($states !== $expected) throw new InvalidArgumentException('Every physical component needs an explicit operating state');
		$modeEntries = array_values(array_filter($machine->mode_topologies, fn($m) => $m->mode === $model->mode && $m->release_status !== 'unsupported'));
		if(count($modeEntries) !== 1) throw new InvalidArgumentException('Operating model mode mismatch');
		$load = in_array($model->mode, ['heat_pump', 'full_heat_recovery'], true) ? $model->condenser->loop_key : $model->evaporator->loop_key;
		if($modeEntries[0]->load_loop_key !== $load) throw new InvalidArgumentException('Operating useful loop mismatch');
		$used = $usedLoops = [];
		foreach(['evaporator', 'condenser', 'recovery'] as $side) if($model->$side !== null) {
			$a = $model->$side; $key = $a->instance_key; $component = $components[$key] ?? null;
			if(!$component || isset($used[$key]) || !in_array($key, $model->enabled_components, true) || !in_array($component->role, ['evaporator', 'water_condenser', 'recovery_exchanger'], true)) throw new InvalidArgumentException('Invalid operating exchanger assignment'); $used[$key] = true;
			$spec = $specs[$key]->data;
			if(!in_array($a->section_key, $spec->specification->sections, true) || count($spec->specification->sections) !== 1) throw new InvalidArgumentException('Operating mode requires one physical exchanger section');
			$this->direction($component->ports, $key, $a->inlet_port, true); $this->direction($component->ports, $key, $a->outlet_port, false);
			$refPorts = array_keys(array_filter((array)$component->ports, fn($kind) => str_starts_with($kind, 'refrigerant_'))); $assigned = [$a->inlet_port, $a->outlet_port]; $actual = array_map(fn($p) => $key.'.'.$p, $refPorts); sort($assigned); sort($actual);
			if($assigned !== $actual) throw new InvalidArgumentException('Operating exchanger port coverage mismatch');
			$loops = array_values(array_filter($machine->hydraulic_loops, fn($l) => $l->loop_key === $a->loop_key));
			if(count($loops) !== 1) throw new InvalidArgumentException('Unknown operating fluid loop');
			if(isset($usedLoops[$a->loop_key])) throw new InvalidArgumentException('Serial thermally coupled operating exchangers are not supported'); $usedLoops[$a->loop_key] = true;
			if(isset($spec->specification->thermal_map_ref)) {
				$map = $h->resolve($resolve, $spec->specification->thermal_map_ref, 'heat_exchanger_map'); $this->schema->exchangerBinding($spec, $map->data);
				if($map->data->contract_version >= 2) {
					if($loops[0]->fluid_dataset === null) throw new InvalidArgumentException('Operating liquid revision required');
					$h->sameFluid($map->data->fluid_ref, $h->resolve($resolve, $loops[0]->fluid_dataset, 'fluid_properties'));
				}
			}
			$in = $out = false;
			foreach($component->ports as $port => $kind) foreach($loops[0]->edges as $edge) { if($kind === 'liquid_in' && $edge[1] === $key.'.'.$port) $in = true; if($kind === 'liquid_out' && $edge[0] === $key.'.'.$port) $out = true; }
			if(!$in || !$out) throw new InvalidArgumentException('Operating exchanger is outside its liquid loop');
			if(($side === 'recovery' || ($side === 'condenser' && $model->mode === 'full_heat_recovery')) && $component->role !== 'recovery_exchanger') throw new InvalidArgumentException('Dedicated recovery exchanger required');
		}
		$valves = []; foreach($model->valve_states as $state) $valves[$state->instance_key] = $state->state_key;
		$expectedValves = [];
		foreach($specs as $key => $spec) if(isset($spec->data->specification->refrigerant_valve)) {
			$expectedValves[] = $key;
			if(!in_array($key, $model->enabled_components, true) || !isset($valves[$key]) || count(array_filter($spec->data->specification->refrigerant_valve->states, fn($v) => $v->state_key === $valves[$key])) !== 1) throw new InvalidArgumentException('Missing operating valve state');
		}
		$actual = array_keys($valves); sort($actual); sort($expectedValves); if($actual !== $expectedValves) throw new InvalidArgumentException('Unknown operating valve');
		foreach($components as $key => $component) if(in_array($key, $model->enabled_components, true)) foreach($component->ports as $kind) if(str_starts_with($kind, 'refrigerant_') && $component->role !== 'compressor' && !isset($used[$key]) && !isset($valves[$key])) {
			throw new InvalidArgumentException('Unassigned enabled refrigerant component');
		}
		$c = $machine->circuits[0]; $exp = $c->expansion_device;
		$this->direction($exp->ports, $exp->device_key, $model->expansion->inlet_port, true); $this->direction($exp->ports, $exp->device_key, $model->expansion->outlet_port, false);
		if($model->expansion->inlet_port === $model->expansion->outlet_port) throw new InvalidArgumentException('Expansion flow direction missing');
		$stages = []; foreach($machine->stages as $stage) $stages[$stage->stage_key] = $stage;
		foreach($model->stage_keys as $key) if(!isset($stages[$key]) || array_diff($stages[$key]->active_compressors, $model->enabled_components)) throw new InvalidArgumentException('Stage not supported by operating topology');
		if($model->contract_version === 2) foreach($model->stage_keys as $key) $this->topology($machine, $model, $specs, $stages[$key]->active_compressors);
		foreach($model->points ?? [] as $point) {
			$expected = array_values(array_diff(array_merge(array_keys($components), [$exp->device_key]), $stages[$point->stage_key]->active_compressors)); $actual = array_map(fn($p) => $p->instance_key, $point->power_boundary->inputs); sort($actual); sort($expected);
			if($actual !== $expected) throw new InvalidArgumentException('Electrical boundary must cover auxiliary and inactive inputs explicitly');
			$this->topology($machine, $model, $specs, $stages[$point->stage_key]->active_compressors);
		}
		$boundary = $h->resolve($resolve, $model->energy_boundary_ref, 'thermal_balance_model');
		if($boundary->data->contract_version !== 1 || $boundary->data->circuit_key !== $model->circuit_key) throw new InvalidArgumentException('Operating energy boundary mismatch');
		return compact('components', 'specs', 'boundary');
	}

	private function direction(stdClass $ports, string $instance, string $port, bool $inlet): void
	{
		$prefix = $instance.'.';
		if(!str_starts_with($port, $prefix) || !in_array($ports->{substr($port, strlen($prefix))} ?? '', [$inlet ? 'refrigerant_in' : 'refrigerant_out', 'refrigerant_bidirectional'], true)) throw new InvalidArgumentException('Invalid operating refrigerant direction');
	}

	private function topology(stdClass $machine, stdClass $model, array $specs, array $compressors): void
	{
		$adj = []; $connect = function($a, $b) use (&$adj) { $adj[$a][] = $b; $adj[$b][] = $a; };
		foreach($machine->circuits[0]->refrigerant_connections as [$a, $b]) $connect($a, $b);
		$valvePorts = [];
		foreach($model->valve_states as $v) {
			$state = array_values(array_filter($specs[$v->instance_key]->data->specification->refrigerant_valve->states, fn($s) => $s->state_key === $v->state_key))[0];
			foreach($state->connections as [$a, $b]) { $a = $v->instance_key.'.'.$a; $b = $v->instance_key.'.'.$b; $connect($a, $b); $valvePorts[] = $a; }
		}
		$groups = []; $seen = [];
		foreach(array_keys($adj) as $port) if(!isset($seen[$port])) {
			$queue = [$port]; $key = count($groups); $groups[$key] = [];
			while($queue) { $p = array_pop($queue); if(isset($seen[$p])) continue; $seen[$p] = $key; $groups[$key][] = $p; foreach($adj[$p] ?? [] as $next) if(!isset($seen[$next])) $queue[] = $next; }
		}
		$e = $model->evaporator; $c = $model->condenser; $r = $model->recovery; $x = $model->expansion;
		$expected = [[$e->outlet_port], [$r !== null ? $r->inlet_port : $c->inlet_port], [$c->outlet_port, $x->inlet_port], [$x->outlet_port, $e->inlet_port]];
		foreach($compressors as $key) { $expected[0][] = $key.'.suction'; $expected[1][] = $key.'.discharge'; }
		if($r !== null) $expected[] = [$r->outlet_port, $c->inlet_port];
		$terminals = array_merge(...$expected); $used = [];
		foreach($groups as $group) { $edges = 0; foreach($group as $port) $edges += count($adj[$port]); if($edges !== 2 * (count($group) - 1)) throw new InvalidArgumentException('Cyclic refrigerant pressure headers are not supported'); }
		foreach($expected as $required) {
			$key = $seen[$required[0]] ?? null;
			if($key === null || isset($used[$key])) throw new InvalidArgumentException('Operating refrigerant path disconnected or short-circuited');
			$actual = array_values(array_intersect($groups[$key], $terminals)); sort($actual); sort($required);
			if($actual !== $required) throw new InvalidArgumentException('Operating refrigerant path or valve position contradicts exchanger roles'); $used[$key] = true;
		}
		foreach($valvePorts as $port) if(!isset($used[$seen[$port]])) throw new InvalidArgumentException('Unused refrigerant valve route');
		foreach($model->isolated_components as $key) foreach($specs[$key]->data->ports ?? [] as $port => $kind) if(str_starts_with($kind, 'refrigerant_') && isset($used[$seen[$key.'.'.$port] ?? -1])) throw new InvalidArgumentException('Inactive exchanger is not isolated');
	}

	public function composition(stdClass $value): stdClass { return $this->schema->fields($value, ['basis' => ['mass_fraction', 'volume_fraction'], 'fraction' => 'nonnegative', 'volume_reference_degC' => '?number']); }
	private function unique(array $values): void { if(count($values) !== count(array_unique($values))) throw new InvalidArgumentException('Duplicate operating identity'); }
}
