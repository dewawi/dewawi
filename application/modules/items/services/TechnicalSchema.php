<?php

class Items_Service_TechnicalSchema
{
	public const ROLES = ['compressor', 'evaporator', 'air_condenser', 'water_condenser', 'pump', 'fan', 'recovery_exchanger', 'tank', 'technical_option', 'machine'];
	public const TYPES = ['compressor_polynomial', 'machine_configuration', 'machine_family', 'component_spec', 'reference_points', 'heat_exchanger_map', 'thermal_balance_model'];
	public const QUANTITIES = ['cooling_capacity' => ['W', 'kW'], 'power_input' => ['W', 'kW'], 'current' => ['A'], 'refrigerant_mass_flow' => ['kg/h', 'kg/s'], 'discharge_temperature' => ['degC']];

	public function fields($value, array $required, array $optional = []): stdClass
	{
		if(!$value instanceof stdClass) throw new InvalidArgumentException('Expected a technical object');
		$actual = get_object_vars($value);
		if(array_diff(array_keys($required), array_keys($actual)) || array_diff(array_keys($actual), array_keys($required + $optional))) throw new InvalidArgumentException('Missing or unknown technical field');
		$result = new stdClass();
		foreach($required + $optional as $key => $type) if(array_key_exists($key, $actual)) $result->$key = $this->typed($actual[$key], $type);
		return $result;
	}

	private function typed($value, $type)
	{
		if(is_array($type)) {
			if(!in_array($value, $type, true)) throw new InvalidArgumentException('Unknown technical enumeration');
			return $value;
		}
		if(str_starts_with($type, '?')) return $value === null ? null : $this->typed($value, substr($type, 1));
		if(str_starts_with($type, 'list:')) {
			if(!is_array($value) || !array_is_list($value) || count($value) > 5000) throw new InvalidArgumentException('Invalid technical list');
			return array_map(fn($v) => $this->typed($v, substr($type, 5)), $value);
		}
		if($type === 'object') {
			if(!$value instanceof stdClass) throw new InvalidArgumentException('Expected technical object');
			return clone $value;
		}
		if($type === 'bool') {
			if(!is_bool($value)) throw new InvalidArgumentException('Expected technical boolean');
			return $value;
		}
		if($type === 'int' || $type === 'uint') {
			if(!(is_string($value) || is_int($value)) || !preg_match('/^[0-9]+$/D', (string)$value) || strlen((string)$value) > 10 || (float)$value > 2147483647 || (int)$value < ($type === 'int' ? 1 : 0)) throw new InvalidArgumentException('Invalid technical integer');
			return (int)$value;
		}
		if(in_array($type, ['number', 'positive', 'nonnegative'], true)) {
			$value = Items_Service_TechnicalJson::decimal($value);
			if(($type === 'positive' && (float)$value <= 0) || ($type === 'nonnegative' && (float)$value < 0)) throw new InvalidArgumentException('Invalid technical numeric range');
			return $value;
		}
		if($type === 'ref') return $this->fields($value, ['dataset_key' => 'key', 'revision' => 'int']);
		if(!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || preg_match('/[\x{10000}-\x{10ffff}]/u', $value)) throw new InvalidArgumentException('Invalid technical text');
		if($type === 'key') {
			if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9:_.-]{0,127}$/D', $value)) throw new InvalidArgumentException('Invalid technical identifier');
		} elseif($type === 'hash') {
			if(!preg_match('/^[a-f0-9]{64}$/D', $value)) throw new InvalidArgumentException('Invalid technical hash');
		} elseif(trim($value) === '' || mb_strlen($value) > ($type === 'longtext' ? 4096 : ($type === 'revision_text' ? 128 : 255))) throw new InvalidArgumentException('Invalid technical text length');
		return $value;
	}

	public function source($source): stdClass
	{
		$s = $this->fields($source, ['filename' => 'text', 'sha256' => 'hash'], ['source_key' => 'key', 'locator' => 'text', 'sheet' => 'text', 'software' => 'text', 'revision' => '?text', 'rights_reference' => '?text', 'conflicts' => 'list:object']);
		if(!isset($s->locator) && !isset($s->sheet)) throw new InvalidArgumentException('Technical source locator is required');
		if(isset($s->conflicts)) foreach($s->conflicts as &$conflict) $conflict = $this->fields($conflict, ['field' => 'key', 'cell' => 'key', 'reported' => '?number', 'source' => '?number']);
		return $s;
	}

	public function dataset($record): stdClass
	{
		if($this->entries($record) > 5000 || strlen(Items_Service_TechnicalJson::encode($record)) > 1048576) throw new InvalidArgumentException('Technical package budget exceeded');
		$d = $this->fields($record, ['dataset_key' => 'key', 'revision' => 'int', 'dataset_type' => self::TYPES, 'schema_version' => 'int', 'requested_status' => ['draft'], 'manufacturer_name' => '?text', 'manufacturer_revision' => '?revision_text', 'data_owner' => '?text', 'source' => 'object', 'data' => 'object'], ['blockers' => 'list:key', 'supersedes' => '?ref', 'sources' => 'list:object', 'manufacturer_ref' => 'key', 'import_context' => 'object']);
		if($d->schema_version !== 1) throw new InvalidArgumentException('Unsupported technical schema version');
		$d->source = $this->source($d->source);
		if(isset($d->sources)) foreach($d->sources as &$source) $source = $this->source($source);
		if(isset($d->import_context)) $d->import_context = $this->fields($d->import_context, ['template_kind' => '?text', 'provenance_note' => '?longtext', 'production_import_allowed' => '?bool']);
		$d->blockers = $d->blockers ?? [];
		if(count($d->blockers) !== count(array_unique($d->blockers))) throw new InvalidArgumentException('Duplicate technical blocker');
		switch($d->dataset_type) {
			case 'compressor_polynomial': $d->data = $this->polynomial($d->data); break;
			case 'machine_family': $d->data = $this->family($d->data); break;
			case 'machine_configuration': $d->data = $this->machine($d->data); break;
			case 'component_spec': $d->data = $this->component($d->data); break;
			case 'reference_points': $d->data = $this->references($d->data); break;
			case 'heat_exchanger_map': $d->data = $this->heatExchangerMap($d->data); break;
			case 'thermal_balance_model': $d->data = $this->thermalBalanceModel($d->data); break;
		}
		if(strlen(Items_Service_TechnicalJson::encode($d)) > 1048576 || $this->entries($d) > 5000) throw new InvalidArgumentException('Technical package budget exceeded');
		return $d;
	}

	private function entries($v): int
	{
		if(!is_array($v) && !is_object($v)) return 0;
		$count = count((array)$v);
		foreach($v as $child) $count += $this->entries($child);
		return $count;
	}

	private function electrical($value): stdClass
	{
		return $this->fields($value, ['voltage_V' => '?positive', 'phases' => '?int', 'execution_code' => '?text']);
	}

	private function polynomial($data): stdClass
	{
		$d = $this->fields($data, ['model' => 'text', 'refrigerant' => 'key', 'equation_id' => ['te_tc_cubic_c0_c9_v1'], 'temperature_unit' => ['degC'], 'temperature_basis' => 'object', 'conditions' => 'object', 'electrical' => 'object', 'operating_envelope' => '?object', 'quantities' => 'object'], ['condition_set' => 'text']);
		$d->temperature_basis = $this->fields($d->temperature_basis, ['Te' => '?text', 'Tc' => '?text']);
		foreach($d->temperature_basis as $v) if($v !== null && !in_array($v, ['dew', 'bubble', 'midpoint', 'pure_saturation'], true)) throw new InvalidArgumentException('Unknown saturation temperature basis');
		$d->conditions = $this->fields($d->conditions, ['speed_rpm' => '?positive', 'frequency_Hz' => '?positive', 'superheat_K' => '?nonnegative', 'return_gas_temperature_degC' => '?number', 'subcooling_K' => '?nonnegative', 'suction_condition_mode' => ['superheat', 'RGT']]);
		if($d->conditions->suction_condition_mode === 'superheat' && $d->conditions->superheat_K === null) throw new InvalidArgumentException('Superheat condition is required');
		if($d->conditions->suction_condition_mode === 'RGT' && $d->conditions->return_gas_temperature_degC === null) throw new InvalidArgumentException('RGT condition is required');
		$d->electrical = $this->electrical($d->electrical);
		if($d->operating_envelope !== null) {
			$d->operating_envelope = $this->fields($d->operating_envelope, ['kind' => ['te_tc_polygon'], 'unit' => ['degC'], 'vertices' => 'list:object', 'source_locator' => 'text']);
			if(count($d->operating_envelope->vertices) < 3) throw new InvalidArgumentException('Incomplete operating envelope');
			$seen = [];
			foreach($d->operating_envelope->vertices as &$vertex) {
				$vertex = $this->fields($vertex, ['Te' => 'number', 'Tc' => 'number']);
				$key = Items_Service_TechnicalJson::encode($vertex);
				if(isset($seen[$key]) || (float)$vertex->Te >= (float)$vertex->Tc) throw new InvalidArgumentException('Invalid envelope vertex');
				$seen[$key] = true;
			}
			$vertices = $d->operating_envelope->vertices; $area = 0.0;
			foreach($vertices as $i => $a) {
				$b = $vertices[($i + 1) % count($vertices)];
				$area += (float)$a->Te * (float)$b->Tc - (float)$b->Te * (float)$a->Tc;
			}
			if(!is_finite($area) || $area == 0.0) throw new InvalidArgumentException('Degenerate operating envelope');
		}
		$q = get_object_vars($d->quantities);
		if(!isset($q['cooling_capacity'], $q['power_input']) || array_diff(array_keys($q), array_keys(self::QUANTITIES))) throw new InvalidArgumentException('Missing or unknown polynomial quantity');
		foreach($q as $name => $value) {
			$value = $this->fields($value, ['unit' => self::QUANTITIES[$name], 'coefficients_C0_to_C9' => 'list:number', 'source_range' => 'text']);
			if(count($value->coefficients_C0_to_C9) !== 10) throw new InvalidArgumentException('Exactly ten polynomial coefficients are required');
			$d->quantities->$name = $value;
		}
		return $d;
	}

	private function family($data): stdClass
	{
		$d = $this->fields($data, ['family_key' => 'key', 'title' => 'text', 'chassis' => 'list:object']);
		if(!$d->chassis) throw new InvalidArgumentException('At least one chassis is required');
		$seen = [];
		foreach($d->chassis as &$chassis) {
			$chassis = $this->fields($chassis, ['chassis_key' => 'key', 'dimensions' => 'object', 'installation_kinds' => 'list:key', 'structural_limits_dataset' => '?ref']);
			$chassis->dimensions = $this->fields($chassis->dimensions, ['length_mm' => '?positive', 'width_mm' => '?positive', 'height_mm' => '?positive']);
			if(isset($seen[$chassis->chassis_key]) || !$chassis->installation_kinds || array_diff($chassis->installation_kinds, ['indoor', 'outdoor', 'split'])) throw new InvalidArgumentException('Invalid chassis definition');
			$seen[$chassis->chassis_key] = true;
		}
		return $d;
	}

	private function ports($value): stdClass
	{
		if(!$value instanceof stdClass || !get_object_vars($value)) throw new InvalidArgumentException('Component ports required');
		foreach($value as $key => $type) {
			$this->typed($key, 'key');
			if(!in_array($type, ['refrigerant_in', 'refrigerant_out', 'liquid_in', 'liquid_out', 'air_in', 'air_out'], true)) throw new InvalidArgumentException('Unknown technical port type');
		}
		return clone $value;
	}

	private function component($data): stdClass
	{
		$d = $this->fields($data, ['component_key' => 'key', 'role' => array_values(array_diff(self::ROLES, ['machine'])), 'model' => 'text', 'refrigerant' => '?key', 'electrical' => '?object', 'ports' => 'object', 'specification' => 'object']);
		$d->ports = $this->ports($d->ports);
		if($d->electrical !== null) $d->electrical = $this->electrical($d->electrical);
		$hx = ['exchanger_type' => ['plate', 'shell_tube', 'finned_coil', 'unconfirmed'], 'sections' => 'list:key', 'thermal_map_ref' => '?ref', 'pressure_loss_ref' => '?ref', 'flow_min_m3_h' => '?nonnegative', 'flow_max_m3_h' => '?positive', 'pressure_max_kPa' => '?positive'];
		switch($d->role) {
			case 'evaporator': case 'air_condenser': case 'water_condenser': case 'recovery_exchanger': $spec = $hx; break;
			case 'pump': $spec = ['curve_ref' => '?ref', 'curve_definition' => ['gross', 'net'], 'speed_rpm' => '?positive', 'frequency_Hz' => '?positive', 'fluid_ref' => '?ref', 'efficiency_map_ref' => '?ref', 'power_input_ref' => '?ref']; break;
			case 'fan': $spec = ['air_curve_ref' => '?ref', 'power_input_ref' => '?ref', 'noise_dataset_ref' => '?ref', 'control_modes' => 'list:key']; break;
			case 'tank': $spec = ['tank_kind' => ['open', 'pressurized', 'unconfirmed'], 'nominal_volume_L' => '?positive', 'usable_volume_L' => '?positive', 'pressure_max_kPa' => '?positive', 'material' => '?text']; break;
			case 'compressor': $spec = ['polynomial_ref' => '?ref', 'oil_return_stage_limits_ref' => '?ref']; break;
			case 'technical_option': $spec = ['effect' => ['unknown', 'neutral_verified', 'add', 'replace', 'restrict'], 'effect_dataset_ref' => '?ref', 'source_locator' => '?text']; break;
		}
		$d->specification = $this->fields($d->specification, $spec);
		if(isset($d->specification->flow_min_m3_h, $d->specification->flow_max_m3_h) && (float)$d->specification->flow_min_m3_h > (float)$d->specification->flow_max_m3_h) throw new InvalidArgumentException('Reversed flow limits');
		if(isset($d->specification->usable_volume_L, $d->specification->nominal_volume_L) && (float)$d->specification->usable_volume_L > (float)$d->specification->nominal_volume_L) throw new InvalidArgumentException('Usable volume exceeds nominal volume');
		return $d;
	}

	private function thermalRange($value, string $unit, bool $positive = false): stdClass
	{
		$r = $this->fields($value, ['min' => $positive ? 'positive' : 'number', 'max' => $positive ? 'positive' : 'number', 'unit' => [$unit]]);
		if(Items_Service_TechnicalJson::compareDecimal($r->min, $r->max) > 0 || ($unit === 'degC' && (float)$r->min <= -273.15)) throw new InvalidArgumentException('Invalid thermal range');
		return $r;
	}

	private function heatExchangerMap($data): stdClass
	{
		$d = $this->fields($data, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'component_key' => 'key', 'model' => 'text', 'role' => ['evaporator', 'air_condenser', 'water_condenser'],
			'exchanger_type' => ['plate', 'shell_tube', 'finned_coil'], 'refrigerant' => 'key', 'temperature_basis' => ['dew', 'bubble', 'midpoint', 'pure_saturation'],
			'model_id' => ['synthetic_evaporator_effectiveness_v1', 'synthetic_air_condenser_effectiveness_v1', 'synthetic_water_condenser_effectiveness_v1'],
			'measurement_boundary' => ['secondary_stream_only_no_external_heat_v1'], 'interpolation' => ['none_analytic'], 'variables' => 'object',
			'refrigerant_sections_are_isolated' => 'bool', 'media' => 'list:object', 'sections' => 'list:object', 'fan_condition' => '?object', 'restrictions' => 'list:key', 'missing_evidence' => 'list:key']);
		if($d->contract_version !== 1 || $d->model_id !== 'synthetic_'.$d->role.'_effectiveness_v1') throw new InvalidArgumentException('Unsupported exchanger model contract');
		$d->variables = $this->fields($d->variables, ['saturation_temperature' => ['degC'], 'secondary_inlet' => ['degC'], 'secondary_outlet' => ['degC'], 'volume_flow' => ['m3/h'], 'heat_flow' => ['kW'], 'pressure_drop' => ['kPa']]);
		$seen = [];
		foreach($d->media as &$medium) {
			$medium = $this->fields($medium, ['medium_key' => 'key', 'phase' => ['single_phase_liquid', 'dry_gas'], 'composition' => 'text', 'properties' => 'object']);
			if(isset($seen[$medium->medium_key]) || ($medium->phase === 'dry_gas') !== ($d->role === 'air_condenser')) throw new InvalidArgumentException('Invalid exchanger medium');
			$seen[$medium->medium_key] = true;
			$medium->properties = $this->fields($medium->properties, ['model_id' => ['synthetic_constant_cp_density_v1'], 'density_kg_m3' => '?positive', 'specific_heat_kJ_kg_K' => '?positive', 'temperature_range' => 'object', 'source_locator' => 'text']);
			$medium->properties->temperature_range = $this->thermalRange($medium->properties->temperature_range, 'degC');
		}
		unset($medium); $seen = [];
		foreach($d->sections as &$section) {
			$section = $this->fields($section, ['section_key' => 'key', 'effectiveness' => 'positive', 'limits' => 'object', 'pressure_drop_kPa' => '?nonnegative']);
			if(isset($seen[$section->section_key]) || (float)$section->effectiveness >= 1.0) throw new InvalidArgumentException('Invalid exchanger section');
			$seen[$section->section_key] = true;
			$section->limits = $this->fields($section->limits, ['saturation_temperature' => 'object', 'secondary_inlet' => 'object', 'secondary_outlet' => 'object', 'volume_flow' => 'object']);
			foreach(['saturation_temperature', 'secondary_inlet', 'secondary_outlet'] as $key) $section->limits->$key = $this->thermalRange($section->limits->$key, 'degC');
			$section->limits->volume_flow = $this->thermalRange($section->limits->volume_flow, 'm3/h', true);
		}
		if(!$d->sections || !$d->media || (count($d->sections) > 1 && !$d->refrigerant_sections_are_isolated)) throw new InvalidArgumentException('Missing or nonisolated exchanger sections');
		if($d->fan_condition !== null) $d->fan_condition = $this->fields($d->fan_condition, ['execution_code' => 'text', 'speed_rpm' => 'positive']);
		if($d->role !== 'air_condenser' && $d->fan_condition !== null) throw new InvalidArgumentException('Liquid exchanger cannot define air fan conditions');
		return $d;
	}

	private function thermalBalanceModel($data): stdClass
	{
		if(isset($data->contract_version) && (string)$data->contract_version === '2') return $this->parallelThermalModel($data);
		$d = $this->fields($data, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'circuit_key' => 'key', 'energy_balance' => 'object', 'search_bounds' => 'object', 'numerics' => 'object', 'missing_evidence' => 'list:key']);
		if($d->contract_version !== 1) throw new InvalidArgumentException('Unsupported thermal balance contract');
		$d->energy_balance = $this->fields($d->energy_balance, ['model_id' => ['synthetic_hermetic_no_external_losses_v1'], 'boundary' => ['evaporator_plus_electrical_input_to_condenser_v1'], 'motor_heat_to_refrigerant_fraction' => 'number', 'external_heat_loss_kW' => 'number', 'transient_storage_kW' => 'number', 'other_heat_input_kW' => 'number']);
		$b = $d->energy_balance;
		if($b->motor_heat_to_refrigerant_fraction !== '1' || $b->external_heat_loss_kW !== '0' || $b->transient_storage_kW !== '0' || $b->other_heat_input_kW !== '0') throw new InvalidArgumentException('Contradictory synthetic energy balance');
		$d->search_bounds = $this->fields($d->search_bounds, ['Te' => 'object', 'Tc' => 'object']);
		foreach(['Te', 'Tc'] as $key) {
			$d->search_bounds->$key = $this->thermalRange($d->search_bounds->$key, 'degC');
			if((float)$d->search_bounds->$key->min >= (float)$d->search_bounds->$key->max) throw new InvalidArgumentException('Empty thermal search interval');
		}
		if((float)$d->search_bounds->Te->max >= (float)$d->search_bounds->Tc->min) throw new InvalidArgumentException('Thermal search temperatures overlap');
		$d->numerics = $this->fields($d->numerics, ['heat_tolerance_kW' => 'positive', 'temperature_tolerance_K' => 'positive', 'max_outer_iterations' => 'int', 'max_inner_iterations' => 'int']);
		foreach(['heat_tolerance_kW', 'temperature_tolerance_K'] as $key) if((float)$d->numerics->$key < 1e-9 || (float)$d->numerics->$key > 1e-3) throw new InvalidArgumentException('Unsupported thermal tolerance');
		foreach(['max_outer_iterations', 'max_inner_iterations'] as $key) if($d->numerics->$key > 80) throw new InvalidArgumentException('Thermal iteration budget exceeds contract');
		return $d;
	}

	private function parallelThermalModel($data): stdClass
	{
		$d = $this->fields($data, ['contract_version' => 'int', 'usage' => ['synthetic_test_only'], 'model_id' => ['synthetic_fixed_inlet_parallel_sections_v1'], 'circuits' => 'list:object', 'secondary_groups' => 'list:object',
			'inactive_section_model_id' => ['synthetic_zero_heat_pass_through_v1'], 'variables' => 'object', 'numerics' => 'object', 'missing_evidence' => 'list:key']);
		if($d->contract_version !== 2 || !$d->circuits || !$d->secondary_groups) throw new InvalidArgumentException('Incomplete parallel thermal model');
		$d->variables = $this->fields($d->variables, ['temperature' => ['degC'], 'volume_flow' => ['m3/h'], 'heat_flow' => ['kW'], 'capacity_rate' => ['kW/K']]);
		$d->numerics = $this->fields($d->numerics, ['heat_tolerance_kW' => 'positive', 'temperature_tolerance_K' => 'positive']);
		foreach($d->numerics as $tolerance) if((float)$tolerance < 1e-9 || (float)$tolerance > 1e-3) throw new InvalidArgumentException('Unsupported parallel thermal tolerance');
		$seen = [];
		foreach($d->circuits as &$circuit) {
			$circuit = $this->fields($circuit, ['circuit_key' => 'key', 'circuit_model_ref' => 'ref']);
			if(isset($seen[$circuit->circuit_key])) throw new InvalidArgumentException('Duplicate thermal circuit assignment');
			$seen[$circuit->circuit_key] = true;
		}
		unset($circuit); $seen = [];
		foreach($d->secondary_groups as &$group) {
			$group = $this->fields($group, ['group_key' => 'key', 'topology_kind' => ['single_section_boundary_v1', 'parallel_sections_common_inlet_v1'], 'mixing_model_id' => ['synthetic_constant_properties_parallel_v1'],
				'side' => ['evaporator', 'condenser'], 'instance_key' => 'key', 'hydraulic_loop_key' => 'key', 'inlet_port' => 'key', 'outlet_port' => 'key', 'medium_key' => 'key', 'total_volume_flow_m3_h' => '?positive', 'branches' => 'list:object', 'limits' => 'object']);
			if(isset($seen[$group->group_key]) || count($group->branches) !== ($group->topology_kind === 'single_section_boundary_v1' ? 1 : 2)) throw new InvalidArgumentException('Invalid parallel secondary group');
			$seen[$group->group_key] = true;
			$group->limits = $this->fields($group->limits, ['secondary_inlet' => 'object', 'mixed_outlet' => 'object', 'total_volume_flow' => 'object']);
			foreach(['secondary_inlet', 'mixed_outlet'] as $key) $group->limits->$key = $this->thermalRange($group->limits->$key, 'degC');
			$group->limits->total_volume_flow = $this->thermalRange($group->limits->total_volume_flow, 'm3/h', true);
			$sum = '0'; $complete = $group->total_volume_flow_m3_h !== null; $sections = [];
			foreach($group->branches as &$branch) {
				$branch = $this->fields($branch, ['circuit_key' => 'key', 'section_key' => 'key', 'volume_flow_m3_h' => '?positive']);
				if(isset($sections[$branch->section_key])) throw new InvalidArgumentException('Duplicate secondary section');
				$sections[$branch->section_key] = true;
				if($branch->volume_flow_m3_h === null) $complete = false;
				else $sum = Items_Service_TechnicalJson::addNonnegativeDecimals($sum, $branch->volume_flow_m3_h);
			}
			unset($branch);
			if($complete && $sum !== $group->total_volume_flow_m3_h) throw new InvalidArgumentException('Secondary branch flows do not equal the declared total');
		}
		return $d;
	}

	public function exchangerBinding(stdClass $data, stdClass $map): void
	{
		foreach(['component_key', 'model', 'role', 'refrigerant'] as $key) if($map->$key !== $data->$key) throw new InvalidArgumentException('Exchanger map component binding mismatch');
		$sections = array_map(fn($section) => $section->section_key, $map->sections); $expected = $data->specification->sections;
		sort($sections); sort($expected);
		if($sections !== $expected || $map->exchanger_type !== $data->specification->exchanger_type) throw new InvalidArgumentException('Exchanger map section or construction mismatch');
	}

	public function secondarySectionBinding(stdClass $group, array $sections): void
	{
		$expected = array_map(fn($branch) => $branch->section_key, $group->branches); sort($expected); sort($sections);
		if($sections !== $expected) throw new InvalidArgumentException('Secondary map section coverage mismatch');
	}

	public function thermalMachineBinding(stdClass $data, stdClass $model): void
	{
		$circuits = []; $components = []; $loops = [];
		foreach($data->circuits as $circuit) $circuits[$circuit->circuit_key] = $circuit;
		if($model->contract_version === 1) {
			if(!isset($circuits[$model->circuit_key])) throw new InvalidArgumentException('Thermal model circuit binding mismatch');
			return;
		}
		$actual = array_map(fn($c) => $c->circuit_key, $model->circuits); $expected = array_keys($circuits); sort($actual); sort($expected);
		if($actual !== $expected) throw new InvalidArgumentException('Thermal model circuit coverage mismatch');
		foreach($data->components as $component) $components[$component->instance_key] = $component;
		foreach($data->hydraulic_loops as $loop) $loops[$loop->loop_key] = $loop;
		$assignments = $devices = $usedLoops = [];
		foreach($model->secondary_groups as $group) {
			$component = $components[$group->instance_key] ?? null; $loop = $loops[$group->hydraulic_loop_key] ?? null;
			$role = $group->side === 'evaporator' ? 'evaporator' : 'water_condenser';
			if(!$component || $component->role !== $role || !$loop || isset($devices[$group->instance_key]) || isset($usedLoops[$group->hydraulic_loop_key])) throw new InvalidArgumentException('Unproven independent secondary group');
			$devices[$group->instance_key] = $usedLoops[$group->hydraulic_loop_key] = true;
			foreach(['inlet_port' => 'liquid_in', 'outlet_port' => 'liquid_out'] as $key => $type) {
				$prefix = $group->instance_key.'.'; $port = substr($group->$key, strlen($prefix));
				if(!str_starts_with($group->$key, $prefix) || ($component->ports->$port ?? null) !== $type || !in_array($group->$key, array_merge(...$loop->edges), true)) throw new InvalidArgumentException('Secondary boundary port mismatch');
			}
			$users = [];
			foreach($circuits as $key => $circuit) if(($group->side === 'evaporator' ? $circuit->evaporator_section->instance_key : $circuit->condenser_instance) === $group->instance_key) $users[] = $key;
			$declared = array_map(fn($b) => $b->circuit_key, $group->branches); sort($declared); sort($users);
			if($declared !== $users) throw new InvalidArgumentException('Secondary section user coverage mismatch');
			foreach($group->branches as $branch) {
				$circuit = $circuits[$branch->circuit_key] ?? null; $assignment = $branch->circuit_key.':'.$group->side;
				$section = $group->side === 'evaporator' ? ($circuit->evaporator_section->section_key ?? null) : ($circuit->condenser_section_key ?? $branch->section_key);
				if(!$circuit || isset($assignments[$assignment]) || $section !== $branch->section_key) throw new InvalidArgumentException('Thermal section assignment mismatch');
				$assignments[$assignment] = true;
			}
		}
		if(count($assignments) !== count($circuits) * 2) throw new InvalidArgumentException('Incomplete thermal secondary assignment');
	}

	public function reference($point): stdClass
	{
		$p = $this->fields($point, ['reference_key' => 'key', 'dataset_ref' => 'ref', 'reference_kind' => ['mathematical_polynomial', 'manufacturer_component', 'manufacturer_machine', 'measurement'], 'inputs' => 'object', 'quantity' => array_keys(self::QUANTITIES), 'expected_value' => 'number', 'unit' => 'text', 'absolute_tolerance' => '?nonnegative', 'relative_tolerance' => '?nonnegative', 'tolerance_origin' => 'text', 'source' => 'object', 'revision' => 'int', 'engineering_approval' => '?text'], ['machine_profile_ref' => '?object', 'stage_key' => '?key', 'mode' => ['cooling', 'partial_heat_recovery', 'full_heat_recovery', 'reversible_heating']]);
		$p->inputs = $this->fields($p->inputs, [], ['Te_degC' => 'number', 'Tc_degC' => 'number', 'supply_degC' => 'number', 'return_degC' => 'number', 'ambient_degC' => 'number', 'fluid_dataset_ref' => 'ref']);
		if(!get_object_vars($p->inputs) || ($p->absolute_tolerance === null && $p->relative_tolerance === null) || !in_array($p->unit, self::QUANTITIES[$p->quantity], true)) throw new InvalidArgumentException('Incomplete reference point');
		if(isset($p->machine_profile_ref)) $p->machine_profile_ref = $this->fields($p->machine_profile_ref, ['profile_key' => 'key', 'revision' => 'int']);
		$p->source = $this->source($p->source);
		return $p;
	}

	private function references($data): stdClass
	{
		$d = $this->fields($data, ['records' => 'list:object']);
		if(!$d->records) throw new InvalidArgumentException('Reference points required');
		$seen = [];
		foreach($d->records as &$p) {
			$p = $this->reference($p);
			$key = $p->reference_key.':'.$p->quantity;
			if(isset($seen[$key])) throw new InvalidArgumentException('Duplicate reference point');
			$seen[$key] = true;
		}
		return $d;
	}

	public function profile($data): stdClass
	{
		$p = $this->fields($data, ['profile_key' => 'key', 'revision' => 'int', 'item_ref' => 'key', 'dataset_ref' => 'ref', 'role' => self::ROLES, 'binding' => 'object'], ['supersedes' => '?object']);
		$p->binding = $this->fields($p->binding, ['model' => 'text', 'manufacturersku' => '?text', 'electrical' => '?object', 'included_option_refs' => 'list:key', 'extra_option_refs' => 'list:key', 'source_locator' => 'text', 'scope_key' => 'key'], ['base_item_ref' => '?key', 'manufacturer_ref' => 'key', 'source' => 'object']);
		if(isset($p->binding->source)) $p->binding->source = $this->source($p->binding->source);
		if($p->binding->electrical !== null) $p->binding->electrical = $this->electrical($p->binding->electrical);
		if(isset($p->supersedes)) $p->supersedes = $this->fields($p->supersedes, ['profile_key' => 'key', 'revision' => 'int']);
		return $p;
	}

	private function machine($data): stdClass
	{
		$d = $this->fields($data, ['family_ref' => 'ref', 'chassis_key' => 'key', 'machine_item_ref' => 'key', 'base_item_ref' => '?key', 'refrigerant' => 'key', 'circuit_count' => 'int', 'installation_kind' => ['indoor', 'outdoor', 'split'], 'cooling_kind' => ['air', 'water'], 'components' => 'list:object', 'circuits' => 'list:object', 'shared_components' => 'list:object', 'hydraulic_loops' => 'list:object', 'air_paths' => 'list:object', 'stages' => 'list:object', 'approved_stage_keys' => 'list:key', 'mode_topologies' => 'list:object', 'selected_equipment' => 'list:object', 'variant_signature' => 'object', 'approximation' => 'object'], ['thermal_model_ref' => '?ref']);
		$instances = $ports = [];
		foreach($d->components as &$component) {
			$component = $this->fields($component, ['instance_key' => 'key', 'role' => array_values(array_diff(self::ROLES, ['machine'])), 'component_key' => 'key', 'profile_key' => 'key', 'profile_revision' => 'int', 'ports' => 'object']);
			if(isset($instances[$component->instance_key])) throw new InvalidArgumentException('Duplicate physical component');
			$component->ports = $this->ports($component->ports);
			foreach($component->ports as $port => $type) $ports[$component->instance_key.'.'.$port] = $type;
			$instances[$component->instance_key] = $component;
		}
		$circuits = $assigned = $usedPorts = $users = [];
		foreach($d->circuits as &$circuit) {
			$circuit = $this->fields($circuit, ['circuit_key' => 'key', 'refrigerant' => 'key', 'compressor_instances' => 'list:key', 'topology_kind' => ['parallel_compressors_single_evaporator_single_condenser_v1'], 'refrigerant_groups' => 'list:object', 'expansion_device' => 'object', 'evaporator_section' => 'object', 'condenser_instance' => 'key', 'control_dataset' => '?ref'], ['condenser_section_key' => 'key']);
			$key = $circuit->circuit_key;
			if(isset($circuits[$key]) || $circuit->refrigerant !== $d->refrigerant || !$circuit->compressor_instances) throw new InvalidArgumentException('Invalid refrigerant circuit');
			$circuits[$key] = $circuit;
			$circuit->evaporator_section = $this->fields($circuit->evaporator_section, ['instance_key' => 'key', 'section_key' => 'key']);
			$evap = $circuit->evaporator_section->instance_key;
			$section = $circuit->evaporator_section->section_key;
			$cond = $circuit->condenser_instance;
			$condPort = $cond.'.'.(isset($circuit->condenser_section_key) ? $circuit->condenser_section_key.'_' : '');
			if(($instances[$evap]->role ?? '') !== 'evaporator' || ($instances[$cond]->role ?? '') !== ($d->cooling_kind === 'air' ? 'air_condenser' : 'water_condenser')) throw new InvalidArgumentException('Invalid circuit heat exchanger role');
			$users[$evap][] = $users[$cond][] = $key;
			foreach([$evap.'.'.$section.'_in' => 'refrigerant_in', $evap.'.'.$section.'_out' => 'refrigerant_out', $condPort.'in' => 'refrigerant_in', $condPort.'out' => 'refrigerant_out'] as $port => $type) if(($ports[$port] ?? null) !== $type) throw new InvalidArgumentException('Heat exchanger port direction mismatch');
			$circuit->expansion_device = $this->fields($circuit->expansion_device, ['device_key' => 'key', 'kind' => ['unconfirmed', 'electronic', 'thermostatic', 'fixed'], 'component_key' => '?key', 'profile_key' => '?key', 'ports' => 'object'], ['profile_revision' => 'int']);
			$exp = $circuit->expansion_device;
			if(isset($instances[$exp->device_key]) || isset($assigned[$exp->device_key])) throw new InvalidArgumentException('Duplicate expansion device');
			$assigned[$exp->device_key] = $key;
			$exp->ports = $this->fields($exp->ports, ['in' => ['refrigerant_in'], 'out' => ['refrigerant_out']]);
			foreach($exp->ports as $port => $type) $ports[$exp->device_key.'.'.$port] = $type;
			if(($exp->profile_key === null) !== ($exp->component_key === null) || ($exp->profile_key !== null && !isset($exp->profile_revision))) throw new InvalidArgumentException('Incomplete expansion profile reference');
			$expected = ['suction' => [$evap.'.'.$section.'_out'], 'discharge' => [$condPort.'in'], 'liquid' => [$condPort.'out', $exp->device_key.'.in'], 'expanded' => [$exp->device_key.'.out', $evap.'.'.$section.'_in']];
			foreach($circuit->compressor_instances as $compressor) {
				if(($instances[$compressor]->role ?? '') !== 'compressor' || isset($assigned[$compressor])) throw new InvalidArgumentException('Compressor must belong to exactly one circuit');
				if(($ports[$compressor.'.suction'] ?? null) !== 'refrigerant_in' || ($ports[$compressor.'.discharge'] ?? null) !== 'refrigerant_out') throw new InvalidArgumentException('Compressor port direction mismatch');
				$assigned[$compressor] = $key;
				$expected['suction'][] = $compressor.'.suction';
				$expected['discharge'][] = $compressor.'.discharge';
				$users[$compressor][] = $key;
			}
			$groups = [];
			foreach($circuit->refrigerant_groups as &$group) {
				$group = $this->fields($group, ['group_key' => ['suction', 'discharge', 'liquid', 'expanded'], 'members' => 'list:key']);
				if(isset($groups[$group->group_key])) throw new InvalidArgumentException('Duplicate refrigerant group');
				$members = $group->members;
				$required = $expected[$group->group_key];
				sort($members); sort($required);
				if($members !== $required) throw new InvalidArgumentException('Refrigerant topology does not match its circuit');
				$in = $out = 0;
				foreach($members as $port) {
					if(isset($usedPorts[$port]) || !in_array($ports[$port] ?? '', ['refrigerant_in', 'refrigerant_out'], true)) throw new InvalidArgumentException('Invalid or shared refrigerant port');
					$usedPorts[$port] = true;
					if($ports[$port] === 'refrigerant_in') $in++; else $out++;
				}
				if(!$in || !$out) throw new InvalidArgumentException('Refrigerant group direction mismatch');
				$groups[$group->group_key] = true;
			}
			if(count($groups) !== 4) throw new InvalidArgumentException('Incomplete circuit path');
		}
		if(count($circuits) !== $d->circuit_count || !$circuits) throw new InvalidArgumentException('Circuit count mismatch');
		foreach($instances as $key => $instance) {
			if($instance->role === 'compressor' && !isset($assigned[$key])) throw new InvalidArgumentException('Unassigned compressor');
			foreach($instance->ports as $port => $type) if(str_starts_with($type, 'refrigerant_') && !isset($usedPorts[$key.'.'.$port])) throw new InvalidArgumentException('Unconnected refrigerant port');
		}
		$loops = $liquidPorts = [];
		foreach($d->hydraulic_loops as &$loop) {
			$loop = $this->fields($loop, ['loop_key' => 'key', 'fluid_dataset' => '?ref', 'boundary_in' => 'key', 'boundary_out' => 'key', 'edges' => 'list:list:key', 'tank_kind' => '?text', 'external_pressure_definition' => ['external_to_machine'], 'pressure_loss_dataset' => '?ref'], ['external_requirement_kPa' => 'nonnegative', 'flow_min_m3_h' => 'nonnegative', 'flow_max_m3_h' => 'positive']);
			// Edge pairs are arrays in the version-one contract.
		}
		return $this->machinePaths($d, $instances, $ports, $users, $circuits);
	}

	private function machinePaths(stdClass $d, array $instances, array $ports, array $users, array $circuits): stdClass
	{
		$loops = $liquidPorts = $internal = [];
		foreach($instances as $instance) {
			$ins = $outs = [];
			foreach($instance->ports as $key => $type) {
				if($type === 'liquid_in') $ins[] = $instance->instance_key.'.'.$key;
				if($type === 'liquid_out') $outs[] = $instance->instance_key.'.'.$key;
			}
			if($ins || $outs) {
				if(count($ins) !== 1 || count($outs) !== 1) throw new InvalidArgumentException('D2B requires one defined liquid passage per component');
				$internal[$ins[0]] = $outs[0];
			}
		}
		foreach($d->hydraulic_loops as $loop) {
			if(isset($loops[$loop->loop_key]) || $loop->boundary_in === $loop->boundary_out || isset($ports[$loop->boundary_in]) || isset($ports[$loop->boundary_out])) throw new InvalidArgumentException('Invalid hydraulic boundary');
			if($loop->tank_kind !== null && !in_array($loop->tank_kind, ['open', 'pressurized'], true)) throw new InvalidArgumentException('Unknown tank topology');
			if(!$loop->edges) throw new InvalidArgumentException('Hydraulic edges required');
			$loops[$loop->loop_key] = $loop;
			$edges = [];
			foreach($loop->edges as $edge) {
				if(!is_array($edge) || count($edge) !== 2 || !is_string($edge[0]) || !is_string($edge[1])) throw new InvalidArgumentException('Invalid hydraulic edge');
				$edges[$edge[0]] = $edge[1];
				foreach($edge as $n => $port) {
					$boundary = $n === 0 ? $loop->boundary_in : $loop->boundary_out;
					$type = $n === 0 ? 'liquid_out' : 'liquid_in';
					if($port !== $boundary && ($ports[$port] ?? null) !== $type) throw new InvalidArgumentException('Hydraulic port direction mismatch');
					if(isset($liquidPorts[$port])) throw new InvalidArgumentException('Duplicate hydraulic port use');
					$liquidPorts[$port] = $loop->loop_key;
				}
			}
			$cursor = $loop->boundary_in; $traversed = [];
			while($cursor !== $loop->boundary_out) {
				if(isset($traversed[$cursor]) || !isset($edges[$cursor])) throw new InvalidArgumentException('Disconnected or cyclic hydraulic path');
				$traversed[$cursor] = true;
				$next = $edges[$cursor];
				$cursor = $next === $loop->boundary_out ? $next : ($internal[$next] ?? '');
			}
			if(count($traversed) !== count($edges)) throw new InvalidArgumentException('Disconnected hydraulic component');
		}
		foreach($ports as $port => $type) if(str_starts_with($type, 'liquid_') && !isset($liquidPorts[$port])) throw new InvalidArgumentException('Unconnected hydraulic port');
		$airPaths = $airUsers = [];
		foreach($d->air_paths as &$path) {
			$path = $this->fields($path, ['path_key' => 'key', 'topology_kind' => ['parallel_coils_common_fan'], 'ambient_in' => 'key', 'coil_instances' => 'list:key', 'fan_instance' => 'key', 'flow_distribution_dataset' => '?ref']);
			if(isset($airPaths[$path->path_key]) || !$path->coil_instances || ($instances[$path->fan_instance]->role ?? '') !== 'fan') throw new InvalidArgumentException('Invalid air path');
			$this->unique($path->coil_instances);
			if(isset($airUsers[$path->fan_instance]) || ($ports[$path->fan_instance.'.in'] ?? null) !== 'air_in' || ($ports[$path->fan_instance.'.out'] ?? null) !== 'air_out') throw new InvalidArgumentException('Invalid or duplicated physical fan');
			$airUsers[$path->fan_instance] = true;
			$fanUsers = [];
			foreach($path->coil_instances as $coil) {
				if(isset($airUsers[$coil]) || ($instances[$coil]->role ?? '') !== 'air_condenser' || ($ports[$coil.'.air_in'] ?? null) !== 'air_in' || ($ports[$coil.'.air_out'] ?? null) !== 'air_out') throw new InvalidArgumentException('Invalid air coil');
				$airUsers[$coil] = true;
				$fanUsers = array_merge($fanUsers, $users[$coil] ?? []);
			}
			$users[$path->fan_instance] = array_values(array_unique($fanUsers));
			$airPaths[$path->path_key] = $path;
		}
		foreach($instances as $key => $instance) if(in_array($instance->role, ['fan', 'air_condenser'], true) && !isset($airUsers[$key])) throw new InvalidArgumentException('Unassigned air component');
		if($d->cooling_kind === 'water' && ($d->air_paths || ($d->approximation->enabled ?? false))) throw new InvalidArgumentException('Water condenser cannot use an air approximation');
		$shared = [];
		foreach($d->shared_components as &$component) {
			$component = $this->fields($component, ['instance_key' => 'key'], ['shared_by_circuits' => 'list:key', 'refrigerant_sections_are_isolated' => 'bool', 'hydraulic_loop_key' => 'key', 'air_path_key' => 'key']);
			$key = $component->instance_key;
			if(isset($shared[$key]) || !isset($instances[$key])) throw new InvalidArgumentException('Duplicate or unknown shared component');
			if(count($users[$key] ?? []) > 1 && !isset($component->shared_by_circuits)) throw new InvalidArgumentException('Shared circuit users required');
			if(isset($component->shared_by_circuits)) {
				$expected = $users[$key] ?? [];
				$actual = $component->shared_by_circuits;
				sort($expected); sort($actual);
				if($actual !== $expected || count($actual) < 2) throw new InvalidArgumentException('Shared component users mismatch');
			}
			if(in_array($instances[$key]->role, ['evaporator', 'air_condenser', 'water_condenser'], true) && count($users[$key] ?? []) > 1 && ($component->refrigerant_sections_are_isolated ?? false) !== true) throw new InvalidArgumentException('Shared exchanger sections must be isolated');
			if(isset($component->hydraulic_loop_key) && !isset($loops[$component->hydraulic_loop_key])) throw new InvalidArgumentException('Unknown shared hydraulic loop');
			if(isset($component->air_path_key) && !isset($airPaths[$component->air_path_key])) throw new InvalidArgumentException('Unknown shared air path');
			$shared[$key] = true;
		}
		foreach($users as $key => $list) if(count($list) > 1 && !isset($shared[$key])) throw new InvalidArgumentException('Shared physical component must be declared');
		$stageKeys = $approved = [];
		foreach($d->stages as &$stage) {
			$stage = $this->fields($stage, ['stage_key' => 'key', 'active_compressors' => 'list:key', 'controls_ref' => '?ref', 'release_status' => ['draft', 'approved']]);
			$this->unique($stage->active_compressors);
			if(isset($stageKeys[$stage->stage_key]) || !$stage->active_compressors) throw new InvalidArgumentException('Invalid operating stage');
			foreach($stage->active_compressors as $key) if(($instances[$key]->role ?? '') !== 'compressor') throw new InvalidArgumentException('Unknown stage compressor');
			$stageKeys[$stage->stage_key] = true;
			if($stage->release_status === 'approved') $approved[] = $stage->stage_key;
		}
		$requested = $d->approved_stage_keys;
		sort($requested); sort($approved);
		if($requested !== $approved) throw new InvalidArgumentException('Approved stage list mismatch');
		$modes = [];
		foreach($d->mode_topologies as &$mode) {
			$mode = $this->fields($mode, ['mode' => ['cooling', 'partial_heat_recovery', 'full_heat_recovery', 'reversible_heating'], 'release_status' => ['draft', 'approved', 'unsupported'], 'topology_ref' => '?text'], ['circuit_keys' => 'list:key', 'load_loop_key' => 'key']);
			if(isset($modes[$mode->mode])) throw new InvalidArgumentException('Duplicate operating mode');
			if($mode->mode !== 'cooling' && ($mode->release_status !== 'unsupported' || $mode->topology_ref !== null)) throw new InvalidArgumentException('Unsupported thermal mode');
			if($mode->mode === 'cooling') {
				$actual = $mode->circuit_keys ?? []; $expected = array_keys($circuits);
				sort($actual); sort($expected);
				if($actual !== $expected || $mode->topology_ref !== 'self:cooling' || !isset($loops[$mode->load_loop_key ?? ''])) throw new InvalidArgumentException('Incomplete cooling mode');
			}
			$modes[$mode->mode] = true;
		}
		if(!isset($modes['cooling'])) throw new InvalidArgumentException('Cooling topology required');
		$d->variant_signature = $this->fields($d->variant_signature, ['included_option_refs' => 'list:key', 'extra_option_refs' => 'list:key']);
		$this->unique(array_merge($d->variant_signature->included_option_refs, $d->variant_signature->extra_option_refs));
		$optionKeys = [];
		foreach($d->selected_equipment as &$option) {
			$option = $this->fields($option, ['option_ref' => 'key', 'component_key' => 'key', 'technical_effect' => ['unknown', 'neutral_verified', 'add', 'replace', 'restrict'], 'target_instance_key' => 'key', 'replacement_component_key' => '?key', 'effect_dataset_ref' => '?ref', 'source_locator' => '?text', 'included' => 'bool'], ['option_group_ref' => 'key', 'component_item_ref' => '?key', 'base_item_ref' => '?key', 'variant_item_ref' => '?key']);
			if(isset($optionKeys[$option->option_ref]) || !isset($instances[$option->target_instance_key])) throw new InvalidArgumentException('Invalid technical equipment target');
			$optionKeys[$option->option_ref] = true;
			if($option->included !== in_array($option->option_ref, $d->variant_signature->included_option_refs, true) || !in_array($option->option_ref, array_merge($d->variant_signature->included_option_refs, $d->variant_signature->extra_option_refs), true)) throw new InvalidArgumentException('Equipment and option signature mismatch');
		}
		$equipmentRefs = array_keys($optionKeys); $signature = array_merge($d->variant_signature->included_option_refs, $d->variant_signature->extra_option_refs);
		sort($equipmentRefs); sort($signature);
		if($equipmentRefs !== $signature) throw new InvalidArgumentException('Every selected option needs an explicit technical effect record');
		$d->approximation = $this->fields($d->approximation, ['enabled' => 'bool', 'model_id' => ['fixed_temperature_offsets_v1'], 'condensing_air_offset_K' => 'nonnegative', 'evaporating_supply_offset_K' => 'nonnegative', 'water_delta_T_K' => '?positive', 'scope' => 'text', 'source' => 'text', 'release_status' => ['draft']]);
		return $d;
	}

	private function unique(array $values): void
	{
		if(count($values) !== count(array_unique($values))) throw new InvalidArgumentException('Duplicate technical identity');
	}
}
