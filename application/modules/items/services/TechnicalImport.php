<?php

// Private input adapters. They normalize data, never open source files or execute formulas.
class Items_Service_TechnicalImport
{
	private Items_Service_TechnicalSchema $schema;

	public function __construct() { $this->schema = new Items_Service_TechnicalSchema(); }

	public function referencePoints(string $json, string $datasetKey, int $revision, ?string $dataOwner = null): string
	{
		$input = $this->schema->fields(Items_Service_TechnicalJson::decode($json), ['format' => ['dewawi-reference-points'], 'format_version' => 'int', 'production_import_allowed' => 'bool', 'records' => 'list:object']);
		if($input->format_version !== 1 || !$input->records) throw new InvalidArgumentException('Invalid reference-points adapter version');
		$records = [];
		foreach($input->records as $point) $records[] = $this->schema->reference($point);
		$dataset = $this->draft($datasetKey, $revision, 'reference_points', $records[0]->source, (object)['records' => $records]);
		$dataset->data_owner = $dataOwner;
		return Items_Service_TechnicalJson::encode((object)['format' => 'dewawi-technical-import', 'format_version' => 1, 'production_import_allowed' => false, 'datasets' => [$this->schema->dataset($dataset)]]);
	}

	private function draft(string $key, int $revision, string $type, stdClass $source, stdClass $data): stdClass
	{
		return (object)['dataset_key' => $key, 'revision' => $revision, 'dataset_type' => $type, 'schema_version' => 1, 'requested_status' => 'draft', 'manufacturer_name' => null, 'manufacturer_revision' => null, 'data_owner' => null, 'source' => $source, 'data' => $data, 'blockers' => []];
	}

	public function csv(string $template, string $csv, string $contextJson): string
	{
		if(strlen($csv) > 4194304 || !mb_check_encoding($csv, 'UTF-8') || str_contains($csv, "\0")) throw new InvalidArgumentException('Invalid technical CSV size or encoding');
		$contracts = json_decode(file_get_contents(dirname(__DIR__).'/configs/technical-import-columns.json'), true, 32, JSON_THROW_ON_ERROR)['templates'];
		if(!isset($contracts[$template])) throw new InvalidArgumentException('Unknown technical CSV template');
		$context = $this->schema->fields(Items_Service_TechnicalJson::decode($contextJson), ['format' => ['dewawi-technical-csv-context'], 'format_version' => 'int', 'bundle' => 'object', 'sources' => 'object'], ['component_datasets' => 'object', 'data_owner' => '?text']);
		if(isset($context->component_datasets)) foreach($context->component_datasets as $key => $ref) {
			$this->schema->fields((object)['key' => $key], ['key' => 'key']);
			$context->component_datasets->$key = $this->schema->fields($ref, ['dataset_key' => 'key', 'revision' => 'int']);
		}
		if($context->format_version !== 1) throw new InvalidArgumentException('Unsupported CSV context version');
		$bundle = $this->schema->fields($context->bundle, ['format' => ['dewawi-technical-import'], 'format_version' => 'int', 'datasets' => 'list:object'], ['profiles' => 'list:object']);
		if($bundle->format_version !== 1) throw new InvalidArgumentException('Unsupported CSV bundle version');
		foreach($context->sources as $key => $source) {
			$this->schema->fields((object)['key' => $key], ['key' => 'key']);
			$context->sources->$key = $this->schema->source($source);
		}
		$stream = fopen('php://temp', 'w+'); fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv)); rewind($stream);
		try {
			$header = $this->record($stream);
			$contract = $contracts[$template];
			if($header !== $contract['columns']) throw new InvalidArgumentException('Technical CSV header differs from the versioned contract');
			$count = 0; $seen = [];
			while(($values = $this->record($stream)) !== false) {
				if($values === [null]) continue;
				if(count($values) !== count($header) || ++$count > 5000) throw new InvalidArgumentException('Invalid CSV row width or row limit');
				$row = array_combine($header, $values);
				foreach(['revision', 'schema_version', 'family_revision', 'machine_revision', 'profile_revision', 'dataset_revision', 'phases'] as $field) if(isset($row[$field]) && $row[$field] !== '') $this->schema->fields((object)['value' => $row[$field]], ['value' => 'int']);
				foreach($row as $field => $value) if($value !== '' && preg_match('/_(?:V|Hz|rpm|degC|K|mm|kPa|m3_h|L)$/D', $field)) Items_Service_TechnicalJson::decimal($value);
				foreach($contract['required_columns'] as $field) if(trim($row[$field]) === '') throw new InvalidArgumentException('Missing technical CSV field: '.$field);
				$source = $context->sources->{$row['source_ref']} ?? null;
				if(!$source || $source->sha256 !== $row['source_sha256']) throw new InvalidArgumentException('CSV source manifest hash mismatch');
				$identityFields = [
					'01-komponentenartikel' => ['component_key', 'revision'], '02-polynomdatensaetze' => ['dataset_key', 'revision', 'quantity'],
					'03-maschinenfamilien-gehaeuse' => ['family_dataset_key', 'family_revision', 'chassis_key'],
					'04-kreislauf-verdichterzuordnungen' => ['machine_dataset_key', 'machine_revision', 'circuit_key', 'instance_key'],
					'05-waermetauscher' => ['component_key', 'revision', 'refrigerant_section_key'], '06-pumpen-hydraulik' => ['component_key', 'revision', 'loop_key'],
					'07-optionen-zubehoer' => ['option_ref', 'variant_item_ref', 'revision'], '08-referenzbetriebspunkte' => ['reference_key', 'revision', 'quantity'],
				];
				$identity = json_encode(array_map(fn($field) => $row[$field], $identityFields[$template]), JSON_THROW_ON_ERROR);
				if(isset($seen[$identity])) throw new InvalidArgumentException('Duplicate technical CSV identity');
				$seen[$identity] = true;
				$source = clone $source; $source->locator = $row['source_locator'];
				$this->csvRow($template, $row, $source, $bundle, $context);
			}
			if(!$count) throw new InvalidArgumentException('Technical CSV contains no records');
		} finally { fclose($stream); }
		if(isset($context->data_owner)) foreach($bundle->datasets as $dataset) {
			if($dataset->data_owner !== null && $dataset->data_owner !== $context->data_owner) throw new InvalidArgumentException('CSV context contradicts the package data owner');
			$dataset->data_owner = $context->data_owner;
		}
		// Run the common closed schemas after aggregation, before returning importable JSON.
		$json = Items_Service_TechnicalJson::encode($bundle);
		(new Items_Service_TechnicalDataset())->validateJson($json);
		return $json;
	}

	private function record($stream)
	{
		$start = ftell($stream);
		$row = fgetcsv($stream, 0, ';', '"', '');
		if($row === false) return false;
		$end = ftell($stream); fseek($stream, $start); $raw = fread($stream, $end - $start);
		$field = '(?:"(?:[^"]|"")*"|[^";\r\n]*)';
		if(!preg_match('~\A'.$field.'(?:;'.$field.')*(?:\r\n|\n|\r)?\z~sD', $raw)) throw new InvalidArgumentException('Malformed technical CSV quoting');
		return $row;
	}

	private function number(string $value) { return $value === '' ? null : Items_Service_TechnicalJson::decimal($value); }
	private function ref(string $value)
	{
		if($value === '') return null;
		if(!preg_match('/^(.+)@([1-9][0-9]*)$/D', $value, $m)) throw new InvalidArgumentException('CSV references require key@revision');
		return $this->schema->fields((object)['dataset_key' => $m[1], 'revision' => $m[2]], ['dataset_key' => 'key', 'revision' => 'int']);
	}
	private function boolean(string $value): bool
	{
		if(!in_array($value, ['true', 'false'], true)) throw new InvalidArgumentException('CSV boolean must be true or false');
		return $value === 'true';
	}

	private function find(stdClass $bundle, string $key, int $revision): stdClass
	{
		foreach($bundle->datasets as $d) if($d->dataset_key === $key && (int)$d->revision === $revision) return $d;
		throw new InvalidArgumentException('CSV requires the complete base package in its context');
	}

	private function csvRow(string $template, array $r, stdClass $source, stdClass $bundle, stdClass $context): void
	{
		switch($template) {
			case '02-polynomdatensaetze':
				if($r['envelope_ref'] !== '') throw new InvalidArgumentException('Polynomial envelope must be inline in version one; external envelope schemas are not implemented');
				$d = null;
				foreach($bundle->datasets as $candidate) if($candidate->dataset_key === $r['dataset_key'] && (string)$candidate->revision === $r['revision']) $d = $candidate;
				$data = (object)['condition_set' => $r['condition_set'], 'model' => $r['model'], 'refrigerant' => $r['refrigerant'], 'equation_id' => 'te_tc_cubic_c0_c9_v1', 'temperature_unit' => 'degC', 'temperature_basis' => (object)['Te' => $r['temperature_basis_Te'] ?: null, 'Tc' => $r['temperature_basis_Tc'] ?: null], 'conditions' => (object)['speed_rpm' => $this->number($r['speed_rpm']), 'frequency_Hz' => $this->number($r['frequency_Hz']), 'superheat_K' => $this->number($r['superheat_K']), 'return_gas_temperature_degC' => $this->number($r['return_gas_temperature_degC']), 'subcooling_K' => $this->number($r['subcooling_K']), 'suction_condition_mode' => 'superheat'], 'electrical' => (object)['voltage_V' => null, 'phases' => null, 'execution_code' => null], 'operating_envelope' => null, 'quantities' => new stdClass()];
				if(!$d) {
					$d = $this->draft($r['dataset_key'], (int)$r['revision'], 'compressor_polynomial', $source, $data);
					$d->schema_version = $r['schema_version']; $d->manufacturer_name = $r['manufacturer_name']; $d->manufacturer_revision = $r['manufacturer_revision'];
					$d->data_owner = $context->data_owner ?? null; $bundle->datasets[] = $d;
				} else {
					$withoutQuantities = clone $d->data; $withoutQuantities->quantities = new stdClass();
					if(Items_Service_TechnicalJson::encode($withoutQuantities) !== Items_Service_TechnicalJson::encode($data) || $d->source->sha256 !== $source->sha256 || $d->manufacturer_revision !== $r['manufacturer_revision'] || $d->manufacturer_name !== $r['manufacturer_name'] || (int)$d->schema_version !== (int)$r['schema_version']) throw new InvalidArgumentException('Mixed CSV polynomial conditions');
				}
				if(isset($d->data->quantities->{$r['quantity']})) throw new InvalidArgumentException('Duplicate CSV polynomial quantity');
				$c = []; for($i = 0; $i < 10; $i++) $c[] = Items_Service_TechnicalJson::decimal($r['C'.$i]);
				$d->data->quantities->{$r['quantity']} = (object)['unit' => $r['unit'], 'coefficients_C0_to_C9' => $c, 'source_range' => $r['source_locator']];
				break;
			case '03-maschinenfamilien-gehaeuse':
				$d = null;
				foreach($bundle->datasets as $candidate) if($candidate->dataset_key === $r['family_dataset_key'] && (int)$candidate->revision === (int)$r['family_revision']) $d = $candidate;
				if(!$d) { $d = $this->draft($r['family_dataset_key'], (int)$r['family_revision'], 'machine_family', $source, (object)['family_key' => $r['family_key'], 'title' => $r['title'], 'chassis' => []]); $bundle->datasets[] = $d; }
				if($d->data->family_key !== $r['family_key'] || $d->data->title !== $r['title'] || (isset($d->manufacturer_ref) && $d->manufacturer_ref !== $r['manufacturer_ref'])) throw new InvalidArgumentException('Mixed CSV family identity');
				$d->manufacturer_ref = $r['manufacturer_ref'];
				$d->data->chassis[] = (object)['chassis_key' => $r['chassis_key'], 'dimensions' => (object)['length_mm' => $this->number($r['length_mm']), 'width_mm' => $this->number($r['width_mm']), 'height_mm' => $this->number($r['height_mm'])], 'installation_kinds' => [$r['installation_kind']], 'structural_limits_dataset' => $this->ref($r['structural_limits_dataset_ref'])];
				break;
			case '08-referenzbetriebspunkte':
				$point = (object)['reference_key' => $r['reference_key'], 'dataset_ref' => (object)['dataset_key' => $r['dataset_key'], 'revision' => $r['dataset_revision']], 'reference_kind' => $r['reference_kind'], 'inputs' => new stdClass(), 'quantity' => $r['quantity'], 'expected_value' => $r['expected_value'], 'unit' => $r['unit'], 'absolute_tolerance' => $this->number($r['absolute_tolerance']), 'relative_tolerance' => $this->number($r['relative_tolerance']), 'tolerance_origin' => 'CSV source tolerance: '.$r['source_locator'], 'source' => $source, 'revision' => $r['revision'], 'engineering_approval' => null, 'mode' => $r['mode']];
				foreach(['Te_degC', 'Tc_degC', 'supply_degC', 'return_degC', 'ambient_degC'] as $field) if($r[$field] !== '') $point->inputs->$field = $r[$field];
				if($r['fluid_dataset_ref'] !== '') $point->inputs->fluid_dataset_ref = $this->ref($r['fluid_dataset_ref']);
				if($r['stage_key'] !== '') $point->stage_key = $r['stage_key'];
				if($r['machine_profile_ref'] !== '') { $p = $this->ref($r['machine_profile_ref']); $point->machine_profile_ref = (object)['profile_key' => $p->dataset_key, 'revision' => $p->revision]; }
				$bundle->datasets[] = $this->draft('reference:'.hash('sha256', $r['reference_key'].':'.$r['quantity']), (int)$r['revision'], 'reference_points', $source, (object)['records' => [$point]]);
				break;
			case '01-komponentenartikel':
				$ref = $context->component_datasets->{$r['component_key']} ?? null;
				if(!$ref) throw new InvalidArgumentException('Component dataset mapping required in CSV context');
				$ref = $this->schema->fields($ref, ['dataset_key' => 'key', 'revision' => 'int']);
				$base = $this->find($bundle, $ref->dataset_key, $ref->revision);
				foreach(['frequency_Hz', 'speed_rpm'] as $field) if($r[$field] !== '' && (!isset($base->data->conditions->$field) || $this->number($r[$field]) !== Items_Service_TechnicalJson::decimal($base->data->conditions->$field))) throw new InvalidArgumentException('CSV component conditions contradict its dataset');
				$bundle->profiles = $bundle->profiles ?? [];
				$bundle->profiles[] = (object)['profile_key' => $r['component_key'], 'revision' => $r['revision'], 'item_ref' => $r['item_ref'], 'dataset_ref' => $ref, 'role' => $r['component_type'], 'binding' => (object)['model' => $r['model'], 'manufacturersku' => $r['manufacturersku'], 'electrical' => (object)['voltage_V' => $this->number($r['voltage_V']), 'phases' => $r['phases'] ?: null, 'execution_code' => $r['electrical_execution_code'] ?: null], 'base_item_ref' => $r['base_item_ref'] ?: null, 'included_option_refs' => [], 'extra_option_refs' => [], 'source_locator' => $r['source_locator'], 'scope_key' => $r['component_key'], 'manufacturer_ref' => $r['manufacturer_ref'], 'source' => $source]];
				$profile = $bundle->profiles[count($bundle->profiles) - 1];
				if($r['voltage_V'] === '' && $r['phases'] === '' && $r['electrical_execution_code'] === '') $profile->binding->electrical = null;
				break;
			case '04-kreislauf-verdichterzuordnungen':
				$d = $this->find($bundle, $r['machine_dataset_key'], (int)$r['machine_revision']);
				$circuits = array_values(array_filter($d->data->circuits, fn($c) => $c->circuit_key === $r['circuit_key']));
				$components = array_values(array_filter($d->data->components, fn($c) => $c->instance_key === $r['instance_key']));
				if(count($circuits) !== 1 || count($components) !== 1) throw new InvalidArgumentException('CSV circuit view has no exact topology target');
				$c = $circuits[0]; $p = $components[0];
				if(!in_array($p->instance_key, $c->compressor_instances, true) || $p->component_key !== $r['component_key'] || $p->profile_key !== $r['profile_key'] || (int)$p->profile_revision !== (int)$r['profile_revision'] || $c->refrigerant !== $r['refrigerant'] || $c->topology_kind !== $r['topology_kind'] || $c->evaporator_section->instance_key !== $r['evaporator_instance'] || $c->evaporator_section->section_key !== $r['evaporator_section'] || $c->condenser_instance !== $r['condenser_instance'] || $r['suction_group'] !== 'suction' || $r['discharge_group'] !== 'discharge' || ($r['expansion_device_ref'] !== '' && $r['expansion_device_ref'] !== $c->expansion_device->device_key)) throw new InvalidArgumentException('CSV circuit view contradicts complete topology');
				break;
			case '05-waermetauscher': case '06-pumpen-hydraulik':
				$ref = $context->component_datasets->{$r['component_key']} ?? null;
				if(!$ref) throw new InvalidArgumentException('CSV component base package required');
				$d = $this->find($bundle, $ref->dataset_key, (int)$ref->revision);
				if($d->dataset_type !== 'component_spec' || (int)$ref->revision !== (int)$r['revision']) throw new InvalidArgumentException('CSV component type or revision mismatch');
				$profileMatches = array_filter($bundle->profiles ?? [], fn($p) => $p->profile_key === $r['profile_key'] && (int)$p->revision === (int)$r['profile_revision'] && $p->item_ref === $r['component_key'] && $p->dataset_ref->dataset_key === $ref->dataset_key && (int)$p->dataset_ref->revision === (int)$ref->revision);
				if(count($profileMatches) !== 1) throw new InvalidArgumentException('CSV component requires an exact profile in its context');
				if($template === '05-waermetauscher') {
					if($d->data->role !== $r['role'] || $d->data->refrigerant !== $r['refrigerant'] || !in_array($r['refrigerant_section_key'], $d->data->specification->sections, true)) throw new InvalidArgumentException('CSV heat exchanger identity/section mismatch');
					$d->data->specification = (object)['exchanger_type' => $r['exchanger_type'], 'sections' => $d->data->specification->sections, 'thermal_map_ref' => $this->ref($r['map_dataset_ref']), 'pressure_loss_ref' => $this->ref($r['pressure_loss_dataset_ref']), 'flow_min_m3_h' => $this->number($r['flow_min_m3_h']), 'flow_max_m3_h' => $this->number($r['flow_max_m3_h']), 'pressure_max_kPa' => $this->number($r['pressure_max_kPa'])];
					if($r['primary_fluid_ref'] !== '' || $r['secondary_fluid_ref'] !== '') throw new InvalidArgumentException('Fluid map schemas are not implemented in D2B');
				} else {
					$loops = [];
					foreach($bundle->datasets as $machine) if($machine->dataset_type === 'machine_configuration') foreach($machine->data->hydraulic_loops as $loop) if($loop->loop_key === $r['loop_key']) $loops[] = [$machine, $loop];
					if(count($loops) !== 1) throw new InvalidArgumentException('Hydraulic CSV requires one exact complete loop context');
					[$machine, $loop] = $loops[0];
					foreach(['external_requirement_kPa', 'flow_min_m3_h', 'flow_max_m3_h'] as $field) if($r[$field] !== '' && (!isset($loop->$field) || $this->number($r[$field]) !== Items_Service_TechnicalJson::decimal($loop->$field))) throw new InvalidArgumentException('Hydraulic CSV contradicts loop data');
					if($r['tank_kind'] !== '' && $loop->tank_kind !== $r['tank_kind']) throw new InvalidArgumentException('CSV tank topology mismatch');
					if($r['internal_loss_dataset_ref'] !== '' && Items_Service_TechnicalJson::encode($this->ref($r['internal_loss_dataset_ref'])) !== Items_Service_TechnicalJson::encode($loop->pressure_loss_dataset)) throw new InvalidArgumentException('CSV internal losses mismatch');
					if($r['usable_volume_L'] !== '') {
						$tanks = [];
						foreach($machine->data->components as $component) if($component->role === 'tank') foreach($loop->edges as $edge) if(in_array($component->instance_key.'.in', $edge, true)) $tanks[] = $component;
						if(count($tanks) !== 1) throw new InvalidArgumentException('Exact tank required for CSV volume');
						$tankRef = $context->component_datasets->{$tanks[0]->component_key} ?? null;
						if(!$tankRef) throw new InvalidArgumentException('Tank data package mapping required');
						$tank = $this->find($bundle, $tankRef->dataset_key, (int)$tankRef->revision);
						if($this->number($r['usable_volume_L']) !== Items_Service_TechnicalJson::decimal($tank->data->specification->usable_volume_L)) throw new InvalidArgumentException('CSV usable tank volume mismatch');
					}
					if($d->data->role !== 'pump' || Items_Service_TechnicalJson::encode($this->ref($r['fluid_dataset_ref'])) !== Items_Service_TechnicalJson::encode($loop->fluid_dataset)) throw new InvalidArgumentException('Pump CSV role or fluid mismatch');
					$d->data->specification = (object)['curve_ref' => $this->ref($r['pump_curve_dataset_ref']), 'curve_definition' => $r['pressure_curve_definition'], 'speed_rpm' => $this->number($r['speed_rpm']), 'frequency_Hz' => $this->number($r['frequency_Hz']), 'fluid_ref' => $this->ref($r['fluid_dataset_ref']), 'efficiency_map_ref' => null, 'power_input_ref' => null];
				}
				$d->source = $source;
				break;
			case '07-optionen-zubehoer':
				$targets = [];
				foreach($bundle->datasets as $candidate) if($candidate->dataset_type === 'machine_configuration' && ($r['variant_item_ref'] === '' || $candidate->data->machine_item_ref === $r['variant_item_ref'])) foreach($candidate->data->selected_equipment as $equipment) if($equipment->option_ref === $r['option_ref']) $targets[] = [$candidate, $equipment];
				if(count($targets) !== 1) throw new InvalidArgumentException('CSV equipment requires one exact complete machine context');
				[$d, $equipment] = $targets[0];
				if($equipment->component_key !== $r['component_key'] || $equipment->target_instance_key !== $r['target_instance_key'] || $equipment->technical_effect !== $r['technical_effect'] || $equipment->included !== $this->boolean($r['included_in_variant']) || $r['action'] !== $r['technical_effect'] || $equipment->replacement_component_key !== ($r['replacement_component_key'] ?: null) || Items_Service_TechnicalJson::encode($equipment->effect_dataset_ref) !== Items_Service_TechnicalJson::encode($this->ref($r['effect_dataset_ref']))) throw new InvalidArgumentException('CSV equipment view contradicts topology');
				$equipment->option_group_ref = $r['option_group_ref']; $equipment->component_item_ref = $r['component_item_ref'] ?: null; $equipment->base_item_ref = $r['base_item_ref'] ?: null; $equipment->variant_item_ref = $r['variant_item_ref'] ?: null;
				$equipment->source_locator = $r['source_locator'];
				break;
		}
		if(isset($d) && isset($d->dataset_type)) {
			$d->sources = $d->sources ?? [];
			$source->revision = $r['revision'];
			$encoded = Items_Service_TechnicalJson::encode($source);
			if(!in_array($encoded, array_map([Items_Service_TechnicalJson::class, 'encode'], $d->sources), true)) $d->sources[] = $source;
		}
	}
}
