<?php

class Contacts_Service_InquiryTechnical
{
	private Items_Service_TechnicalDataset $technical;
	private int $owner;

	public function __construct()
	{
		$this->technical = new Items_Service_TechnicalDataset();
	}

	private function context(): void
	{
		$this->owner = $this->technical->ownerForRead();
		$user = (array)Zend_Registry::get('User');
		if(!(new DEEC_Permission($user))->hasPermission('contacts', 'contact', 'view')) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		$client = (array)Zend_Registry::get('Client');
		$selected = (int)$client['id'];
		$shopOwner = !empty($client['parentid']) ? (int)($client['modules']['shops'] ?? 0) : $selected;
		$db = Zend_Db_Table::getDefaultAdapter();
		$parent = (int)$db->fetchOne('SELECT parentid FROM client WHERE id = ? AND deleted = 0', [$selected]);
		$allowed = (new Application_Model_DbTable_Client())->getSwitchableClients();
		if($shopOwner <= 0 || !isset($allowed[$shopOwner]) || !in_array($shopOwner, [$selected, $parent], true)) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
	}

	public function open(int $inquiryId, string $search = '', int $itemId = 0, int $datasetId = 0, int $revision = 0, string $hash = ''): array
	{
		$this->context();
		$row = (new Contacts_Model_DbTable_Inquiry())->getDetail($inquiryId);
		if(!$row) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		$inquiry = (new Contacts_Service_InquiryView())->build($row);
		$view = ['inquiry' => $inquiry, 'requirements' => $this->requirements($inquiry), 'search' => $search, 'matches' => [], 'item' => null,
			'revisions' => [], 'machine' => null, 'choices' => [], 'result' => null, 'request' => null, 'eligible' => false];
		$itemDb = (new Items_Model_DbTable_Item())->setClientId($this->owner);
		if(mb_strlen($search) >= 2 && mb_strlen($search) <= 100) {
			foreach($itemDb->suggestItems($search, $this->owner, 20) as $match) $view['matches'][] = array_intersect_key($match, array_flip(['id', 'sku', 'title', 'manufacturersku']));
		}
		if(!$itemId && !$datasetId) return $view;
		$item = $itemDb->getById($itemId);
		if(!$item) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		$view['item'] = array_intersect_key($item, array_flip(['id', 'parentid', 'sku', 'title', 'manufacturersku']));
		$view['revisions'] = $this->technical->machineRevisions($itemId);
		if(!$datasetId) return $view;
		// The posted revision/hash must still identify the explicitly selected article.
		$matched = array_values(array_filter($view['revisions'], fn($r) => $r['id'] === $datasetId && $r['revision'] === $revision && hash_equals($r['sha256'], $hash)));
		if(count($matched) !== 1) throw new RuntimeException('CONTACT_TECH_REVISION', 409);
		try { $view['machine'] = $this->technical->inspectMachine($itemId, $datasetId, $revision, $hash); }
		catch(RuntimeException $e) { throw new RuntimeException('CONTACT_TECH_REVISION', 409, $e); }
		$view['machine']['articles'] = [];
		foreach($view['machine']['items'] as $ref => $binding) {
			$article = $itemDb->getById($binding->id);
			if(!$article) throw new RuntimeException('CONTACT_TECH_REVISION', 409);
			$view['machine']['articles'][$ref] = array_intersect_key($article, array_flip(['id', 'sku', 'title', 'manufacturersku']));
		}
		$view['choices'] = $this->choices($view['machine']);
		return $view;
	}

	private function requirements(array $inquiry): array
	{
		$known = array_column($inquiry['fields'], null, 'name');
		try { $steps = Shops_Model_FormConfig::normalize(Shops_Model_FormConfig::decode($inquiry['form_fields'])); }
		catch(InvalidArgumentException $e) { return array_values($known); }
		$out = []; $data = $inquiry['data'];
		foreach($steps as $fields) {
			$definitions = array_column($fields, null, 'name');
			foreach($fields as $field) {
				if(in_array($field['type'], ['hidden', 'submit', 'button', 'html'], true)) continue;
				$dependency = $field; $active = true;
				while(isset($dependency['depends_on'])) {
					$parent = $dependency['depends_on'];
					if(!isset($data[$parent]) || !is_scalar($data[$parent]) || !in_array((string)$data[$parent], array_map('strval', (array)$dependency['depends_value']), true)) { $active = false; break; }
					$dependency = $definitions[$parent];
				}
				if(!$active) continue;
				$out[] = ($known[$field['name']] ?? ['name' => $field['name'], 'label' => $field['label'] ?? $field['name'], 'value' => '', 'translate_value' => false]) + ['unit' => $field['unit'] ?? ''];
			}
		}
		return $out;
	}

	private function choices(array $snapshot): array
	{
		$d = $snapshot['record']->data; $choices = [];
		if(($d->configuration_version ?? 1) === 2) {
			foreach($d->mode_topologies as $entry) {
				if($entry->release_status === 'unsupported') continue;
				$ref = $entry->operating_model_ref;
				$model = $snapshot['records']['technicaldataset:'.$ref->dataset_key.'@'.$ref->revision]->data;
				foreach($model->stage_keys as $stage) {
					$coupled = in_array($model->contract_version, [2, 3], true);
					$choices['mode:'.$entry->mode.':'.$stage] = ['method' => $coupled ? 'evaluateThermohydraulic' : 'evaluateOperatingMode', 'label' => $coupled ? 'CONTACT_TECH_COUPLED' : 'CONTACT_TECH_OPERATING',
						'mode' => $entry->mode, 'stage' => $stage, 'contract' => $model->contract_version, 'valves' => $model->valve_states, 'input' => $coupled ? 'inlets' : 'point'];
				}
			}
		} else {
			$choices['stage'] = ['method' => 'evaluateMachineStage', 'label' => 'CONTACT_TECH_STAGE', 'input' => 'stage'];
			if(($d->thermal_model_ref ?? null) !== null) {
				$single = $d->circuit_count === 1;
				$choices[$single ? 'thermal_circuit' : 'thermal_machine'] = ['method' => $single ? 'evaluateThermalCircuit' : 'evaluateThermalMachine', 'label' => 'CONTACT_TECH_THERMAL', 'input' => 'json'];
			}
		}
		foreach($snapshot['records'] as $key => $record) {
			if(!str_starts_with($key, 'technicaldataset:')) continue;
			if($record->dataset_type === 'compressor_polynomial') {
				$identity = $snapshot['dependencies'][$key];
				$choices['compressor:'.$identity['id']] = ['method' => 'evaluateCompressor', 'label' => 'CONTACT_TECH_COMPRESSOR', 'input' => 'compressor', 'target' => $identity];
			}
			if($record->dataset_type === 'hydraulic_path') {
				$distribution = $record->data->contract_version === 2;
				$choices[$distribution ? 'distribution' : 'hydraulics'] = ['method' => $distribution ? 'evaluateHydraulicDistribution' : 'evaluateHydraulics', 'label' => $distribution ? 'CONTACT_TECH_DISTRIBUTION' : 'CONTACT_TECH_HYDRAULICS', 'input' => 'json'];
			}
		}
		return $choices;
	}

	public function calculate(array $view, array $post): array
	{
		$this->context();
		// Re-open the entire inquiry/article/graph even when called outside the controller.
		$root = $view['machine']['identity'] ?? throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		$view = $this->open((int)$view['inquiry']['id'], '', (int)$view['item']['id'], $root['id'], $root['revision'], $root['sha256']);
		$key = $post['calculation'] ?? '';
		if(isset($post['reference']) && (!is_string($post['reference']) || !in_array($post['reference'], ['', '1'], true))) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		if(!is_string($key) || !isset($view['choices'][$key])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		$choice = $view['choices'][$key]; $snapshot = $view['machine']; $d = $snapshot['record']->data;
		$reference = ($post['reference'] ?? '') === '1';
		$synthetic = true;
		foreach($snapshot['records'] as $record) if(isset($record->dataset_type) && ($record->import_context->production_import_allowed ?? null) !== false) $synthetic = false;
		if($reference && !$synthetic) throw new InvalidArgumentException('CONTACT_TECH_SYNTHETIC_REQUIRED');
		if($snapshot['blocked']) throw new RuntimeException('CONTACT_TECH_REVISION', 409);
		if($choice['input'] === 'json') {
			$json = $post['request_json'] ?? '';
			if(!is_string($json) || strlen($json) > 32768) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			try { $request = json_decode($json, false, 32, JSON_THROW_ON_ERROR); }
			catch(JsonException $e) { throw new InvalidArgumentException('CONTACT_TECH_INPUT'); }
			if(!$request instanceof stdClass) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		} elseif($choice['input'] === 'inlets') {
			$request = (object)['operating_mode' => $choice['mode'], 'stage_key' => $choice['stage'], 'valve_states' => $choice['valves'], 'secondary_boundaries' => (object)[
				'evaporator' => (object)['inlet_degC' => $this->number($post['cold_inlet'] ?? null)], 'condenser' => (object)['inlet_degC' => $this->number($post['hot_inlet'] ?? null)]]];
		} elseif($choice['input'] === 'stage') {
			$stage = $post['stage'] ?? '';
			if(!is_string($stage) || !in_array($stage, array_column($d->stages, 'stage_key'), true)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$points = [];
			if(!is_array($post['circuits'] ?? null)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			foreach($d->circuits as $circuit) $points[] = (object)['circuit_key' => $circuit->circuit_key, 'point' => $this->point($post['circuits'][$circuit->circuit_key] ?? [])];
			$request = (object)['operating_mode' => 'cooling', 'stage_key' => $stage, 'circuit_points' => $points];
		} else {
			$request = (object)['point' => $this->point($post)];
			if($choice['input'] === 'point') {
				$request->operating_mode = $choice['mode']; $request->stage_key = $choice['stage']; $request->valve_states = $choice['valves'];
			}
		}
		$target = $choice['target'] ?? $root;
		$mode = $reference ? (in_array($choice['method'], ['evaluateCompressor', 'evaluateMachineStage'], true) ? 'mathematical_reference' : 'synthetic_reference') : 'technical';
		$view['result'] = $this->technical->{$choice['method']}($target['id'], $target['revision'], $target['sha256'], $request, $mode);
		$view['request'] = $request;
		$view['calculation'] = $key;
		$view['reference'] = $reference;
		// A component point, including a released D3 point, never releases this machine.
		$view['eligible'] = false;
		return $view;
	}

	private function number($value): string
	{
		if(!is_string($value) || !preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value) || !is_finite((float)$value)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		return $value;
	}

	private function point($post): stdClass
	{
		if(!is_array($post)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		return (object)['Te_degC' => $this->number($post['Te_degC'] ?? null), 'Tc_degC' => $this->number($post['Tc_degC'] ?? null), 'temperature_unit' => 'degC'];
	}
}
