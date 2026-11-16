<?php

class Items_Service_TechnicalDataset
{
	private ?Zend_Db_Adapter_Abstract $db;
	private Items_Service_TechnicalSchema $schema;
	private array $definitions = [];
	private array $plan = [];
	private array $visiting = [];
	private array $mapping = [];
	private int $owner;
	private int $actor;

	public function __construct()
	{
		$this->db = Zend_Db_Table::getDefaultAdapter();
		$this->schema = new Items_Service_TechnicalSchema();
	}

	private function context(string $action, bool $site = false): void
	{
		if(!$this->db) throw new RuntimeException('Technical database context missing');
		if(!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new RuntimeException('Technical access denied');
		$user = (array)Zend_Registry::get('User');
		$identity = Zend_Auth::getInstance()->getIdentity();
		if(!Zend_Auth::getInstance()->hasIdentity() || (int)($identity->id ?? 0) !== (int)($user['id'] ?? 0)) throw new RuntimeException('Technical access denied');
		$account = (new Users_Model_DbTable_User())->getActiveUserById((int)$user['id']);
		if(!$account) throw new RuntimeException('Technical access denied');
		$client = (array)Zend_Registry::get('Client');
		$allowed = (new Application_Model_DbTable_Client())->getSwitchableClients();
		$selected = (int)($client['id'] ?? 0);
		if($selected <= 0 || !isset($allowed[$selected])) throw new RuntimeException('Technical owner access denied');
		$owner = (int)($client['modules']['items'] ?? $selected);
		if($site) {
			if(!Zend_Registry::isRegistered('SiteContext')) throw new RuntimeException('Technical site context missing');
			$owner = (int)Zend_Registry::get('SiteContext')->getClientId();
		} elseif(Zend_Registry::isRegistered('SiteContext')) throw new RuntimeException('Private technical access is not available in a site context');
		$selectedRow = $this->db->fetchRow('SELECT id, parentid FROM client WHERE id = ? AND deleted = 0', [$selected]);
		if($owner <= 0 || !isset($allowed[$owner]) || !$selectedRow || ($owner !== $selected && $owner !== (int)$selectedRow['parentid'])) throw new RuntimeException('Technical owner access denied');
		$permission = new DEEC_Permission($user);
		if(!$permission->hasPermission('items', 'item', 'view') || !$permission->hasPermission('items', 'technicaldataset', $action)) throw new RuntimeException('Technical permission denied');
		if($action !== 'view' && !$permission->hasPermission('items', 'item', 'edit')) throw new RuntimeException('Technical write permission denied');
		$this->owner = $owner;
		$this->actor = (int)$account['id'];
	}

	private function transaction(callable $work)
	{
		if($this->db->getConnection()->inTransaction()) throw new RuntimeException('Technical operation requires its own transaction');
		$lock = 'technical:'.sha1($this->db->fetchOne('SELECT DATABASE()').':'.$this->owner);
		if((int)$this->db->fetchOne('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) throw new RuntimeException('Technical owner is busy');
		try {
			$this->db->beginTransaction();
			try { $result = $work(); $this->db->commit(); return $result; }
			catch(Throwable $e) { $this->db->rollBack(); throw $e; }
		} finally { $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]); }
	}

	public function validateJson(string $json): stdClass
	{
		$bundle = $this->schema->fields(Items_Service_TechnicalJson::decode($json), ['format' => ['dewawi-technical-import'], 'format_version' => 'int', 'datasets' => 'list:object'], ['profiles' => 'list:object', 'template_kind' => 'text', 'production_import_allowed' => 'bool', 'provenance_note' => 'longtext']);
		if($bundle->format_version !== 1 || count($bundle->datasets) > 100 || (!$bundle->datasets && empty($bundle->profiles))) throw new InvalidArgumentException('Invalid technical import version or package count');
		foreach($bundle->datasets as &$record) {
			if(isset($bundle->template_kind) || isset($bundle->provenance_note) || isset($bundle->production_import_allowed)) {
				$context = (object)['template_kind' => $bundle->template_kind ?? null, 'provenance_note' => $bundle->provenance_note ?? null, 'production_import_allowed' => $bundle->production_import_allowed ?? null];
				if(isset($record->import_context) && Items_Service_TechnicalJson::encode($record->import_context) !== Items_Service_TechnicalJson::encode($context)) throw new InvalidArgumentException('Conflicting import provenance');
				$record->import_context = $context;
			}
			$record = $this->schema->dataset($record);
		}
		unset($record);
		foreach($bundle->profiles ?? [] as $index => $record) $bundle->profiles[$index] = $this->schema->profile($record);
		$keys = [];
		foreach($bundle->datasets as $d) {
			$key = 'dataset:'.$d->dataset_key.'@'.$d->revision;
			if(isset($keys[$key])) throw new InvalidArgumentException('Duplicate technical dataset identity');
			$keys[$key] = true;
		}
		foreach($bundle->profiles ?? [] as $p) {
			$key = 'profile:'.$p->profile_key.'@'.$p->revision;
			if(isset($keys[$key])) throw new InvalidArgumentException('Duplicate technical profile identity');
			$keys[$key] = true;
		}
		return $bundle;
	}

	public function importJson(string $json, array $mapping = [], bool $dryRun = false): array
	{
		$this->context('import');
		$bundle = $this->validateJson($json);
		$this->mapping = $this->validateMapping($mapping);
		return $this->transaction(function() use ($bundle, $dryRun) {
			$this->definitions = $this->plan = $this->visiting = [];
			foreach($bundle->datasets as $d) $this->define('technicaldataset', $d->dataset_key, $d->revision, $d);
			foreach($bundle->profiles ?? [] as $p) $this->define('itemtechnicalprofile', $p->profile_key, $p->revision, $p);
			foreach(array_keys($this->definitions) as $key) $this->prepare($key);
			$this->checkConflicts();
			if($dryRun) return ['status' => 'review_required', 'dry_run' => true, 'planned' => count($this->plan)];
			$result = [];
			foreach($this->plan as $key => $node) $result[$key] = $this->persist($key, $node, $result);
			return ['status' => 'review_required', 'dry_run' => false, 'revisions' => $result];
		});
	}

	private function validateMapping(array $mapping): array
	{
		if(array_diff(array_keys($mapping), ['items', 'options', 'groups', 'manufacturers'])) throw new InvalidArgumentException('Unknown technical mapping category');
		foreach($mapping as $category => $entries) {
			if(!is_array($entries) || count($entries) > 5000) throw new InvalidArgumentException('Invalid technical mapping');
			foreach($entries as $symbol => $id) {
				$this->schema->fields((object)['symbol' => $symbol, 'id' => $id], ['symbol' => 'key', 'id' => 'int']);
				if(!is_int($id) || $id <= 0) throw new InvalidArgumentException('Mapping requires exact integer IDs');
			}
		}
		return $mapping;
	}

	private function key(string $table, string $key, int $revision): string { return $table.':'.$key.'@'.$revision; }

	private function define(string $table, string $key, int $revision, stdClass $record): void
	{
		$key = $this->key($table, $key, $revision);
		if(isset($this->definitions[$key])) throw new InvalidArgumentException('Duplicate technical revision in import');
		$this->definitions[$key] = ['table' => $table, 'record' => $record];
	}

	private function row(string $table, string $key, int $revision): ?array
	{
		$column = $table === 'technicaldataset' ? 'dataset_key' : 'profile_key';
		$row = $this->db->fetchRow('SELECT * FROM '.$table.' WHERE clientid = ? AND '.$column.' = ? AND revision = ?', [$this->owner, $key, $revision]);
		return $row ?: null;
	}

	private function dependency(string $table, string $key, int $revision): string
	{
		$id = $this->key($table, $key, $revision);
		if(isset($this->definitions[$id])) $this->prepare($id);
		elseif(!isset($this->plan[$id])) {
			$row = $this->row($table, $key, $revision);
			if(!$row) throw new InvalidArgumentException('Unknown or inaccessible technical revision');
			if(isset($this->visiting[$id]) || count($this->visiting) >= 32) throw new InvalidArgumentException('Cyclic persisted technical reference');
			$this->visiting[$id] = true;
			$payload = $this->verifyRow($table, $row);
			foreach($payload->references as $ref) {
				$child = $this->dependency($ref->table, $ref->key, $ref->revision);
				$childRow = $this->plan[$child]['existing'] ?? null;
				if($childRow && $this->identity($ref->table, $childRow)['sha256'] !== $ref->sha256) throw new RuntimeException('Technical dependency hash mismatch');
			}
			unset($this->visiting[$id]);
			$this->plan[$id] = ['table' => $table, 'existing' => $row];
		}
		return $id;
	}

	private function prepare(string $key): void
	{
		if(isset($this->plan[$key])) return;
		if(isset($this->visiting[$key]) || count($this->visiting) >= 32) throw new InvalidArgumentException('Cyclic or excessively nested technical reference');
		$this->visiting[$key] = true;
		$node = $this->definitions[$key];
		$r = $node['record']; $table = $node['table'];
		$node['dependencies'] = []; $node['items'] = [];
		if($table === 'technicaldataset') {
			if(isset($r->manufacturer_ref)) $this->mapped('manufacturers', $r->manufacturer_ref, 'manufacturer');
			$this->collectReferences($r->data, $node['dependencies']);
			$this->referenceTypes($r->data);
			if($r->dataset_type === 'machine_configuration') $this->machineBindings($r->data, $node);
			if($r->dataset_type === 'component_spec') $this->exchangerBinding($r);
			if($this->hydraulicArticle($r)) $node['items'][$r->data->component_key] = $this->item($r->data->component_key);
			$this->fluidHydraulicBinding($r);
			if($r->dataset_type === 'reference_points') foreach($r->data->records as $point) {
				$target = $this->recordOf($this->key('technicaldataset', $point->dataset_ref->dataset_key, $point->dataset_ref->revision));
				if($point->reference_kind === 'mathematical_polynomial' && ($target->dataset_type !== 'compressor_polynomial' || !isset($point->inputs->Te_degC, $point->inputs->Tc_degC) || count(get_object_vars($point->inputs)) !== 2)) throw new InvalidArgumentException('Polynomial reference requires a polynomial and exact Te/Tc inputs');
				if($target->dataset_type === 'compressor_polynomial' && !isset($target->data->quantities->{$point->quantity})) throw new InvalidArgumentException('Reference quantity is absent from the target curve');
				if(isset($point->machine_profile_ref)) {
					$profile = $this->recordOf($this->key('itemtechnicalprofile', $point->machine_profile_ref->profile_key, $point->machine_profile_ref->revision));
					if($profile->role !== 'machine' || Items_Service_TechnicalJson::encode($profile->dataset_ref) !== Items_Service_TechnicalJson::encode($point->dataset_ref)) throw new InvalidArgumentException('Reference machine profile mismatch');
				}
			}
		} else {
			$node['dependencies'][] = $this->dependency('technicaldataset', $r->dataset_ref->dataset_key, $r->dataset_ref->revision);
			$node['items'][$r->item_ref] = $this->item($r->item_ref);
			$this->profileBinding($r, $node);
		}
		if(isset($r->supersedes)) {
			$previousKey = $table === 'technicaldataset' ? $r->supersedes->dataset_key : $r->supersedes->profile_key;
			$ownKey = $table === 'technicaldataset' ? $r->dataset_key : $r->profile_key;
			if($previousKey !== $ownKey || $r->supersedes->revision >= $r->revision) throw new InvalidArgumentException('Invalid preceding revision');
			$node['previous'] = $this->dependency($table, $previousKey, $r->supersedes->revision);
		}
		$node['dependencies'] = array_values(array_unique($node['dependencies']));
		sort($node['dependencies']); ksort($node['items']);
		$this->plan[$key] = $node;
		unset($this->visiting[$key]);
	}

	private function collectReferences($value, array &$dependencies): void
	{
		if($value instanceof stdClass && isset($value->dataset_key, $value->revision) && count(get_object_vars($value)) === 2) {
			$dependencies[] = $this->dependency('technicaldataset', $value->dataset_key, (int)$value->revision);
			return;
		}
		if($value instanceof stdClass && isset($value->profile_key)) {
			$revision = $value->profile_revision ?? $value->revision ?? null;
			if($revision !== null) $dependencies[] = $this->dependency('itemtechnicalprofile', $value->profile_key, (int)$revision);
		}
		if(is_array($value) || is_object($value)) foreach($value as $child) $this->collectReferences($child, $dependencies);
	}

	private function referenceTypes($value, string $field = ''): void
	{
		if($value instanceof stdClass && isset($value->dataset_key, $value->revision) && count(get_object_vars($value)) === 2) {
			$record = $this->recordOf($this->key('technicaldataset', $value->dataset_key, $value->revision));
			$required = ['family_ref' => 'machine_family', 'polynomial_ref' => 'compressor_polynomial', 'effect_dataset_ref' => 'component_spec', 'thermal_map_ref' => 'heat_exchanger_map', 'thermal_model_ref' => 'thermal_balance_model', 'circuit_model_ref' => 'thermal_balance_model', 'fluid_ref' => 'fluid_properties', 'fluid_dataset' => 'fluid_properties', 'pressure_loss_ref' => 'pressure_loss_map', 'external_loss_ref' => 'pressure_loss_map', 'pressure_loss_dataset' => 'hydraulic_path', 'curve_ref' => 'pump_curve', 'operating_model_ref' => 'operating_mode_model', 'energy_boundary_ref' => 'thermal_balance_model'];
			if(isset($required[$field]) && ($record->dataset_type !== $required[$field] || ($field === 'effect_dataset_ref' && ($record->data->role ?? '') !== 'technical_option'))) throw new InvalidArgumentException('Technical reference type mismatch');
			if(!isset($required[$field]) && $field !== 'dataset_ref') throw new InvalidArgumentException('Referenced map schema is not implemented in D2B');
			if($field === 'dataset_ref' && !in_array($record->dataset_type, ['compressor_polynomial', 'machine_configuration', 'component_spec'], true)) throw new InvalidArgumentException('Invalid reference-point target');
			return;
		}
		if(is_array($value) || is_object($value)) foreach($value as $name => $child) $this->referenceTypes($child, is_string($name) ? $name : $field);
	}

	private function mapped(string $category, string $symbol, string $table): array
	{
		$id = $this->mapping[$category][$symbol] ?? null;
		if(!$id) throw new InvalidArgumentException('Missing explicit technical identity mapping');
		$row = $this->db->fetchRow('SELECT * FROM '.$table.' WHERE id = ? AND clientid = ? AND deleted = 0 FOR UPDATE', [$id, $this->owner]);
		if(!$row) throw new InvalidArgumentException('Unknown or inaccessible mapped identity');
		return $row;
	}

	private function item(string $symbol): array
	{
		$row = $this->mapped('items', $symbol, 'item');
		return ['id' => (int)$row['id'], 'parentid' => (int)($row['parentid'] ?? 0), 'manufacturersku' => trim((string)$row['manufacturersku']) === '' ? null : $row['manufacturersku'], 'manufacturerid' => (int)$row['manufacturerid'] > 0 ? (int)$row['manufacturerid'] : null];
	}

	private function options(int $itemId, array $included, array $extra): array
	{
		$item = $this->db->fetchRow('SELECT parentid FROM item WHERE id = ? AND clientid = ? AND deleted = 0 FOR UPDATE', [$itemId, $this->owner]);
		if(!$item) throw new InvalidArgumentException('Unknown or inaccessible technical article');
		$base = (int)($item['parentid'] ?: $itemId);
		$seen = $ids = [];
		foreach(array_merge($included, $extra) as $symbol) {
			$option = $this->mapped('options', $symbol, 'itemopt');
			$group = $this->db->fetchRow('SELECT * FROM itemoptset WHERE id = ? AND clientid = ? AND parentid = ? AND deleted = 0 FOR UPDATE', [(int)$option['optsetid'], $this->owner, $base]);
			if(!$group || (int)$option['parentid'] !== $base || isset($seen[$option['id']])) throw new InvalidArgumentException('Invalid or duplicated technical option');
			if((int)$option['itemid'] > 0 && !$this->db->fetchRow('SELECT id FROM item WHERE id = ? AND clientid = ? AND deleted = 0 FOR UPDATE', [(int)$option['itemid'], $this->owner])) throw new InvalidArgumentException('Inaccessible accessory article');
			$seen[$option['id']] = true;
			$ids[$symbol] = ['id' => (int)$option['id'], 'groupid' => (int)$option['optsetid'], 'itemid' => (int)$option['itemid'], 'parentid' => $base];
		}
		$actual = $this->db->fetchCol('SELECT itemoptid FROM itemvariantopt WHERE itemid = ? AND clientid = ? AND deleted = 0 FOR UPDATE', [$itemId, $this->owner]);
		$expected = array_map(fn($symbol) => $ids[$symbol]['id'], $included);
		sort($actual, SORT_NUMERIC); sort($expected, SORT_NUMERIC);
		if($actual != $expected || (!$item['parentid'] && $included)) throw new InvalidArgumentException('Variant option signature mismatch');
		ksort($ids);
		return $ids;
	}

	private function profileBinding(stdClass $profile, array &$node): void
	{
		$item = $node['items'][$profile->item_ref];
		$b = $profile->binding;
		if(isset($b->manufacturer_ref)) {
			$manufacturer = $this->mapped('manufacturers', $b->manufacturer_ref, 'manufacturer');
			if($item['manufacturerid'] !== (int)$manufacturer['id']) throw new InvalidArgumentException('Manufacturer identity mismatch');
		}
		if($b->manufacturersku !== $item['manufacturersku']) throw new InvalidArgumentException('Manufacturer ordering code mismatch');
		if(isset($b->base_item_ref)) {
			$base = $this->item($b->base_item_ref);
			if($item['parentid'] !== $base['id']) throw new InvalidArgumentException('Variant base identity mismatch');
			$node['items'][$b->base_item_ref] = $base;
		} elseif($item['parentid']) throw new InvalidArgumentException('Variant requires its exact base reference');
		$node['options'] = $this->options($item['id'], $b->included_option_refs, $b->extra_option_refs);
		$dataset = $this->recordOf($node['dependencies'][0]);
		$expectedRole = $dataset->dataset_type === 'machine_configuration' ? 'machine' : ($dataset->dataset_type === 'compressor_polynomial' ? 'compressor' : ($dataset->data->role ?? null));
		if($profile->role !== $expectedRole || (isset($dataset->data->model) && $b->model !== $dataset->data->model)) throw new InvalidArgumentException('Technical profile type or model mismatch');
		if(property_exists($dataset->data, 'electrical') && Items_Service_TechnicalJson::encode($dataset->data->electrical) !== Items_Service_TechnicalJson::encode($b->electrical)) throw new InvalidArgumentException('Technical electrical execution mismatch');
	}

	private function recordOf(string $key): stdClass
	{
		$node = $this->plan[$key];
		return $node['record'] ?? $this->verifyRow($node['table'], $node['existing'])->record;
	}

	private function machineBindings(stdClass $data, array &$node): void
	{
		$machine = $this->item($data->machine_item_ref);
		$node['items'][$data->machine_item_ref] = $machine;
		if($data->base_item_ref !== null) {
			$base = $this->item($data->base_item_ref);
			if($machine['parentid'] !== $base['id']) throw new InvalidArgumentException('Machine variant base mismatch');
			$node['items'][$data->base_item_ref] = $base;
		} elseif($machine['parentid']) throw new InvalidArgumentException('Machine variant base required');
		$node['options'] = $this->options($machine['id'], $data->variant_signature->included_option_refs, $data->variant_signature->extra_option_refs);
		$family = $this->recordOf($this->key('technicaldataset', $data->family_ref->dataset_key, $data->family_ref->revision));
		$chassis = array_values(array_filter($family->data->chassis ?? [], fn($c) => $c->chassis_key === $data->chassis_key));
		if($family->dataset_type !== 'machine_family' || count($chassis) !== 1 || !in_array($data->installation_kind, $chassis[0]->installation_kinds, true)) throw new InvalidArgumentException('Machine chassis or installation mismatch');
		if(isset($data->thermal_model_ref)) {
			$ref = $data->thermal_model_ref;
			$model = $this->recordOf($this->key('technicaldataset', $ref->dataset_key, $ref->revision));
			$this->schema->thermalMachineBinding($data, $model->data);
			foreach($model->data->circuits ?? [] as $assignment) {
				$ref = $assignment->circuit_model_ref; $single = $this->recordOf($this->key('technicaldataset', $ref->dataset_key, $ref->revision));
				if($single->data->contract_version !== 1 || $single->data->circuit_key !== $assignment->circuit_key) throw new InvalidArgumentException('Parallel thermal circuit model mismatch');
			}
		}
		foreach($data->selected_equipment as $equipment) {
			$option = $node['options'][$equipment->option_ref] ?? null;
			if(!$option) throw new InvalidArgumentException('Unknown selected option');
			if($option['itemid'] > 0 && $this->item($equipment->component_key)['id'] !== $option['itemid']) throw new InvalidArgumentException('Technical equipment article mismatch');
			if(isset($equipment->option_group_ref)) {
				$group = $this->mapped('groups', $equipment->option_group_ref, 'itemoptset');
				if($option['groupid'] !== (int)$group['id']) throw new InvalidArgumentException('Option group identity mismatch');
			}
			if(isset($equipment->component_item_ref) && $this->item($equipment->component_item_ref)['id'] !== $option['itemid']) throw new InvalidArgumentException('Option accessory identity mismatch');
			if(isset($equipment->base_item_ref) && $this->item($equipment->base_item_ref)['id'] !== ($machine['parentid'] ?: $machine['id'])) throw new InvalidArgumentException('Option base identity mismatch');
			if(isset($equipment->variant_item_ref) && $this->item($equipment->variant_item_ref)['id'] !== $machine['id']) throw new InvalidArgumentException('Option variant identity mismatch');
		}
		foreach($data->components as $component) {
			$key = $this->key('itemtechnicalprofile', $component->profile_key, $component->profile_revision);
			$profile = $this->recordOf($key);
			if($profile->role !== $component->role || $profile->item_ref !== $component->component_key) throw new InvalidArgumentException('Component profile identity mismatch');
			$datasetKey = $this->key('technicaldataset', $profile->dataset_ref->dataset_key, $profile->dataset_ref->revision);
			$dataset = $this->recordOf($datasetKey);
			if(isset($dataset->data->refrigerant) && $dataset->data->refrigerant !== null && $dataset->data->refrigerant !== $data->refrigerant) throw new InvalidArgumentException('Component refrigerant mismatch');
			if(isset($dataset->data->ports) && Items_Service_TechnicalJson::encode($dataset->data->ports) !== Items_Service_TechnicalJson::encode($component->ports)) throw new InvalidArgumentException('Component profile ports mismatch');
			if($component->role === 'evaporator' && isset($dataset->data->specification->sections)) foreach($data->circuits as $c) if(isset($c->evaporator_section) && $c->evaporator_section->instance_key === $component->instance_key && !in_array($c->evaporator_section->section_key, $dataset->data->specification->sections, true)) throw new InvalidArgumentException('Unknown evaporator section');
			foreach($model->data->secondary_groups ?? [] as $group) if($group->instance_key === $component->instance_key) {
				if($dataset->dataset_type !== 'component_spec') throw new InvalidArgumentException('Secondary component specification required');
				$this->schema->secondarySectionBinding($group, $dataset->data->specification->sections);
			}
			foreach($data->circuits as $c) if(isset($c->condenser_instance) && $c->condenser_instance === $component->instance_key && isset($c->condenser_section_key) && !in_array($c->condenser_section_key, $dataset->data->specification->sections ?? [], true)) throw new InvalidArgumentException('Unknown condenser section');
		}
		if(($data->configuration_version ?? 1) === 2) (new Items_Service_OperatingModeSchema())->machineBinding($data, function($table, $key, $revision) { return $this->recordOf($this->key($table, $key, $revision)); });
		foreach($data->circuits as $circuit) {
			$expansion = $circuit->expansion_device;
			if($expansion->profile_key === null) continue;
			$profile = $this->recordOf($this->key('itemtechnicalprofile', $expansion->profile_key, $expansion->profile_revision));
			if($profile->role !== 'technical_option' || $profile->item_ref !== $expansion->component_key) throw new InvalidArgumentException('Expansion profile identity mismatch');
			$dataset = $this->recordOf($this->key('technicaldataset', $profile->dataset_ref->dataset_key, $profile->dataset_ref->revision));
			if(!isset($dataset->data->ports) || Items_Service_TechnicalJson::encode($dataset->data->ports) !== Items_Service_TechnicalJson::encode($expansion->ports)) throw new InvalidArgumentException('Expansion profile ports mismatch');
		}

	}

	private function hydraulicArticle(stdClass $r): bool
	{
		if($r->dataset_type === 'heat_exchanger_map' && $r->data->contract_version >= 2) return true;
		if($r->dataset_type === 'component_spec') {
			if(isset($r->data->specification->refrigerant_valve)) return true;
			$ref = $r->data->specification->thermal_map_ref ?? null;
			if($ref !== null && $this->recordOf($this->key('technicaldataset', $ref->dataset_key, $ref->revision))->data->contract_version >= 2) return true;
		}
		return $r->dataset_type === 'pump_curve' || ($r->dataset_type === 'pressure_loss_map' && $r->data->role !== 'external') || ($r->dataset_type === 'component_spec' && (isset($r->data->specification->pressure_loss_ref) || isset($r->data->specification->curve_ref)));
	}

	private function fluidHydraulicBinding(stdClass $record): void
	{
		$core = new Items_Service_FluidHydraulics();
		$resolve = function($table, $key, $revision) { return $this->recordOf($this->key($table, $key, $revision)); };
		$d = $record->data;
		if($record->dataset_type === 'heat_exchanger_map' && $d->contract_version >= 2) (new Items_Service_FluidProperties())->composition($core->resolve($resolve, $d->fluid_ref, 'fluid_properties')->data, $d->fluid_composition);
		if($record->dataset_type === 'heat_exchanger_map' && $d->contract_version === 1) foreach($d->media as $medium) if(isset($medium->fluid_ref, $medium->fluid_composition)) (new Items_Service_FluidProperties())->composition($core->resolve($resolve, $medium->fluid_ref, 'fluid_properties')->data, $medium->fluid_composition);
		if($record->dataset_type === 'component_spec') {
			foreach(['pressure_loss_ref', 'curve_ref', 'thermal_map_ref'] as $field) if(isset($d->specification->$field)) {
				$ref = $d->specification->$field;
				if($field === 'thermal_map_ref' && $this->recordOf($this->key('technicaldataset', $ref->dataset_key, $ref->revision))->data->contract_version < 2) continue;
				$node = $this->plan[$this->key('technicaldataset', $ref->dataset_key, $ref->revision)];
				$items = isset($node['existing']) ? (array)$this->verifyRow('technicaldataset', $node['existing'])->items : $node['items'];
				$identity = (array)($items[$d->component_key] ?? []);
				if(($identity['id'] ?? null) !== $this->item($d->component_key)['id']) throw new InvalidArgumentException('Hydraulic component article mismatch');
			}
			if(isset($d->specification->pressure_loss_ref)) $core->mapBinding($record, $core->resolve($resolve, $d->specification->pressure_loss_ref, 'pressure_loss_map'));
			if($d->role === 'pump' && isset($d->specification->curve_ref)) $core->pumpBinding($record, $core->resolve($resolve, $d->specification->curve_ref, 'pump_curve'));
		}
		if(in_array($record->dataset_type, ['pressure_loss_map', 'pump_curve'], true)) {
			$fluid = $core->resolve($resolve, $d->fluid_ref, 'fluid_properties')->data;
			if($record->dataset_type === 'pressure_loss_map') {
				foreach([[$d->temperatures_degC, $fluid->temperatures_degC], [$d->concentrations, $fluid->concentrations]] as [$axis, $domain]) foreach($axis as $value) Items_Service_FluidProperties::bracket($domain, $value);
			} else {
				foreach([[$d->limits->temperature_degC, $fluid->temperatures_degC], [$d->limits->concentration, $fluid->concentrations]] as [$range, $axis]) { Items_Service_FluidProperties::bracket($axis, $range->min); Items_Service_FluidProperties::bracket($axis, $range->max); }
			}
		}
		if($record->dataset_type === 'machine_configuration') foreach($d->hydraulic_loops as $loop) {
			if($loop->pressure_loss_dataset === null) continue;
			$path = $core->resolve($resolve, $loop->pressure_loss_dataset, 'hydraulic_path'); $core->pathBinding($d, $loop, $path);
			if($path->data->contract_version === 2) $core->distributionBinding($d, $loop, $path, $resolve);
			if($loop->fluid_dataset === null) throw new InvalidArgumentException('Hydraulic path requires a loop fluid revision');
			$fluid = $core->resolve($resolve, $loop->fluid_dataset, 'fluid_properties');
			foreach($d->components as $component) if(in_array($component->instance_key, array_merge($path->data->component_instances, [$path->data->pump_instance]), true)) {
				$spec = $core->component($component, $resolve)->data->specification;
				foreach(['pressure_loss_ref' => 'pressure_loss_map', 'curve_ref' => 'pump_curve'] as $field => $type) if(isset($spec->$field)) $core->sameFluid($core->resolve($resolve, $spec->$field, $type)->data->fluid_ref, $fluid);
				if(isset($spec->fluid_ref)) $core->sameFluid($spec->fluid_ref, $fluid);
			}
			if($path->data->external_loss_ref !== null) {
				$external = $core->resolve($resolve, $path->data->external_loss_ref, 'pressure_loss_map'); $core->sameFluid($external->data->fluid_ref, $fluid);
				if($external->data->role !== 'external' || array_map(fn($section) => $section->section_key, $external->data->sections) !== ['whole']) throw new InvalidArgumentException('External hydraulic boundary mismatch');
			}
		}
	}

	private function exchangerBinding(stdClass $record): void
	{
		$ref = $record->data->specification->thermal_map_ref ?? null;
		if($ref === null) return;
		$map = $this->recordOf($this->key('technicaldataset', $ref->dataset_key, $ref->revision))->data;
		$this->schema->exchangerBinding($record->data, $map);
	}

	private function sourcesOf(stdClass $record): array
	{
		$sources = [$record->source];
		foreach($record->sources ?? [] as $source) $sources[] = $source;
		if($record->dataset_type === 'reference_points') foreach($record->data->records as $point) $sources[] = $point->source;
		$result = [];
		foreach($sources as $source) $result[Items_Service_TechnicalJson::encode($source)] = $source;
		ksort($result);
		return array_values($result);
	}

	private function payload(array $node, array $references): string
	{
		return Items_Service_TechnicalJson::encode((object)['normalization_version' => 1, 'record' => $node['record'], 'references' => $references, 'items' => (object)$node['items'], 'options' => (object)($node['options'] ?? [])]);
	}

	private function checkConflicts(): void
	{
		foreach($this->plan as $node) {
			if(isset($node['existing'])) continue;
			$r = $node['record']; $table = $node['table']; $column = $table === 'technicaldataset' ? 'dataset_key' : 'profile_key';
			$row = $this->row($table, $r->$column, $r->revision);
			if(!$row) continue;
			$this->verifyRow($table, $row);
			$refs = [];
			foreach($node['dependencies'] as $key) {
				$child = $this->plan[$key];
				$record = $child['record'] ?? null;
				$name = $child['table'] === 'technicaldataset' ? 'dataset_key' : 'profile_key';
				$existing = $child['existing'] ?? $this->row($child['table'], $record->$name, $record->revision);
				if(!$existing) throw new RuntimeException('Existing revision cannot gain a new dependency');
				$refs[] = $this->identity($child['table'], $existing);
			}
			$hash = $table === 'technicaldataset' ? 'payload_sha256' : 'binding_sha256';
			if(!hash_equals($row[$hash], hash('sha256', $this->payload($node, $refs)))) throw new RuntimeException('Conflicting immutable technical revision');
		}
	}

	private function persist(string $key, array $node, array $saved): array
	{
		if(isset($node['existing'])) return $this->identity($node['table'], $node['existing']);
		$table = $node['table']; $r = $node['record'];
		$references = array_map(fn($k) => $saved[$k], $node['dependencies']);
		$json = $this->payload($node, $references);
		if(strlen($json) > 1048576) throw new InvalidArgumentException('Resolved technical payload exceeds byte budget');
		$column = $table === 'technicaldataset' ? 'dataset_key' : 'profile_key';
		$data = ['clientid' => $this->owner, $column => $r->$column, 'revision' => $r->revision, 'supersedesid' => isset($node['previous']) ? $saved[$node['previous']]['id'] : null, 'status' => 'draft', 'created' => gmdate('Y-m-d H:i:s'), 'createdby' => $this->actor];
		if($table === 'technicaldataset') {
			$data += ['dataset_type' => $r->dataset_type, 'schema_version' => $r->schema_version, 'manufacturer_name' => $r->manufacturer_name, 'manufacturer_revision' => $r->manufacturer_revision, 'data_owner' => $r->data_owner, 'source_json' => Items_Service_TechnicalJson::encode($this->sourcesOf($r)), 'payload_json' => $json, 'payload_sha256' => hash('sha256', $json)];
		} else {
			$data += ['itemid' => $node['items'][$r->item_ref]['id'], 'datasetid' => $references[0]['id'], 'role' => $r->role, 'binding_json' => $json, 'binding_sha256' => hash('sha256', $json)];
		}
		$existing = $this->row($table, $r->$column, $r->revision);
		$hash = $table === 'technicaldataset' ? 'payload_sha256' : 'binding_sha256';
		if($existing) {
			$this->verifyRow($table, $existing);
			if(!hash_equals($existing[$hash], $data[$hash]) || (int)$existing['supersedesid'] !== (int)$data['supersedesid']) throw new RuntimeException('Conflicting immutable technical revision');
			return $this->identity($table, $existing);
		}
		$this->db->insert($table, $data);
		$data['id'] = (int)$this->db->lastInsertId();
		return $this->identity($table, $data);
	}

	private function identity(string $table, array $row): array
	{
		return ['table' => $table, 'id' => (int)$row['id'], 'key' => $row[$table === 'technicaldataset' ? 'dataset_key' : 'profile_key'], 'revision' => (int)$row['revision'], 'sha256' => $row[$table === 'technicaldataset' ? 'payload_sha256' : 'binding_sha256']];
	}

	private function verifyRow(string $table, array $row): stdClass
	{
		$json = $row[$table === 'technicaldataset' ? 'payload_json' : 'binding_json'];
		$hash = $row[$table === 'technicaldataset' ? 'payload_sha256' : 'binding_sha256'];
		if(!hash_equals($hash, hash('sha256', $json))) throw new RuntimeException('Technical revision hash mismatch');
		$p = $this->schema->fields(Items_Service_TechnicalJson::decode($json), ['normalization_version' => 'int', 'record' => 'object', 'references' => 'list:object', 'items' => 'object', 'options' => 'object']);
		if($p->normalization_version !== 1) throw new RuntimeException('Unsupported technical normalization version');
		$p->record = $table === 'technicaldataset' ? $this->schema->dataset($p->record) : $this->schema->profile($p->record);
		foreach($p->references as &$ref) $ref = $this->schema->fields($ref, ['table' => ['technicaldataset', 'itemtechnicalprofile'], 'id' => 'int', 'key' => 'key', 'revision' => 'int', 'sha256' => 'hash']);
		foreach($p->items as &$item) $item = $this->schema->fields($item, ['id' => 'int', 'parentid' => 'uint', 'manufacturersku' => '?text', 'manufacturerid' => '?int']);
		foreach($p->options as &$option) $option = $this->schema->fields($option, ['id' => 'int', 'groupid' => 'int', 'itemid' => 'uint', 'parentid' => 'int']);
		return $p;
	}

	public function getById(string $table, int $id, bool $site = false): ?array
	{
		if(!in_array($table, ['technicaldataset', 'itemtechnicalprofile'], true)) throw new InvalidArgumentException('Invalid technical entity');
		$this->context('view', $site);
		$row = $this->db->fetchRow('SELECT * FROM '.$table.' WHERE id = ? AND clientid = ?', [$id, $this->owner]);
		if(!$row) return null;
		$this->verifyRow($table, $row);
		return $row;
	}

	public function ownerForRead(bool $site = false): int
	{
		$this->context('view', $site);
		return $this->owner;
	}

	public function machineRevisions(int $itemId): array
	{
		$this->context('view');
		if($itemId <= 0) throw new InvalidArgumentException('Invalid machine article');
		// Machine bindings live in the immutable payload; a machine profile is optional.
		$rows = $this->db->fetchAll('SELECT * FROM technicaldataset WHERE clientid = ? AND dataset_type = ? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, CONCAT(\'$.items."\', JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.record.data.machine_item_ref\')), \'".id\'))) = ? ORDER BY dataset_key, revision DESC LIMIT 101', [$this->owner, 'machine_configuration', (string)$itemId]);
		if(count($rows) > 100) throw new RuntimeException('Too many machine revisions; narrow the technical data inventory');
		$result = [];
		foreach($rows as $row) {
			$p = $this->verifyRow('technicaldataset', $row);
			if($p->record->dataset_type !== 'machine_configuration' || (int)($p->items->{$p->record->data->machine_item_ref}->id ?? 0) !== $itemId) throw new RuntimeException('Technical article binding mismatch');
			$result[] = $this->identity('technicaldataset', $row) + ['status' => $row['status']];
		}
		return $result;
	}

	public function inspectMachine(int $itemId, int $id, int $revision, string $hash, ?callable $consume = null): array
	{
		$this->context('view');
		return $this->transaction(function() use ($itemId, $id, $revision, $hash, $consume) {
			$snapshot = $this->machineSnapshot($id, $revision, $hash);
			$p = $snapshot['machine'];
			if($itemId <= 0 || (int)($p->items->{$p->record->data->machine_item_ref}->id ?? 0) !== $itemId) throw new RuntimeException('Technical article binding mismatch');
			$records = []; $items = new stdClass();
			foreach($snapshot['graph'] as $key => $node) {
				$records[$key] = $node['payload']->record;
				foreach($node['payload']->items as $ref => $binding) {
					if(isset($items->$ref) && Items_Service_TechnicalJson::encode($items->$ref) !== Items_Service_TechnicalJson::encode($binding)) throw new RuntimeException('Conflicting technical article binding');
					$items->$ref = $binding;
				}
			}
			$inspection = ['identity' => $this->identity('technicaldataset', $snapshot['row']), 'record' => $p->record, 'items' => $items, 'options' => $p->options,
				'records' => $records, 'dependencies' => $snapshot['provenance'], 'reasons' => array_values(array_unique($snapshot['reasons'])), 'blocked' => $snapshot['blocked']];
			// Persist a bounded review under the same owner lock and graph transaction.
			return $consume ? $consume($inspection) : $inspection;
		});
	}

	public function technicalStatus(string $table, int $id): array
	{
		$this->safeTable($table);
		$this->context('view');
		return $this->transaction(function() use ($table, $id) {
			$row = $this->db->fetchRow('SELECT * FROM '.$table.' WHERE id = ? AND clientid = ? FOR UPDATE', [$id, $this->owner]);
			if(!$row) throw new InvalidArgumentException('Technical revision not found');
			$reasons = $this->readiness($table, $row, [], false);
			return ['status' => $reasons ? 'review_required' : 'approved', 'reasons' => array_values(array_unique($reasons))];
		});
	}

	public function evaluateCompressor(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		if(!in_array($mode, ['technical', 'mathematical_reference'], true)) throw new InvalidArgumentException('Unknown compressor evaluation mode');
		$this->context('view');
		if($mode === 'mathematical_reference') $this->context('import');
		$this->schema->fields((object)['id' => $id, 'revision' => $revision, 'hash' => $expectedHash], ['id' => 'int', 'revision' => 'int', 'hash' => 'hash']);
		$request = $this->schema->fields($request, ['point' => 'object'], ['context' => 'object']);
		return $this->transaction(fn() => $this->compressorEvaluation($id, $revision, $expectedHash, $request, $mode));
	}

	private function compressorEvaluation(int $id, int $revision, string $expectedHash, stdClass $request, string $mode): array
	{
		return $this->compressorPoint($this->compressorSnapshot($id, $revision, $expectedHash), $request, $mode);
	}

	private function compressorSnapshot(int $id, int $revision, string $expectedHash): array
	{
		$row = $this->db->fetchRow('SELECT * FROM technicaldataset WHERE id = ? AND clientid = ? AND revision = ? FOR UPDATE', [$id, $this->owner, $revision]);
		if(!$row || !hash_equals($row['payload_sha256'], $expectedHash)) throw new RuntimeException('Technical revision is unavailable');
		$payload = $this->verifyRow('technicaldataset', $row);
		$r = $payload->record;
		if($r->dataset_type !== 'compressor_polynomial') throw new InvalidArgumentException('A compressor polynomial dataset is required');
		if($row['dataset_key'] !== $r->dataset_key || (int)$row['revision'] !== $r->revision || $row['dataset_type'] !== $r->dataset_type || (int)$row['schema_version'] !== $r->schema_version || !in_array($row['status'], ['draft', 'approved', 'withdrawn'], true)) throw new RuntimeException('Technical revision integrity failure');
		$reasons = $this->readiness('technicaldataset', $row, [], false);
		if($row['status'] === 'withdrawn') $reasons[] = 'revision_withdrawn';
		if($r->manufacturer_name === null) $reasons[] = 'manufacturer_missing';
		if($r->manufacturer_revision === null) $reasons[] = 'manufacturer_revision_missing';
		$approval = $this->revisionApproval($row, $r, $this->sourcesOf($r));
		return compact('row', 'payload', 'r', 'reasons', 'approval');
	}

	private function compressorPoint(array $snapshot, stdClass $request, string $mode): array
	{
		extract($snapshot, EXTR_SKIP);
		$core = new Items_Service_CompressorPolynomial(); $point = $core->point($request->point); $released = !$reasons;
		$violations = isset($request->context) ? $this->compressorContext($r, $request->context) : [];
		if($mode === 'technical' && !isset($request->context)) $violations[] = 'operating_context_required';
		$region = $r->data->operating_envelope === null ? ['status' => 'review_required', 'reason' => 'operating_envelope_missing'] : $core->operatingRegion($r->data->operating_envelope, $point);
		$result = ['evaluation_mode' => $mode, 'status' => 'review_required', 'scope' => 'compressor_point_only', 'eligible' => false,
			'package' => ['id' => (int)$row['id'], 'dataset_key' => $r->dataset_key, 'revision' => $r->revision, 'schema_version' => $r->schema_version,
				'payload_sha256' => $row['payload_sha256'], 'normalization_version' => $payload->normalization_version, 'status' => $row['status'],
				'approved' => $row['approved'], 'approvedby' => $row['approvedby'] === null ? null : (int)$row['approvedby'], 'withdrawn' => $row['withdrawn'],
				'approval_sha256' => $approval, 'source_sha256s' => array_values(array_unique(array_map(fn($s) => $s->sha256, $this->sourcesOf($r))))],
			'equation_id' => $r->data->equation_id, 'calculation_version' => Items_Service_CompressorPolynomial::VERSION, 'inputs' => $point,
			'curve' => ['manufacturer_name' => $r->manufacturer_name, 'manufacturer_revision' => $r->manufacturer_revision, 'model' => $r->data->model,
				'refrigerant' => $r->data->refrigerant, 'condition_set' => $r->data->condition_set ?? $r->source->sheet ?? null],
			'conditions' => $r->data->conditions, 'temperature_basis' => $r->data->temperature_basis, 'electrical' => $r->data->electrical,
			'operating_region' => $region, 'reasons' => [], 'violations' => $violations, 'calculation' => null];
		if($mode === 'mathematical_reference') $reasons[] = 'mathematical_reference_only';
		if($region['reason'] !== null) $reasons[] = $region['reason'];
		if($mode === 'technical' && !$violations && $released && $region['status'] === 'outside') $result['status'] = 'unsuitable';
		$canCalculate = !$violations && $row['status'] !== 'withdrawn' && (($mode === 'mathematical_reference' && $region['status'] !== 'outside') || (!$reasons && $region['status'] === 'inside'));
		if($canCalculate) {
			$result['calculation'] = $core->evaluate($r, $point);
			$reasons = array_merge($reasons, $result['calculation']['reasons']);
			if($mode === 'technical' && !$reasons) $result['status'] = 'valid_compressor_point';
		}
		$result['reasons'] = array_values(array_unique(array_merge($reasons, $violations)));
		return $result;
	}

	public function evaluateMachineStage(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		if(!in_array($mode, ['technical', 'mathematical_reference'], true)) throw new InvalidArgumentException('Unknown machine evaluation mode');
		$this->context('view');
		if($mode === 'mathematical_reference') $this->context('import');
		$this->schema->fields((object)['id' => $id, 'revision' => $revision, 'hash' => $expectedHash], ['id' => 'int', 'revision' => 'int', 'hash' => 'hash']);
		return $this->transaction(function() use ($id, $revision, $expectedHash, $request, $mode) {
			extract($this->machineSnapshot($id, $revision, $expectedHash), EXTR_SKIP);
			// D4A has no released machine model; caller-supplied temperatures are reference inputs only.
			$canCalculate = !$blocked && $mode === 'mathematical_reference';
			$result = (new Items_Service_MachineStage())->evaluate($machine->record, $request, $this->machineCompressor($graph, $mode, $canCalculate));
			$result['evaluation_mode'] = $mode;
			$result['machine'] = $this->identity('technicaldataset', $row) + ['article' => $machine->items->{$machine->record->data->machine_item_ref}, 'article_ref' => $machine->record->data->machine_item_ref];
			$result['dependencies'] = $provenance;
			$result['reasons'] = array_values(array_unique(array_merge($result['reasons'], $reasons, $mode === 'mathematical_reference' ? ['mathematical_reference_only'] : ['machine_release_required_for_technical_inputs'])));
			return $result;
		});
	}

	public function evaluateFluid(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->fluidHydraulicEvaluation($id, $revision, $expectedHash, $request, $mode, 'fluid');
	}

	public function evaluateHydraulics(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->fluidHydraulicEvaluation($id, $revision, $expectedHash, $request, $mode, 'machine');
	}

	public function evaluateHydraulicDistribution(int $id, int $revision, string $hash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->fluidHydraulicEvaluation($id, $revision, $hash, $request, $mode, 'distribution');
	}

	public function evaluatePressureLoss(int $id, int $revision, string $hash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->fluidHydraulicEvaluation($id, $revision, $hash, $request, $mode, 'pressure');
	}

	public function evaluatePump(int $id, int $revision, string $hash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->fluidHydraulicEvaluation($id, $revision, $hash, $request, $mode, 'pump');
	}

	private function fluidHydraulicEvaluation(int $id, int $revision, string $hash, stdClass $request, string $mode, string $kind): array
	{
		if(!in_array($mode, ['technical', 'synthetic_reference'], true)) throw new InvalidArgumentException('Unknown fluid/hydraulic mode');
		$this->context('view'); if($mode === 'synthetic_reference') $this->context('import');
		$this->schema->fields((object)['id' => $id, 'revision' => $revision, 'hash' => $hash], ['id' => 'int', 'revision' => 'int', 'hash' => 'hash']);
		return $this->transaction(function() use ($id, $revision, $hash, $request, $mode, $kind) {
			extract($this->machineSnapshot($id, $revision, $hash, ['fluid' => 'fluid_properties', 'machine' => 'machine_configuration', 'distribution' => 'machine_configuration', 'pressure' => 'pressure_loss_map', 'pump' => 'pump_curve'][$kind]), EXTR_SKIP);
			$blocker = $blocked ? 'dependency_unavailable' : null;
			if($mode === 'synthetic_reference' && !$this->syntheticFluidHydraulicGraph($graph)) $blocker = 'synthetic_fluid_hydraulic_provenance_required';
			if($mode === 'technical' && $reasons) $blocker = 'released_fluid_hydraulic_evidence_required';
			$resolve = function($table, $key, $revision) use ($graph) { return $graph[$this->key($table, $key, $revision)]['payload']->record ?? throw new RuntimeException('Unpinned hydraulic dependency'); };
			if($blocker !== null && $kind === 'distribution') {
				$result = (new Items_Service_HydraulicDistribution())->evaluate($machine->record, $request, $resolve, $blocker);
			}
			elseif($blocker !== null) $result = ['eligible' => false, 'status' => 'review_required', 'calculation' => null, 'solution' => null, 'reasons' => [$blocker]];
			elseif($kind === 'fluid') $result = (new Items_Service_FluidProperties())->evaluate($machine->record, $request);
			else {
				$core = new Items_Service_FluidHydraulics();
				if($kind === 'distribution') $result = (new Items_Service_HydraulicDistribution())->evaluate($machine->record, $request, $resolve);
				elseif($kind === 'machine') $result = $core->evaluate($machine->record, $request, $resolve);
				else {
					$fluid = $core->resolve($resolve, $machine->record->data->fluid_ref, 'fluid_properties');
					$result = ['eligible' => false, 'status' => 'review_required', 'calculation' => null, 'reasons' => []];
					try {
						if($kind === 'pressure') {
							$q = $this->schema->fields($request, ['section_key' => 'key', 'composition' => 'object', 'temperature_degC' => 'number', 'volume_flow_m3_h' => 'positive']);
							$result['calculation'] = $core->pressure($machine->record, $fluid, $q->section_key, $q->composition, $q->temperature_degC, $q->volume_flow_m3_h);
						} else {
							$q = $this->schema->fields($request, ['composition' => 'object', 'condition' => 'object', 'required_pressure_Pa' => 'nonnegative']);
							$result['calculation'] = $core->pump($machine->record, $fluid, $q->composition, $q->condition, (float)$q->required_pressure_Pa);
						}
						$result['mathematical_status'] = 'calculated';
					} catch(DomainException $e) { $result['reasons'][] = $e->getMessage(); $result['mathematical_status'] = 'blocked'; }
				}
			}
			$result['evaluation_mode'] = $mode; $result['dependencies'] = $provenance; $result['dependency_reasons'] = $reasons;
			$result['dataset'] = $this->identity('technicaldataset', $row);
			$result['scope'] = ['fluid' => 'fluid_properties_only', 'pressure' => 'component_pressure_loss_only', 'pump' => 'pump_at_prescribed_flow_only', 'machine' => 'known_hydraulic_paths_only', 'distribution' => 'fixed_temperature_hydraulic_operating_point_only'][$kind];
			$result['original_request'] = json_decode(json_encode($request, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
			$result['technical_component_status'] = in_array($kind, ['fluid', 'pressure', 'pump'], true) && $mode === 'technical' && !$reasons && ($result['calculation'] ?? null) !== null ? 'validated' : 'not_released';
			return $result;
		});
	}

	private function syntheticFluidHydraulicGraph(array $graph): bool
	{
		foreach($graph as $node) {
			$r = $node['payload']->record;
			if($node['table'] === 'technicaldataset' && in_array($r->dataset_type, ['fluid_properties', 'pressure_loss_map', 'pump_curve', 'hydraulic_path'], true) && ($r->data->usage !== 'synthetic_test_only' || ($r->import_context->production_import_allowed ?? null) !== false)) return false;
		}
		return true;
	}

	public function evaluateOperatingMode(int $id, int $revision, string $hash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->operatingEvaluation($id, $revision, $hash, $request, $mode, false);
	}

	public function evaluateThermohydraulic(int $id, int $revision, string $hash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->operatingEvaluation($id, $revision, $hash, $request, $mode, true);
	}

	private function operatingEvaluation(int $id, int $revision, string $hash, stdClass $request, string $mode, bool $coupled): array
	{
		if(!in_array($mode, ['technical', 'synthetic_reference'], true)) throw new InvalidArgumentException('Unknown operating evaluation mode');
		$this->context('view'); if($mode === 'synthetic_reference') $this->context('import');
		$this->schema->fields((object)['id' => $id, 'revision' => $revision, 'hash' => $hash], ['id' => 'int', 'revision' => 'int', 'hash' => 'hash']);
		return $this->transaction(function() use ($id, $revision, $hash, $request, $mode, $coupled) {
			extract($this->machineSnapshot($id, $revision, $hash), EXTR_SKIP);
			$resolve = function($table, $key, $revision) use ($graph) { return $graph[$this->key($table, $key, $revision)]['payload']->record ?? throw new RuntimeException('Unpinned operating dependency'); };
			$blocker = $blocked ? 'operating_dependency_unavailable' : ($mode === 'technical' ? 'released_real_operating_mode_model_not_available' : null);
			if(($machine->record->data->configuration_version ?? 1) === 2 && $mode === 'synthetic_reference') foreach($graph as $node) if($node['table'] === 'technicaldataset' && ($node['payload']->record->import_context->production_import_allowed ?? null) !== false) $blocker = 'synthetic_operating_provenance_required';
			$evaluator = $coupled ? new Items_Service_ThermohydraulicCircuit() : new Items_Service_MachineOperatingMode();
			$result = $evaluator->evaluate($machine->record, $request, $resolve, $this->machineCompressor($graph, 'mathematical_reference', $blocker === null), $blocker);
			$result['evaluation_mode'] = $mode; $result['dependencies'] = $provenance; $result['dependency_reasons'] = $reasons;
			$result['machine'] = $this->identity('technicaldataset', $row) + ['article' => $machine->items->{$machine->record->data->machine_item_ref}];
			return $result;
		});
	}

	public function evaluateThermalCircuit(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->thermalEvaluation($id, $revision, $expectedHash, $request, $mode, false);
	}

	public function evaluateThermalMachine(int $id, int $revision, string $expectedHash, stdClass $request, string $mode = 'technical'): array
	{
		return $this->thermalEvaluation($id, $revision, $expectedHash, $request, $mode, true);
	}

	private function thermalEvaluation(int $id, int $revision, string $expectedHash, stdClass $request, string $mode, bool $multi): array
	{
		if(!in_array($mode, ['technical', 'synthetic_reference'], true)) throw new InvalidArgumentException('Unknown thermal evaluation mode');
		$this->context('view');
		if($mode === 'synthetic_reference') $this->context('import');
		$this->schema->fields((object)['id' => $id, 'revision' => $revision, 'hash' => $expectedHash], ['id' => 'int', 'revision' => 'int', 'hash' => 'hash']);
		return $this->transaction(function() use ($id, $revision, $expectedHash, $request, $mode, $multi) {
			extract($this->machineSnapshot($id, $revision, $expectedHash), EXTR_SKIP);
			$resolve = function(string $table, string $key, int $revision) use ($graph): stdClass {
				return $graph[$this->key($table, $key, $revision)]['payload']->record ?? throw new RuntimeException('Unpinned thermal dependency');
			};
			$blocker = $blocked ? 'machine_dependency_blocks_thermal_calculation' : ($mode === 'synthetic_reference' ? null : 'released_real_thermal_model_not_available');
			if(isset($request->hydraulic_conditions) && $mode === 'synthetic_reference' && !$this->syntheticFluidHydraulicGraph($graph)) $blocker = 'synthetic_fluid_hydraulic_provenance_required';
			$core = $multi ? new Items_Service_ThermalMachine() : new Items_Service_ThermalCircuit();
			$result = $core->evaluate($machine->record, $request, $resolve, $this->machineCompressor($graph, 'mathematical_reference', !$blocked), $blocker);
			$result['evaluation_mode'] = $mode;
			$result['machine'] = $this->identity('technicaldataset', $row) + ['article' => $machine->items->{$machine->record->data->machine_item_ref}, 'article_ref' => $machine->record->data->machine_item_ref];
			$result['dependencies'] = $provenance;
			$result['dependency_reasons'] = $reasons;
			return $result;
		});
	}

	private function machineSnapshot(int $id, int $revision, string $expectedHash, string $requiredType = 'machine_configuration'): array
	{
		$row = $this->db->fetchRow('SELECT * FROM technicaldataset WHERE id = ? AND clientid = ? AND revision = ? FOR UPDATE', [$id, $this->owner, $revision]);
		if(!$row || !hash_equals($row['payload_sha256'], $expectedHash)) throw new RuntimeException('Technical revision is unavailable');
		$graph = [];
		$root = $this->machineDependency('technicaldataset', $row, $graph, []);
		$machine = $graph[$root]['payload'];
		if($machine->record->dataset_type !== $requiredType) throw new InvalidArgumentException('Unexpected technical evaluation dataset type');
		$this->machineGraphBindings($graph);
		$reasons = array_values(array_unique($this->readiness('technicaldataset', $row, [], false)));
		$blocked = (bool)array_intersect($reasons, ['article_identity_changed', 'option_identity_changed', 'variant_signature_changed']);
		$provenance = [];
		foreach($graph as $key => $node) {
			$r = $node['row']; $p = $node['payload']; $table = $node['table'];
			if($r['status'] === 'withdrawn') { $blocked = true; $reasons[] = 'dependency_withdrawn'; }
			$sources = $table === 'technicaldataset' ? $this->sourcesOf($p->record) : $this->sourcesOf($graph[$this->key('technicaldataset', $p->record->dataset_ref->dataset_key, $p->record->dataset_ref->revision)]['payload']->record);
			if($table === 'itemtechnicalprofile' && isset($p->record->binding->source)) $sources[] = $p->record->binding->source;
			$approval = $this->revisionApproval($r, $p->record, $sources);
			if($r['status'] === 'approved') {
				$proof = Items_Service_TechnicalJson::decode($r['approval_json']);
				$references = [];
				foreach($proof->references as $released) {
					$released = $this->schema->fields($released, ['revision' => 'object', 'status' => ['approved'], 'approved' => 'text', 'approvedby' => 'int', 'approval_sha256' => 'hash']);
					$ref = $this->schema->fields($released->revision, ['table' => ['technicaldataset', 'itemtechnicalprofile'], 'id' => 'int', 'key' => 'key', 'revision' => 'int', 'sha256' => 'hash']);
					$references[] = $ref;
					$child = $graph[$this->key($ref->table, $ref->key, $ref->revision)]['row'] ?? null;
					if(!$child || $released->approved !== $child['approved'] || $released->approvedby !== (int)$child['approvedby'] || !hash_equals($released->approval_sha256, hash('sha256', (string)$child['approval_json']))) throw new RuntimeException('Technical dependency approval integrity failure');
				}
				if(Items_Service_TechnicalJson::encode($references) !== Items_Service_TechnicalJson::encode($p->references)) throw new RuntimeException('Technical approval reference integrity failure');
			}
			$provenance[$key] = $this->identity($table, $r) + ['schema_version' => $table === 'technicaldataset' ? $p->record->schema_version : 1,
				'normalization_version' => $p->normalization_version, 'status' => $r['status'], 'approved' => $r['approved'], 'approvedby' => $r['approvedby'] === null ? null : (int)$r['approvedby'],
				'withdrawn' => $r['withdrawn'], 'approval_sha256' => $approval, 'sources' => $sources, 'references' => $p->references, 'items' => $p->items, 'options' => $p->options];
		}
		return compact('row', 'graph', 'machine', 'reasons', 'blocked', 'provenance');
	}

	private function machineCompressor(array $graph, string $mode, bool $canCalculate): callable
	{
		$snapshots = [];
		return function($component, $point, $context) use ($graph, $mode, $canCalculate, &$snapshots) {
			$profile = $graph[$this->key('itemtechnicalprofile', $component->profile_key, $component->profile_revision)]['payload']->record;
			$node = $graph[$this->key('technicaldataset', $profile->dataset_ref->dataset_key, $profile->dataset_ref->revision)];
			$record = $node['payload']->record;
			if($record->dataset_type === 'component_spec') {
				$ref = $record->data->specification->polynomial_ref;
				if($ref === null) return ['status' => 'review_required', 'eligible' => false, 'calculation' => null, 'reasons' => ['compressor_polynomial_missing']];
				$node = $graph[$this->key('technicaldataset', $ref->dataset_key, $ref->revision)];
			}
			$r = $node['row']; $input = (object)['point' => $point];
			if($context !== null) $input->context = $context;
			$cacheKey = (int)$r['id'];
			if(!isset($snapshots[$cacheKey])) $snapshots[$cacheKey] = $this->compressorSnapshot($cacheKey, (int)$r['revision'], $r['payload_sha256']);
			$evaluation = $this->compressorPoint($snapshots[$cacheKey], $input, $mode);
			if(!$canCalculate) { $evaluation['calculation'] = null; $evaluation['status'] = 'review_required'; $evaluation['reasons'][] = 'machine_dependency_or_release_blocks_calculation'; }
			return $evaluation;
		};
	}

	private function machineDependency(string $table, array $row, array &$graph, array $path): string
	{
		$payload = $this->verifyRow($table, $row); $record = $payload->record;
		$column = $table === 'technicaldataset' ? 'dataset_key' : 'profile_key';
		$key = $this->key($table, $record->$column, $record->revision);
		if(isset($path[$key]) || count($path) >= 32) throw new RuntimeException('Invalid persisted technical dependency graph');
		if($row[$column] !== $record->$column || (int)$row['revision'] !== $record->revision || !in_array($row['status'], ['draft', 'approved', 'withdrawn'], true)) throw new RuntimeException('Technical revision integrity failure');
		if($table === 'technicaldataset' && ($row['dataset_type'] !== $record->dataset_type || (int)$row['schema_version'] !== $record->schema_version)) throw new RuntimeException('Technical dataset header integrity failure');
		if(isset($record->supersedes)) {
			$previous = $this->row($table, $record->supersedes->$column, $record->supersedes->revision);
			if(!$previous || $record->supersedes->$column !== $record->$column || $record->supersedes->revision >= $record->revision || (int)$row['supersedesid'] !== (int)$previous['id']) throw new RuntimeException('Technical revision history integrity failure');
		} elseif($row['supersedesid'] !== null) throw new RuntimeException('Unexpected technical predecessor');
		if(isset($graph[$key])) {
			if($this->identity($table, $graph[$key]['row']) !== $this->identity($table, $row)) throw new RuntimeException('Conflicting technical dependency identity');
			return $key;
		}
		$path[$key] = true;
		foreach($payload->references as $ref) {
			$child = $this->db->fetchRow('SELECT * FROM '.$this->safeTable($ref->table).' WHERE id = ? AND clientid = ? FOR UPDATE', [$ref->id, $this->owner]);
			if(!$child || $this->identity($ref->table, $child) !== (array)$ref) throw new RuntimeException('Technical dependency integrity failure');
			$this->machineDependency($ref->table, $child, $graph, $path);
		}
		$graph[$key] = ['table' => $table, 'row' => $row, 'payload' => $payload];
		return $key;
	}

	private function machineGraphBindings(array $graph): void
	{
		$saved = [$this->plan, $this->definitions, $this->visiting, $this->mapping];
		try {
			$this->plan = $this->definitions = $this->visiting = [];
			foreach($graph as $key => $node) $this->plan[$key] = ['table' => $node['table'], 'record' => $node['payload']->record, 'items' => array_map(fn($item) => (array)$item, (array)$node['payload']->items)];
			foreach($graph as $node) {
				$p = $node['payload']; $r = $p->record; $this->mapping = ['items' => [], 'options' => [], 'manufacturers' => [], 'groups' => []];
				foreach($p->items as $symbol => $item) $this->mapping['items'][$symbol] = $item->id;
				foreach($p->options as $symbol => $option) $this->mapping['options'][$symbol] = $option->id;
				$dependencies = [];
				if($node['table'] === 'technicaldataset') { $this->collectReferences($r->data, $dependencies); $this->referenceTypes($r->data); }
				else $dependencies[] = $this->key('technicaldataset', $r->dataset_ref->dataset_key, $r->dataset_ref->revision);
				$actual = array_map(fn($ref) => $this->key($ref->table, $ref->key, $ref->revision), $p->references);
				$expected = array_values(array_unique($dependencies)); sort($actual); sort($expected);
				if($actual !== $expected || count($this->plan) !== count($graph)) throw new RuntimeException('Unpinned technical dependency');
				if($node['table'] === 'technicaldataset') { if($r->dataset_type === 'component_spec') $this->exchangerBinding($r); $this->fluidHydraulicBinding($r); }
				if($node['table'] === 'technicaldataset' && $this->hydraulicArticle($r)) {
					if(array_keys((array)$p->items) !== [$r->data->component_key]) throw new RuntimeException('Unpinned hydraulic component article');
				}
				$binding = ['items' => array_map(fn($item) => (array)$item, (array)$p->items), 'dependencies' => $dependencies];
				if($node['table'] === 'itemtechnicalprofile') {
					$target = $graph[$this->key('technicaldataset', $r->dataset_ref->dataset_key, $r->dataset_ref->revision)];
					if((int)$node['row']['itemid'] !== ($p->items->{$r->item_ref}->id ?? 0) || (int)$node['row']['datasetid'] !== (int)$target['row']['id'] || $node['row']['role'] !== $r->role) throw new RuntimeException('Technical profile header integrity failure');
					if(isset($r->binding->manufacturer_ref)) $this->mapping['manufacturers'][$r->binding->manufacturer_ref] = $p->items->{$r->item_ref}->manufacturerid;
					$this->profileBinding($r, $binding);
				} elseif($r->dataset_type === 'machine_configuration') {
					$machine = $p->items->{$r->data->machine_item_ref};
					foreach($r->data->selected_equipment as $equipment) {
						$option = $p->options->{$equipment->option_ref};
						if(isset($equipment->option_group_ref)) $this->mapping['groups'][$equipment->option_group_ref] = $option->groupid;
						$items = $option->itemid > 0 ? [$equipment->component_key => $option->itemid] : [];
						if(isset($equipment->component_item_ref)) $items[$equipment->component_item_ref] = $option->itemid;
						if(isset($equipment->base_item_ref)) $items[$equipment->base_item_ref] = $machine->parentid ?: $machine->id;
						if(isset($equipment->variant_item_ref)) $items[$equipment->variant_item_ref] = $machine->id;
						foreach($items as $symbol => $itemId) {
							if(isset($this->mapping['items'][$symbol]) && $this->mapping['items'][$symbol] !== $itemId) throw new RuntimeException('Conflicting equipment article binding');
							$this->mapping['items'][$symbol] = $itemId;
						}
					}
					$this->machineBindings($r->data, $binding);
				} elseif($r->dataset_type === 'component_spec' && $r->data->role === 'compressor' && $r->data->specification->polynomial_ref !== null) {
					$ref = $r->data->specification->polynomial_ref;
					$curve = $graph[$this->key('technicaldataset', $ref->dataset_key, $ref->revision)]['payload']->record;
					if($curve->data->model !== $r->data->model || $curve->data->refrigerant !== $r->data->refrigerant || Items_Service_TechnicalJson::encode($curve->data->electrical) !== Items_Service_TechnicalJson::encode($r->data->electrical)) throw new RuntimeException('Compressor curve binding mismatch');
				}
			}
		} finally { [$this->plan, $this->definitions, $this->visiting, $this->mapping] = $saved; }
	}

	private function compressorContext(stdClass $record, stdClass $context): array
	{
		$c = $this->schema->fields($context, ['manufacturer_name' => '?text', 'manufacturer_revision' => '?revision_text', 'model' => 'text', 'refrigerant' => 'key', 'conditions' => 'object', 'temperature_basis' => 'object', 'electrical' => 'object']);
		$expected = clone $record; $expected->data = clone $record->data;
		$expected->manufacturer_name = $c->manufacturer_name; $expected->manufacturer_revision = $c->manufacturer_revision;
		foreach(['model', 'refrigerant', 'conditions', 'temperature_basis', 'electrical'] as $key) $expected->data->$key = $c->$key;
		$expected = $this->schema->dataset($expected);
		$reasons = [];
		foreach(['manufacturer_name', 'manufacturer_revision'] as $key) if($record->$key !== $expected->$key) $reasons[] = $key.'_mismatch';
		foreach(['model', 'refrigerant', 'conditions', 'temperature_basis', 'electrical'] as $key) if(Items_Service_TechnicalJson::encode($record->data->$key) !== Items_Service_TechnicalJson::encode($expected->data->$key)) $reasons[] = $key.'_mismatch';
		return $reasons;
	}

	private function revisionApproval(array $row, stdClass $record, array $sources): ?string
	{
		if($row['status'] !== 'approved') return $row['approval_json'] === null ? null : hash('sha256', $row['approval_json']);
		if($row['approval_json'] === null || $row['approved'] === null || (int)$row['approvedby'] <= 0) throw new RuntimeException('Technical approval integrity failure');
		$proof = $this->schema->fields(Items_Service_TechnicalJson::decode($row['approval_json']), ['evidence' => 'object', 'revision_sha256' => 'hash', 'references' => 'list:object']);
		$evidence = $this->schema->fields($proof->evidence, ['scope_key' => 'key', 'rights_reference' => 'text', 'engineering_reference' => 'text', 'source_sha256s' => 'list:hash']);
		$hashes = array_values(array_unique(array_map(fn($s) => $s->sha256, $sources)));
		$actual = $evidence->source_sha256s; sort($hashes); sort($actual);
		$hash = isset($record->dataset_type) ? $row['payload_sha256'] : $row['binding_sha256'];
		if(!hash_equals($proof->revision_sha256, $hash) || $hashes !== $actual || (!isset($record->dataset_type) && $evidence->scope_key !== $record->binding->scope_key)) throw new RuntimeException('Technical approval integrity failure');
		return hash('sha256', $row['approval_json']);
	}

	private function readiness(string $table, array $row, array $visited, bool $approving): array
	{
		$key = $table.':'.$row['id'];
		if(isset($visited[$key]) || count($visited) >= 32) throw new RuntimeException('Invalid persisted technical dependency graph');
		$visited[$key] = true;
		$payload = $this->verifyRow($table, $row);
		$reasons = [];
		if(!$approving && $row['status'] !== 'approved') $reasons[] = 'revision_not_approved';
		foreach($payload->references as $ref) {
			$dependency = $this->db->fetchRow('SELECT * FROM '.$this->safeTable($ref->table).' WHERE id = ? AND clientid = ?', [(int)$ref->id, $this->owner]);
			if(!$dependency || $this->identity($ref->table, $dependency)['sha256'] !== $ref->sha256) throw new RuntimeException('Technical dependency integrity failure');
			$reasons = array_merge($reasons, $this->readiness($ref->table, $dependency, $visited, false));
		}
		foreach($payload->items as $identity) {
			$current = $this->db->fetchRow('SELECT id, parentid, manufacturersku, manufacturerid FROM item WHERE id = ? AND clientid = ? AND deleted = 0 FOR UPDATE', [(int)$identity->id, $this->owner]);
			if(!$current || (int)$current['parentid'] !== (int)$identity->parentid || (trim((string)$current['manufacturersku']) === '' ? null : $current['manufacturersku']) !== $identity->manufacturersku || (int)$current['manufacturerid'] !== (int)$identity->manufacturerid) $reasons[] = 'article_identity_changed';
		}
		foreach($payload->options as $identity) {
			$current = $this->db->fetchRow('SELECT o.optsetid, o.itemid, o.parentid FROM itemopt o JOIN itemoptset g ON g.id = o.optsetid AND g.parentid = o.parentid AND g.clientid = o.clientid AND g.deleted = 0 WHERE o.id = ? AND o.clientid = ? AND o.deleted = 0 FOR UPDATE', [(int)$identity->id, $this->owner]);
			if(!$current || (int)$current['optsetid'] !== (int)$identity->groupid || (int)$current['itemid'] !== (int)$identity->itemid || (int)$current['parentid'] !== (int)$identity->parentid) $reasons[] = 'option_identity_changed';
		}
		$r = $payload->record;
		if($table === 'itemtechnicalprofile') {
			$actual = $this->db->fetchCol('SELECT itemoptid FROM itemvariantopt WHERE itemid = ? AND clientid = ? AND deleted = 0', [(int)$row['itemid'], $this->owner]);
			$expected = array_map(fn($symbol) => (int)$payload->options->$symbol->id, $r->binding->included_option_refs);
			sort($actual, SORT_NUMERIC); sort($expected, SORT_NUMERIC);
			if($actual != $expected) $reasons[] = 'variant_signature_changed';
			return $reasons;
		}
		$reasons = array_merge($reasons, $r->blockers);
		if($r->data_owner === null) $reasons[] = 'data_owner_missing';
		if(isset($r->import_context) && $r->import_context->production_import_allowed === false) $reasons[] = 'source_not_authorized_for_production';
		switch($r->dataset_type) {
			case 'compressor_polynomial':
				$d = $r->data;
				if($d->operating_envelope === null) $reasons[] = 'operating_envelope_missing';
				if(in_array(null, (array)$d->temperature_basis, true)) $reasons[] = 'temperature_basis_missing';
				if(in_array(null, (array)$d->electrical, true)) $reasons[] = 'electrical_execution_missing';
				if($d->conditions->frequency_Hz === null || $d->conditions->speed_rpm === null || $d->conditions->subcooling_K === null) $reasons[] = 'reference_conditions_missing';
				break;
			case 'fluid_properties': case 'pressure_loss_map': case 'pump_curve': case 'hydraulic_path':
				if($r->data->usage === 'synthetic_test_only') $reasons[] = 'synthetic_model_not_releasable';
				$reasons = array_merge($reasons, $r->data->missing_evidence);
				if($r->dataset_type === 'fluid_properties' && $r->data->freezing_degC === null) $reasons[] = 'fluid_freezing_evidence_missing';
				if($r->dataset_type === 'hydraulic_path' && $r->data->external_loss_ref === null) $reasons[] = 'external_pressure_loss_missing';
				break;
			case 'operating_mode_model': case 'heat_exchanger_map': case 'thermal_balance_model':
				$reasons[] = 'synthetic_model_not_releasable';
				$reasons = array_merge($reasons, $r->data->missing_evidence);
				break;
			case 'machine_family':
				foreach($r->data->chassis as $chassis) if(in_array(null, (array)$chassis->dimensions, true)) $reasons[] = 'chassis_dimensions_missing';
				break;
			case 'machine_configuration':
				// D2B validates topology; machine physics and mode releases belong to D4-D6.
				$reasons[] = 'machine_physics_not_released';
				foreach($r->data->selected_equipment as $option) if($option->technical_effect === 'unknown' || $option->source_locator === null || ($option->technical_effect !== 'neutral_verified' && $option->effect_dataset_ref === null)) $reasons[] = 'option_effect_unverified';
				break;
			case 'component_spec':
				// Only complete tank identity/specification can be released without the future map types.
				if($r->data->role !== 'tank' || $r->data->specification->tank_kind === 'unconfirmed' || in_array(null, (array)$r->data->specification, true)) $reasons[] = 'component_performance_data_missing';
				break;
			case 'reference_points':
				foreach($r->data->records as $point) if($point->reference_kind === 'mathematical_polynomial' || $point->engineering_approval === null) $reasons[] = 'independent_engineering_reference_missing';
				break;
		}
		return $reasons;
	}

	private function safeTable(string $table): string
	{
		if(!in_array($table, ['technicaldataset', 'itemtechnicalprofile'], true)) throw new InvalidArgumentException('Invalid technical table');
		return $table;
	}

	public function approve(string $table, int $id, string $expectedHash, string $evidenceJson): void
	{
		$this->safeTable($table);
		$this->context('approve');
		$evidence = $this->schema->fields(Items_Service_TechnicalJson::decode($evidenceJson), ['scope_key' => 'key', 'rights_reference' => 'text', 'engineering_reference' => 'text', 'source_sha256s' => 'list:hash']);
		$this->transaction(function() use ($table, $id, $expectedHash, $evidence) {
			$row = $this->locked($table, $id, 'draft', $expectedHash);
			if($this->readiness($table, $row, [], true)) throw new RuntimeException('Technical engineering review is required');
			$payload = $this->verifyRow($table, $row);
			$sources = $table === 'technicaldataset' ? array_map(fn($s) => $s->sha256, $this->sourcesOf($payload->record)) : [];
			if($table === 'itemtechnicalprofile') {
				if($evidence->scope_key !== $payload->record->binding->scope_key) throw new InvalidArgumentException('Approval scope differs from the exact profile binding');
				$dataset = $this->db->fetchRow('SELECT * FROM technicaldataset WHERE id = ? AND clientid = ?', [(int)$row['datasetid'], $this->owner]);
				$sources = array_map(fn($s) => $s->sha256, $this->sourcesOf($this->verifyRow('technicaldataset', $dataset)->record));
				if(isset($payload->record->binding->source)) $sources[] = $payload->record->binding->source->sha256;
				$peers = $this->db->fetchAll("SELECT * FROM itemtechnicalprofile WHERE clientid = ? AND itemid = ? AND role = ? AND status = 'approved'", [$this->owner, (int)$row['itemid'], $row['role']]);
				foreach($peers as $peer) {
					$other = $this->db->fetchRow('SELECT * FROM technicaldataset WHERE id = ? AND clientid = ?', [(int)$peer['datasetid'], $this->owner]);
					$a = $this->verifyRow('technicaldataset', $dataset)->record;
					$b = $this->verifyRow('technicaldataset', $other)->record;
					if($a->dataset_type !== 'compressor_polynomial' || $b->dataset_type !== 'compressor_polynomial' || Items_Service_TechnicalJson::encode($a->data->conditions) === Items_Service_TechnicalJson::encode($b->data->conditions)) throw new RuntimeException('Overlapping technical profile approval');
				}
			}
			$sources = array_values(array_unique($sources));
			$expectedSources = $evidence->source_sha256s;
			sort($sources); sort($expectedSources);
			if($sources !== $expectedSources) throw new InvalidArgumentException('Approval source hashes do not match');
			$released = [];
			foreach($payload->references as $ref) {
				$dependency = $this->db->fetchRow('SELECT status, approved, approvedby, approval_json FROM '.$this->safeTable($ref->table).' WHERE clientid = ? AND id = ?', [$this->owner, $ref->id]);
				$released[] = (object)['revision' => $ref, 'status' => $dependency['status'], 'approved' => $dependency['approved'], 'approvedby' => (int)$dependency['approvedby'], 'approval_sha256' => hash('sha256', $dependency['approval_json'])];
			}
			$proof = (object)['evidence' => $evidence, 'revision_sha256' => $expectedHash, 'references' => $released];
			$this->transition($table, $row, ['status' => 'approved', 'approved' => gmdate('Y-m-d H:i:s'), 'approvedby' => $this->actor, 'approval_json' => Items_Service_TechnicalJson::encode($proof)]);
		});
	}

	public function withdraw(string $table, int $id, string $expectedHash, string $reason): void
	{
		$this->safeTable($table);
		$this->context('withdraw');
		$this->schema->fields((object)['reason' => $reason], ['reason' => 'text']);
		$this->transaction(function() use ($table, $id, $expectedHash, $reason) {
			$row = $this->locked($table, $id, 'approved', $expectedHash);
			$this->transition($table, $row, ['status' => 'withdrawn', 'withdrawn' => gmdate('Y-m-d H:i:s'), 'withdrawnby' => $this->actor, 'withdrawal_reason' => $reason]);
		});
	}

	private function locked(string $table, int $id, string $status, string $expectedHash): array
	{
		$row = $this->db->fetchRow('SELECT * FROM '.$table.' WHERE id = ? AND clientid = ? FOR UPDATE', [$id, $this->owner]);
		$hash = $table === 'technicaldataset' ? 'payload_sha256' : 'binding_sha256';
		if(!$row || $row['status'] !== $status || !hash_equals($row[$hash], $expectedHash)) throw new RuntimeException('Technical revision state or hash conflict');
		$this->verifyRow($table, $row);
		return $row;
	}

	private function transition(string $table, array $row, array $values): void
	{
		$where = ['id = ?' => (int)$row['id'], 'clientid = ?' => $this->owner, 'status = ?' => $row['status']];
		if($this->db->update($table, $values, $where) !== 1) throw new RuntimeException('Concurrent technical state change');
	}
}
