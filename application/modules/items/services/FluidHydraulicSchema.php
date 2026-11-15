<?php

class Items_Service_FluidHydraulicSchema
{
	private Items_Service_TechnicalSchema $schema;

	public function __construct() { $this->schema = new Items_Service_TechnicalSchema(); }

	public function data(string $type, stdClass $value): stdClass
	{
		$common = ['contract_version' => 'int', 'usage' => ['synthetic_test_only', 'source_documented'], 'missing_evidence' => 'list:key'];
		switch($type) {
			case 'fluid_properties':
				$d = $this->schema->fields($value, $common + ['fluid_key' => 'key', 'mixture' => ['water', 'ethylene_glycol_water', 'propylene_glycol_water'], 'concentration_basis' => ['mass_fraction', 'volume_fraction'], 'volume_reference_degC' => '?number', 'interpolation' => ['bilinear_cp_integral_v1'], 'units' => 'object', 'temperatures_degC' => 'list:number', 'concentrations' => 'list:nonnegative', 'properties' => 'list:list:object', 'freezing_degC' => '?list:number']);
				$d->units = $this->schema->fields($d->units, ['temperature' => ['degC'], 'concentration' => ['fraction'], 'density' => ['kg/m3'], 'specific_heat' => ['J/(kg K)'], 'dynamic_viscosity' => ['Pa s'], 'specific_enthalpy' => ['J/kg']]);
				$this->axis($d->temperatures_degC, true); $this->axis($d->concentrations);
				if(($d->concentration_basis === 'volume_fraction') !== ($d->volume_reference_degC !== null) || ($d->volume_reference_degC !== null && (float)$d->volume_reference_degC <= -273.15) || (float)end($d->concentrations) > 1) throw new InvalidArgumentException('Invalid concentration basis');
				if($d->mixture === 'water' && ($d->concentrations !== ['0'] || $d->concentration_basis !== 'mass_fraction')) throw new InvalidArgumentException('Water requires explicit zero mass fraction');
				$this->grid($d->properties, count($d->concentrations), count($d->temperatures_degC));
				foreach($d->properties as &$row) foreach($row as &$cell) $cell = $this->schema->fields($cell, ['density_kg_m3' => 'positive', 'specific_heat_J_kg_K' => 'positive', 'dynamic_viscosity_Pa_s' => 'positive']);
				if($d->freezing_degC !== null) { if(count($d->freezing_degC) !== count($d->concentrations)) throw new InvalidArgumentException('Incomplete freezing limits'); foreach($d->freezing_degC as $t) if((float)$t <= -273.15) throw new InvalidArgumentException('Invalid freezing limit'); }
				break;
			case 'pressure_loss_map':
				$d = $this->schema->fields($value, $common + ['component_key' => 'key', 'model' => 'text', 'role' => array_merge(Items_Service_TechnicalSchema::ROLES, ['external']), 'fluid_ref' => 'ref', 'interpolation' => ['trilinear_flow_temperature_concentration_v1'], 'measurement_boundary' => ['flange_to_flange', 'external_to_machine'], 'pressure_unit' => ['Pa', 'kPa', 'bar'], 'volume_flow_unit' => ['m3/h'], 'temperatures_degC' => 'list:number', 'concentrations' => 'list:nonnegative', 'volume_flows_m3_h' => 'list:positive', 'sections' => 'list:object']);
				$this->axis($d->temperatures_degC, true); $this->axis($d->concentrations); $this->axis($d->volume_flows_m3_h); $seen = [];
				foreach($d->sections as &$section) {
					$section = $this->schema->fields($section, ['section_key' => 'key', 'pressure_losses' => 'list:list:list:nonnegative']);
					if(isset($seen[$section->section_key])) throw new InvalidArgumentException('Duplicate pressure section'); $seen[$section->section_key] = true;
					$this->grid($section->pressure_losses, count($d->concentrations), count($d->temperatures_degC));
					foreach($section->pressure_losses as $row) foreach($row as $cell) if(count($cell) !== count($d->volume_flows_m3_h)) throw new InvalidArgumentException('Incomplete pressure grid');
				}
				if(!$d->sections || ($d->role === 'external') !== ($d->measurement_boundary === 'external_to_machine')) throw new InvalidArgumentException('Invalid pressure boundary');
				break;
			case 'pump_curve':
				$d = $this->schema->fields($value, $common + ['component_key' => 'key', 'model' => 'text', 'fluid_ref' => 'ref', 'model_id' => ['piecewise_linear_fixed_speed_v1'], 'measurement_boundary' => ['pump_flange_differential'], 'speed_rpm' => 'positive', 'frequency_Hz' => 'positive', 'operating_mode' => 'key', 'ordinate_unit' => ['m', 'Pa', 'kPa', 'bar'], 'volume_flow_unit' => ['m3/h'], 'limits' => 'object', 'points' => 'list:object']);
				$d->limits = $this->schema->fields($d->limits, ['temperature_degC' => 'object', 'concentration' => 'object', 'density_kg_m3' => 'object', 'dynamic_viscosity_Pa_s' => 'object']);
				foreach($d->limits as $name => &$range) $range = $this->range($range, $name === 'temperature_degC');
				$flows = []; foreach($d->points as &$point) { $point = $this->schema->fields($point, ['volume_flow_m3_h' => 'positive', 'ordinate' => 'nonnegative', 'hydraulic_efficiency' => '?positive', 'total_efficiency' => '?positive', 'electrical_input_W' => '?positive']); foreach(['hydraulic_efficiency', 'total_efficiency'] as $k) if($point->$k !== null && (float)$point->$k > 1) throw new InvalidArgumentException('Invalid pump efficiency'); $flows[] = $point->volume_flow_m3_h; }
				$this->axis($flows); if(count($flows) < 2) throw new InvalidArgumentException('Pump curve requires two points');
				break;
			case 'hydraulic_path':
				$d = $this->schema->fields($value, $common + ['loop_key' => 'key', 'topology_kind' => ['serial_components_parallel_sections_v1'], 'component_instances' => 'list:key', 'pump_instance' => '?key', 'external_loss_ref' => '?ref', 'pipe_segments' => 'list:object', 'pressure_boundary' => ['closed_loop_dissipative_only_v1'], 'connections' => 'list:object']);
				if(count($d->component_instances) !== count(array_unique($d->component_instances))) throw new InvalidArgumentException('Duplicate hydraulic component'); $seen = [];
				foreach($d->connections as &$connection) $connection = $this->schema->fields($connection, ['edge_from' => 'key', 'edge_to' => 'key', 'loss_basis' => ['explicit_pipe_segments', 'included_in_component_maps', 'documented_zero'], 'source_locator' => 'text']);
				unset($connection);
				foreach($d->pipe_segments as &$pipe) { $pipe = $this->schema->fields($pipe, ['segment_key' => 'key', 'edge_from' => 'key', 'edge_to' => 'key', 'model_id' => ['circular_laminar_poiseuille_v1'], 'flow_condition' => ['steady_newtonian_fully_developed'], 'length_m' => 'positive', 'internal_diameter_m' => 'positive', 'local_loss_coefficient' => 'nonnegative', 'source_locator' => 'text']); if(isset($seen[$pipe->segment_key])) throw new InvalidArgumentException('Duplicate pipe segment'); $seen[$pipe->segment_key] = true; }
				break;
			default: throw new InvalidArgumentException('Unsupported fluid/hydraulic contract');
		}
		if($d->contract_version !== 1) throw new InvalidArgumentException('Unsupported fluid/hydraulic version');
		return $d;
	}

	private function range(stdClass $value, bool $temperature): stdClass
	{
		$r = $this->schema->fields($value, ['min' => $temperature ? 'number' : 'nonnegative', 'max' => $temperature ? 'number' : 'nonnegative']);
		if(Items_Service_TechnicalJson::compareDecimal($r->min, $r->max) > 0 || ($temperature && (float)$r->min <= -273.15)) throw new InvalidArgumentException('Invalid hydraulic range');
		return $r;
	}

	public function axis(array $axis, bool $temperature = false): void
	{
		if(!$axis) throw new InvalidArgumentException('Empty interpolation axis');
		foreach($axis as $i => $v) if(($temperature && (float)$v <= -273.15) || !is_finite((float)$v) || ($i && (Items_Service_TechnicalJson::compareDecimal($axis[$i - 1], $v) >= 0 || (float)$axis[$i - 1] >= (float)$v))) throw new InvalidArgumentException('Invalid or numerically indistinguishable interpolation axis');
	}

	private function grid(array $grid, int $rows, int $columns): void
	{
		if(count($grid) !== $rows) throw new InvalidArgumentException('Incomplete table rows');
		foreach($grid as $row) if(count($row) !== $columns) throw new InvalidArgumentException('Incomplete table columns');
	}
}
