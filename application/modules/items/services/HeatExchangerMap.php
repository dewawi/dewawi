<?php

class Items_Service_HeatExchangerMap
{
	public const VERSION = 'synthetic_exchanger_effectiveness_binary64_v1';

	public function prepare(stdClass $record, string $sectionKey, stdClass $request): array
	{
		$schema = new Items_Service_TechnicalSchema(); $r = $schema->dataset($record);
		if($r->dataset_type !== 'heat_exchanger_map') throw new InvalidArgumentException('An exchanger map is required');
		$d = $r->data;
		if($d->contract_version !== 1) throw new InvalidArgumentException('Point exchanger maps require the operating mode evaluator');
		$input = $schema->fields($request, ['medium_key' => 'key', 'inlet_degC' => 'number', 'volume_flow_m3_h' => '?positive', 'fluid_state' => '?text'], $d->role === 'air_condenser' ? ['fan' => 'object'] : []);
		if((float)$input->inlet_degC <= -273.15) throw new InvalidArgumentException('Invalid secondary inlet temperature');
		if(isset($input->fan)) $input->fan = $schema->fields($input->fan, ['execution_code' => '?text', 'speed_rpm' => '?positive']);
		$sections = array_values(array_filter($d->sections, fn($s) => $s->section_key === $sectionKey));
		if(count($sections) !== 1) throw new InvalidArgumentException('Exchanger section unavailable');
		$section = $sections[0];
		$result = ['calculation_version' => self::VERSION, 'scope' => 'synthetic_exchanger_only', 'eligible' => false, 'status' => 'review_required',
			'package' => ['dataset_key' => $r->dataset_key, 'revision' => $r->revision], 'model_id' => $d->model_id, 'role' => $d->role, 'section_key' => $sectionKey,
			'inputs' => $input, 'section' => $section, 'medium' => null, 'capacity_rate_kW_K' => null, 'conductance_kW_K' => null, 'mass_flow_kg_s' => null,
			'reasons' => $d->missing_evidence, 'restrictions' => $d->restrictions];
		$media = array_values(array_filter($d->media, fn($m) => $m->medium_key === $input->medium_key));
		if(count($media) !== 1) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_medium_not_permitted'; return $result; }
		$medium = $media[0]; $result['medium'] = $medium;
		if($input->fluid_state === null) $result['reasons'][] = 'fluid_state_missing';
		elseif($input->fluid_state !== $medium->phase) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'fluid_state_mismatch'; }
		if($input->volume_flow_m3_h === null) $result['reasons'][] = 'secondary_flow_missing';
		$properties = $medium->properties;
		if($properties->density_kg_m3 === null || $properties->specific_heat_kJ_kg_K === null) $result['reasons'][] = 'fluid_properties_missing';
		if(!$this->inside($input->inlet_degC, $section->limits->secondary_inlet) || !$this->inside($input->inlet_degC, $properties->temperature_range)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_inlet_outside_map'; }
		if($input->volume_flow_m3_h !== null && !$this->inside($input->volume_flow_m3_h, $section->limits->volume_flow)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_flow_outside_map'; }
		if($d->role === 'air_condenser') {
			if($d->fan_condition === null || !isset($input->fan) || $input->fan->execution_code === null || $input->fan->speed_rpm === null) $result['reasons'][] = 'fan_condition_missing';
			elseif(Items_Service_TechnicalJson::encode($input->fan) !== Items_Service_TechnicalJson::encode($d->fan_condition)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'fan_condition_mismatch'; }
		}
		// Missing engineering evidence stays visible but is not a missing mathematical operand.
		$blocking = count($result['reasons']) > count($d->missing_evidence);
		if($blocking) return $result;
		$mass = (float)$input->volume_flow_m3_h / 3600.0 * (float)$properties->density_kg_m3;
		$capacity = $mass * (float)$properties->specific_heat_kJ_kg_K;
		$conductance = (float)$section->effectiveness * $capacity;
		if(!is_finite($mass) || !is_finite($capacity) || !is_finite($conductance) || min($mass, $capacity, $conductance) <= 0.0) { $result['reasons'][] = 'exchanger_numeric_out_of_range'; return $result; }
		$result['mass_flow_kg_s'] = $mass; $result['capacity_rate_kW_K'] = $capacity; $result['conductance_kW_K'] = $conductance;
		return $result;
	}

	public function atPoint(array $prepared, string $saturation): array
	{
		$saturation = Items_Service_TechnicalJson::decimal($saturation);
		$result = ['calculation_version' => self::VERSION, 'scope' => 'synthetic_exchanger_only', 'eligible' => false, 'status' => $prepared['status'], 'mathematical_status' => 'not_available',
			'heat_flow_kW' => null, 'secondary_outlet_degC' => null, 'secondary_delta_T_K' => null, 'saturation_degC' => $saturation, 'approach_K' => null,
			'pressure_drop_kPa' => $prepared['section']->pressure_drop_kPa, 'reasons' => $prepared['reasons']];
		if($prepared['conductance_kW_K'] === null) return $result;
		if(!$this->inside($saturation, $prepared['section']->limits->saturation_temperature)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'saturation_outside_map'; return $result; }
		$inlet = (float)$prepared['inputs']->inlet_degC; $sat = (float)$saturation;
		$evaporating = $prepared['role'] === 'evaporator';
		$difference = $evaporating ? $inlet - $sat : $sat - $inlet;
		$heat = $prepared['conductance_kW_K'] * $difference;
		$delta = $heat / $prepared['capacity_rate_kW_K'];
		$outlet = $evaporating ? $inlet - $delta : $inlet + $delta;
		if(!is_finite($heat) || !is_finite($outlet) || !is_finite($delta)) { $result['reasons'][] = 'exchanger_numeric_out_of_range'; return $result; }
		if($difference <= 0 || $heat <= 0) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'exchanger_heat_direction_invalid'; return $result; }
		$outletText = json_encode($outlet, JSON_THROW_ON_ERROR);
		if(!$this->inside($outletText, $prepared['section']->limits->secondary_outlet) || !$this->inside($outletText, $prepared['medium']->properties->temperature_range)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'secondary_outlet_outside_map'; return $result; }
		return array_replace($result, ['mathematical_status' => 'calculated', 'heat_flow_kW' => $heat, 'secondary_outlet_degC' => $outlet,
			'secondary_delta_T_K' => $delta, 'approach_K' => $evaporating ? $outlet - $sat : $sat - $outlet]);
	}

	public function parked(array $prepared): array
	{
		$result = ['calculation_version' => self::VERSION, 'model_id' => 'synthetic_zero_heat_pass_through_v1', 'scope' => 'synthetic_parked_section_only', 'eligible' => false, 'status' => 'review_required', 'mathematical_status' => 'not_available',
			'heat_flow_kW' => null, 'secondary_outlet_degC' => null, 'secondary_delta_T_K' => null, 'saturation_degC' => null, 'approach_K' => null, 'reasons' => $prepared['reasons']];
		if($prepared['conductance_kW_K'] === null) return $result;
		if(!$this->inside($prepared['inputs']->inlet_degC, $prepared['section']->limits->secondary_outlet)) { $result['status'] = 'unsuitable'; $result['reasons'][] = 'parked_outlet_outside_map'; return $result; }
		return array_replace($result, ['mathematical_status' => 'calculated', 'heat_flow_kW' => 0.0, 'secondary_outlet_degC' => (float)$prepared['inputs']->inlet_degC, 'secondary_delta_T_K' => 0.0]);
	}

	public function inside(string $value, stdClass $range): bool
	{
		return Items_Service_TechnicalJson::compareDecimal($value, $range->min) >= 0 && Items_Service_TechnicalJson::compareDecimal($value, $range->max) <= 0;
	}

	public function atFluidPoint(stdClass $record, string $sectionKey, string $saturation, string $inlet, string $mass, stdClass $fluid, string $operatingRole): array
	{
		$s = new Items_Service_TechnicalSchema(); if(!in_array($operatingRole, ['evaporator', 'condenser'], true)) throw new InvalidArgumentException('Invalid liquid operating role'); $record = $s->dataset($record); $fluid = $s->dataset($fluid); $d = $record->data;
		if($record->dataset_type !== 'heat_exchanger_map' || $d->contract_version !== 3) throw new InvalidArgumentException('Coupled liquid map required');
		(new Items_Service_FluidHydraulics())->sameFluid($d->fluid_ref, $fluid);
		$sections = array_values(array_filter($d->sections, fn($v) => $v->section_key === $sectionKey)); if(count($sections) !== 1) throw new InvalidArgumentException('Unknown liquid section'); $section = clone $sections[0]; $section->pressure_drop_kPa = null;
		$f = new Items_Service_FluidProperties(); $a = $f->state($fluid->data, $d->fluid_composition, $inlet);
		$outlet = (float)$inlet + (float)$section->effectiveness * ((float)$saturation - (float)$inlet);
		$delta = $outlet - (float)$inlet; if($delta === 0.0 || !is_finite($outlet) || (float)$mass <= 0 || !is_finite((float)$mass)) throw new DomainException('coupled_exchanger_numeric_range');
		$enthalpy = $f->enthalpyDifference($fluid->data, $d->fluid_composition, $inlet, $f::decimal($outlet));
		$capacity = (float)$mass * $enthalpy / $delta / 1000;
		$flow = 3600 * (float)$mass / $a['density_kg_m3'];
		if(!is_finite($capacity) || !is_finite($flow) || min($capacity,(float)$section->effectiveness*$capacity,$flow) <= 0) throw new DomainException('coupled_exchanger_numeric_range');
		if(!$this->inside($inlet, $section->limits->secondary_inlet) || !$this->inside($f::decimal($flow), $section->limits->volume_flow)) throw new DomainException('coupled_exchanger_inlet_or_flow_limit');
		// The D4 effectiveness law uses the D5 enthalpy secant, not a constant-cp approximation.
		$prepared = ['status' => 'review_required', 'reasons' => $d->missing_evidence, 'section' => $section, 'role' => $operatingRole, 'inputs' => (object)['inlet_degC' => $inlet],
			'capacity_rate_kW_K' => $capacity, 'conductance_kW_K' => (float)$section->effectiveness * $capacity, 'medium' => (object)['properties' => (object)['temperature_range' => (object)['min' => $fluid->data->temperatures_degC[0], 'max' => end($fluid->data->temperatures_degC)]]]];
		$r = $this->atPoint($prepared, $saturation);
		$r['map_revision'] = ['dataset_key' => $record->dataset_key, 'revision' => $record->revision]; $r['fluid_revision'] = $d->fluid_ref; $r['inlet_state'] = $a; $r['outlet_state'] = $f->state($fluid->data, $d->fluid_composition, $f::decimal($outlet));
		$r['mass_flow_kg_s'] = (float)$mass; $r['inlet_volume_flow_m3_h'] = $flow; $r['enthalpy_difference_J_kg'] = $enthalpy;
		$r['model_id'] = $d->model_id; $f->finite($r); return $r;
	}
}
