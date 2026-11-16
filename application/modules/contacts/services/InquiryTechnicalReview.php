<?php

class Contacts_Service_InquiryTechnicalReview
{
	private const MAX_BYTES = 131072;
	private const STATUSES = ['review_required', 'technical_incomplete', 'unsuitable'];
	private const MATH_STATUSES = ['calculated', 'failed', 'not_calculated'];
	private const METHODS = ['evaluateCompressor', 'evaluateMachineStage', 'evaluateThermalCircuit', 'evaluateThermalMachine', 'evaluateHydraulics', 'evaluateHydraulicDistribution', 'evaluateOperatingMode', 'evaluateThermohydraulic', 'inspection'];
	private Zend_Db_Adapter_Abstract $db;
	private Contacts_Model_DbTable_Inquirytechnicalreview $records;
	private Contacts_Service_InquiryTechnical $technical;
	private Items_Service_TechnicalDataset $datasets;

	public function __construct()
	{
		$this->db = Zend_Db_Table::getDefaultAdapter();
		$this->records = new Contacts_Model_DbTable_Inquirytechnicalreview();
		$this->technical = new Contacts_Service_InquiryTechnical();
		$this->datasets = new Items_Service_TechnicalDataset();
	}

	public function internalEnabled(): bool
	{
		// An explicit server-side pilot allowlist is not a SaaS entitlement.
		$list = getenv('DEWAWI_SELECTION_INTERNAL_CLIENTS');
		if(!is_string($list) || !preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $list) || !Zend_Registry::isRegistered('Client') || !Zend_Auth::getInstance()->hasIdentity()) return false;
		return in_array((string)$this->selectedClient(), explode(',', $list), true);
	}

	private function selectedClient(): int
	{
		$identity = Zend_Auth::getInstance()->getIdentity();
		return (int)($identity->clientid ?? Zend_Registry::get('Client')['id'] ?? 0);
	}

	private function context(int $inquiryId, bool $write = false): array
	{
		$view = $this->technical->open($inquiryId);
		if(!$this->internalEnabled()) throw new RuntimeException('CONTACT_TECH_REVIEW_ROLLOUT', 403);
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if($write && (!$permission->hasPermission('contacts', 'contact', 'edit') || !$permission->hasPermission('items', 'technicaldataset', 'review'))) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		return $view;
	}

	public function canSave(): bool
	{
		if(!$this->internalEnabled() || !Zend_Registry::isRegistered('User')) return false;
		$p = new DEEC_Permission((array)Zend_Registry::get('User'));
		return $p->hasPermission('contacts', 'contact', 'edit') && $p->hasPermission('items', 'technicaldataset', 'review');
	}

	public function calculate(array $view, array $post, int $previousId = 0): array
	{
		$this->context((int)$view['inquiry']['id'], true);
		$view = $this->technical->calculate($view, $post);
		$choice = $view['choices'][$view['calculation']];
		if($choice['input'] === 'json') {
			try { $this->validateBaseRequest($view['machine'], $choice['method'], $view['request']); }
			catch(InvalidArgumentException $e) {
				if(($view['result']['solution'] ?? null) === null) throw $e;
				$view['review_unavailable'] = true;
				return $view;
			}
		}
		return $this->stage($view, $previousId);
	}

	public function inspect(array $view, int $previousId = 0, ?string $choiceKey = null): array
	{
		$this->context((int)$view['inquiry']['id'], true);
		$r = $view['machine']['identity'] ?? throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		$view = $this->technical->open((int)$view['inquiry']['id'], '', (int)$view['item']['id'], $r['id'], $r['revision'], $r['sha256']);
		if($choiceKey !== null && $choiceKey !== '') {
			if(!isset($view['choices'][$choiceKey])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$view['calculation'] = $choiceKey;
		}
		return $this->stage($view, $previousId);
	}

	private function stage(array $view, int $previousId): array
	{
		if($previousId) $this->stored((int)$view['inquiry']['id'], $previousId);
		$proof = $this->proof($view);
		$session = new Zend_Session_Namespace('InquiryTechnicalReview');
		$pending = $session->pending ?? [];
		foreach($pending as $key => $entry) if($entry['expires'] < time()) unset($pending[$key]);
		while(count($pending) >= 8) array_shift($pending);
		$token = bin2hex(random_bytes(32));
		$pending[$token] = ['expires' => time() + 3600, 'actor' => (int)Zend_Registry::get('User')['id'], 'previousid' => $previousId, 'proof' => $proof];
		$session->pending = $pending;
		$view['review_token'] = $token;
		return $view;
	}

	private function encode($value): string
	{
		// Normalize PHP maps to JSON objects before using the existing canonical codec.
		return Items_Service_TechnicalJson::encode(json_decode(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR));
	}

	private function digest($value): string
	{
		return hash('sha256', $this->encode($value));
	}

	private function customerHash($data): string
	{
		return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
	}

	private function evidence(array $snapshot): array
	{
		$out = [];
		foreach($snapshot['dependencies'] as $key => $node) $out[$key] = array_intersect_key($node, array_flip(['table', 'id', 'key', 'revision', 'sha256', 'status', 'approved', 'approvedby', 'approval_sha256', 'withdrawn'])) + ['source_sha256' => $this->digest($node['sources'])];
		ksort($out);
		return $out;
	}

	private function variant(array $snapshot): array
	{
		$d = $snapshot['record']->data;
		return ['items' => $snapshot['items'], 'signature' => $d->variant_signature, 'equipment' => $d->selected_equipment, 'options' => $snapshot['options']];
	}

	private function metrics(array $result): array
	{
		$metrics = [];
		$add = static function(string $path, $value, string $unit) use(&$metrics): void {
			if($value === null) return;
			if(!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$metrics[] = ['name' => $path, 'value' => is_float($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string)$value, 'unit' => $unit];
		};
		$solution = (array)($result['solution'] ?? []);
		foreach(['evaporator_heat_kW' => 'kW', 'condenser_heat_kW' => 'kW', 'total_hot_side_heat_kW' => 'kW', 'compressor_input_kW' => 'kW', 'useful_heat_kW' => 'kW', 'pump_heat_removed_to_external_thermostats_W' => 'W', 'Te_degC' => '°C', 'Tc_degC' => '°C'] as $key => $unit) $add($key, $solution[$key] ?? null, $unit);
		foreach(['Te_degC', 'Tc_degC'] as $key) $add($key, $solution['point']->$key ?? null, '°C');
		$groups = ['compressor' => $result['calculation']['quantities'] ?? [], 'stage' => $result['stage_compressor_sums'] ?? []];
		foreach($groups as $prefix => $quantities) foreach(Items_Service_TechnicalSchema::QUANTITIES as $key => $units) if(isset($quantities[$key]['unit'])) $add($prefix.'.'.$key, $quantities[$key]['value'] ?? null, $quantities[$key]['unit']);
		foreach($solution['secondary_sides'] ?? [] as $key => $side) foreach(['outlet_degC' => '°C', 'mass_flow_kg_s' => 'kg/s', 'heat_flow_kW' => 'kW', 'pressure_residual_Pa' => 'Pa'] as $name => $unit) $add($key.'.'.$name, $side[$name] ?? null, $unit);
		foreach($solution['loops'] ?? $solution['hydraulics']['loops'] ?? [] as $key => $loop) {
			foreach(['volume_flow_m3_h' => 'm³/h', 'mass_flow_kg_s' => 'kg/s'] as $name => $unit) $add($key.'.'.$name, $loop['flow'][$name] ?? null, $unit);
			$add($key.'.required_pressure_Pa', $loop['required_pressure_Pa'] ?? null, 'Pa');
			$add($key.'.pump.reserve_Pa', $loop['pump']['reserve_Pa'] ?? null, 'Pa');
		}
		foreach(['total_volume_flow_m3_h' => 'm³/h', 'total_mass_flow_kg_s' => 'kg/s', 'required_pressure_Pa' => 'Pa', 'parallel_pressure_Pa' => 'Pa', 'pump_residual_Pa' => 'Pa'] as $name => $unit) $add($name, $solution[$name] ?? null, $unit);
		$balances = ['energy_balance' => $solution['energy_balance'] ?? [], 'thermal_totals' => $solution['thermal_totals'] ?? []];
		foreach($solution['circuits'] ?? [] as $key => $circuit) {
			$balances[$key.'.energy_balance'] = $circuit['energy_balance'] ?? [];
			foreach(['Te_degC', 'Tc_degC'] as $axis) $add($key.'.'.$axis, $circuit['point']->$axis ?? null, '°C');
		}
		foreach($balances as $prefix => $balance) foreach(['compressor_cooling_kW', 'compressor_power_kW', 'required_condenser_heat_kW', 'evaporator_heat_kW', 'condenser_heat_kW', 'energy_residual_kW'] as $name) $add($prefix.'.'.$name, $balance[$name] ?? null, 'kW');
		return $metrics;
	}

	private function proof(array $view): array
	{
		$m = $view['machine']; $d = $m['record']->data; $r = $view['result'];
		foreach($r['dependencies'] ?? [] as $key => $node) if(isset($m['dependencies'][$key])) $m['dependencies'][$key] = $node;
		$choice = $view['choices'][$view['calculation'] ?? ''] ?? null;
		$hasResults = ($r['solution'] ?? $r['calculation'] ?? null) !== null || ($r['stage_compressor_sums']['cooling_capacity']['value'] ?? null) !== null;
		$math = $r === null ? 'not_calculated' : ($hasResults && ($r['calculation']['mathematical_status'] ?? null) !== 'invalid_result' && ($r['mathematical_converged'] ?? true) ? 'calculated' : 'failed');
		$status = ($r['status'] ?? '') === 'unsuitable' || ($r['operating_region']['status'] ?? '') === 'outside' ? 'unsuitable' : ($math === 'calculated' ? 'review_required' : 'technical_incomplete');
		$components = [];
		foreach($d->components as $c) $components[] = ['instance_key' => $c->instance_key, 'role' => $c->role, 'itemid' => $m['items']->{$c->component_key}->id, 'profile_key' => $c->profile_key, 'profile_revision' => $c->profile_revision];
		$missing = [];
		foreach($m['records'] as $record) foreach($record->data->missing_evidence ?? [] as $reason) $missing[] = $record->dataset_key.':'.$reason;
		$p = ['format_version' => 1, 'context' => ['clientid' => $this->selectedClient(), 'actorid' => (int)Zend_Registry::get('User')['id']], 'inquiry' => ['id' => (int)$view['inquiry']['id'], 'clientid' => (int)$view['inquiry']['clientid'], 'data_sha256' => $this->customerHash($view['inquiry']['data'])],
			'article' => ['id' => (int)$view['item']['id'], 'clientid' => $this->datasets->ownerForRead(), 'parentid' => (int)($view['item']['parentid'] ?? 0)], 'machine' => $m['identity'],
			'variant' => $this->variant($m), 'variant_sha256' => $this->digest($this->variant($m)), 'components' => $components, 'dependencies' => $this->evidence($m), 'evaluated_at' => date('Y-m-d H:i:s'),
			'calculation' => ['key' => $view['calculation'] ?? null, 'method' => $r === null ? 'inspection' : $choice['method'], 'version' => $r['calculation_version'] ?? $r['calculation']['calculation_version'] ?? null,
				'mode' => $view['request']->operating_mode ?? $choice['mode'] ?? null, 'stage' => $view['request']->stage_key ?? $choice['stage'] ?? null, 'evaluation_mode' => $r['evaluation_mode'] ?? 'not_calculated', 'request' => $view['request']],
			'result' => ['status' => $status, 'mathematical_status' => $math, 'metrics' => $r === null ? [] : $this->metrics($r), 'scope' => $r['scope'] ?? $r['calculation']['calculation_version'] ?? 'inspection_only',
				'fully_coupled' => (bool)($r['fully_coupled'] ?? false), 'reasons' => array_values(array_unique(array_merge($m['reasons'], $missing, $r['reasons'] ?? [], $r['dependency_reasons'] ?? [], $r['violations'] ?? [], ['machine_release_missing', 'saas_entitlement_unavailable']))),
				'eligible' => false, 'commercial_status' => 'rollout_blocked', 'saas_entitlement_status' => 'unavailable']];
		$this->validateProof($p);
		return $p;
	}

	private function validateBaseRequest(array $machine, string $method, stdClass $request): void
	{
		// Some APIs stop at the release gate before validating nested runtime inputs.
		// Archive only the closed base contracts; advanced read-only evaluations remain available.
		$s = new Items_Service_TechnicalSchema();
		$d = $machine['record']->data;
		$composition = static function(stdClass $value) use($s): void {
			$c = $s->fields($value, ['basis' => ['mass_fraction', 'volume_fraction'], 'fraction' => 'nonnegative', 'volume_reference_degC' => '?number']);
			if((float)$c->fraction > 1) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		};
		$boundary = static function(stdClass $value) use($s): void {
			$b = $s->fields($value, ['medium_key' => 'key', 'inlet_degC' => 'number', 'volume_flow_m3_h' => '?positive', 'fluid_state' => '?text'], ['fan' => 'object']);
			if(isset($b->fan)) $s->fields($b->fan, ['execution_code' => '?text', 'speed_rpm' => '?positive']);
		};
		if(in_array($method, ['evaluateThermalCircuit', 'evaluateThermalMachine'], true)) {
			$base = ['stage_key' => array_column($d->stages, 'stage_key'), 'operating_mode' => ['cooling']];
			if($method === 'evaluateThermalCircuit') {
				$q = $s->fields($request, $base + ['evaporator' => 'object', 'condenser' => 'object']);
				$boundary($q->evaporator); $boundary($q->condenser);
			} else {
				$q = $s->fields($request, $base + ['secondary_boundaries' => 'list:object']);
				$ref = $d->thermal_model_ref;
				$groups = $machine['records']['technicaldataset:'.$ref->dataset_key.'@'.$ref->revision]->data->secondary_groups;
				$seen = [];
				foreach($q->secondary_boundaries as $entry) {
					$entry = $s->fields($entry, ['group_key' => array_column($groups, 'group_key'), 'boundary' => 'object']);
					if(isset($seen[$entry->group_key])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
					$seen[$entry->group_key] = true; $boundary($entry->boundary);
				}
				if(count($seen) !== count($groups)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			}
			return;
		}
		$loopKeys = array_column($d->hydraulic_loops, 'loop_key');
		if($method === 'evaluateHydraulicDistribution') {
			$q = $s->fields($request, ['loop_key' => $loopKeys, 'composition' => 'object', 'temperature_degC' => 'number', 'speed_rpm' => 'positive', 'frequency_Hz' => 'positive', 'operating_mode' => 'key']);
			$composition($q->composition);
			return;
		}
		$q = $s->fields($request, ['loops' => 'list:object']); $seen = [];
		foreach($q->loops as $loop) {
			$loop = $s->fields($loop, ['loop_key' => $loopKeys, 'composition' => 'object', 'flow' => 'object', 'component_states' => 'list:object', 'pipe_states' => 'list:object', 'external_state' => 'object', 'pump_condition' => '?object']);
			if(isset($seen[$loop->loop_key])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$seen[$loop->loop_key] = true; $composition($loop->composition);
			$s->fields($loop->flow, ['value' => 'positive', 'unit' => ['m3/h', 'kg/s', 'kg/h'], 'at_degC' => 'number']);
			foreach($loop->component_states as $state) {
				$state = $s->fields($state, ['instance_key' => array_column($d->components, 'instance_key'), 'sections' => 'list:object']);
				foreach($state->sections as $section) $s->fields($section, ['section_key' => 'key', 'temperature_degC' => 'number', 'volume_flow_m3_h' => 'positive']);
			}
			foreach($loop->pipe_states as $pipe) $s->fields($pipe, ['segment_key' => 'key', 'temperature_degC' => 'number', 'volume_flow_m3_h' => 'positive']);
			$s->fields($loop->external_state, ['temperature_degC' => 'number', 'volume_flow_m3_h' => 'positive']);
			if($loop->pump_condition !== null) $s->fields($loop->pump_condition, ['temperature_degC' => 'number', 'speed_rpm' => 'positive', 'frequency_Hz' => 'positive', 'operating_mode' => 'key']);
		}
		if(count($seen) !== count($loopKeys)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
	}

	private function validateProof(array $proof): void
	{
		if(strlen($this->encode($proof)) > self::MAX_BYTES || ($proof['format_version'] ?? null) !== 1 || ($proof['result']['eligible'] ?? null) !== false
			|| ($proof['result']['commercial_status'] ?? '') !== 'rollout_blocked' || ($proof['result']['saas_entitlement_status'] ?? '') !== 'unavailable'
			|| !in_array($proof['result']['status'] ?? '', self::STATUSES, true) || !in_array($proof['result']['mathematical_status'] ?? '', self::MATH_STATUSES, true)
			|| !in_array($proof['calculation']['method'] ?? '', self::METHODS, true) || count($proof['dependencies'] ?? []) > 500 || count($proof['result']['metrics'] ?? []) > 500) throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409);
		try {
			$s = new Items_Service_TechnicalSchema();
			$p = $s->fields(json_decode($this->encode($proof)), ['format_version' => 'int', 'context' => 'object', 'inquiry' => 'object', 'article' => 'object', 'machine' => 'object', 'variant' => 'object', 'variant_sha256' => 'hash', 'components' => 'list:object', 'dependencies' => 'object', 'evaluated_at' => 'text', 'calculation' => 'object', 'result' => 'object']);
			$s->fields($p->context, ['clientid' => 'int', 'actorid' => 'int']);
			$s->fields($p->inquiry, ['id' => 'int', 'clientid' => 'int', 'data_sha256' => 'hash']);
			$s->fields($p->article, ['id' => 'int', 'clientid' => 'int', 'parentid' => 'uint']);
			$identity = ['table' => ['technicaldataset', 'itemtechnicalprofile'], 'id' => 'int', 'key' => 'key', 'revision' => 'int', 'sha256' => 'hash'];
			$s->fields($p->machine, $identity);
			$s->fields($p->variant, ['items' => 'object', 'signature' => 'object', 'equipment' => 'list:object', 'options' => $p->variant->options instanceof stdClass ? 'object' : 'list:object']);
			foreach($p->components as $component) $s->fields($component, ['instance_key' => 'key', 'role' => 'key', 'itemid' => 'int', 'profile_key' => 'key', 'profile_revision' => 'int']);
			foreach($p->dependencies as $node) $s->fields($node, $identity + ['status' => ['draft', 'approved', 'withdrawn'], 'approved' => '?text', 'approvedby' => '?int', 'approval_sha256' => '?hash', 'withdrawn' => '?text', 'source_sha256' => 'hash']);
			$s->fields($p->calculation, ['key' => '?text', 'method' => self::METHODS, 'version' => '?text', 'mode' => '?key', 'stage' => '?key', 'evaluation_mode' => ['technical', 'mathematical_reference', 'synthetic_reference', 'not_calculated'], 'request' => '?object']);
			$r = $s->fields($p->result, ['status' => self::STATUSES, 'mathematical_status' => self::MATH_STATUSES, 'metrics' => 'list:object', 'scope' => 'text', 'fully_coupled' => 'bool', 'reasons' => 'list:longtext', 'eligible' => [false], 'commercial_status' => ['rollout_blocked'], 'saas_entitlement_status' => ['unavailable']]);
			foreach($r->metrics as $metric) {
				if(!is_numeric($metric->value) || !is_finite((float)$metric->value)) throw new InvalidArgumentException('Invalid review metric');
				$check = clone $metric; $check->value = (string)$metric->value;
				$s->fields($check, ['name' => 'text', 'value' => 'number', 'unit' => 'text']);
			}
		} catch(InvalidArgumentException $e) { throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409, $e); }
	}

	private function headerHash(array $row): string
	{
		$header = [];
		foreach(['clientid', 'inquiryid', 'itemsclientid', 'itemid', 'datasetid', 'dataset_revision', 'version', 'createdby'] as $field) $header[$field] = (int)$row[$field];
		$header['previousid'] = $row['previousid'] === null ? null : (int)$row['previousid'];
		foreach(['dataset_sha256', 'request_key', 'status', 'mathematical_status', 'payload_sha256', 'created'] as $field) $header[$field] = $row[$field];
		return $this->digest($header);
	}

	private function verify(array $row): array
	{
		if(strlen($row['payload_json']) > self::MAX_BYTES || !hash_equals($row['payload_sha256'], hash('sha256', $row['payload_json'])) || !hash_equals($row['record_sha256'], $this->headerHash($row))) throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409);
		try { $p = json_decode($row['payload_json'], true, 32, JSON_THROW_ON_ERROR); }
		catch(JsonException $e) { throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409); }
		$this->validateProof($p);
		$object = json_decode($row['payload_json']);
		if(!hash_equals($p['variant_sha256'], $this->digest($object->variant))) throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409);
		if((int)$row['createdby'] !== $p['context']['actorid'] || (int)$row['inquiryid'] !== $p['inquiry']['id'] || (int)$row['clientid'] !== $p['inquiry']['clientid'] || (int)$row['itemsclientid'] !== $p['article']['clientid'] || (int)$row['itemid'] !== $p['article']['id'] || (int)$row['datasetid'] !== $p['machine']['id']
			|| (int)$row['dataset_revision'] !== $p['machine']['revision'] || !hash_equals($row['dataset_sha256'], $p['machine']['sha256']) || $row['status'] !== $p['result']['status'] || $row['mathematical_status'] !== $p['result']['mathematical_status']) throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409);
		return $p;
	}

	private function stored(int $inquiryId, int $id): array
	{
		$v = $this->context($inquiryId);
		$row = $this->records->findReview((int)$v['inquiry']['clientid'], $inquiryId, $this->datasets->ownerForRead(), $id);
		if(!$row) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		return ['row' => $row, 'proof' => $this->verify($row)];
	}

	public function history(int $inquiryId, int $before = 0): array
	{
		$v = $this->context($inquiryId);
		$rows = $this->records->history((int)$v['inquiry']['clientid'], $inquiryId, $this->datasets->ownerForRead(), $before);
		foreach($rows as &$row) { $this->verify($row); unset($row['payload_json'], $row['request_key']); } unset($row);
		return $rows;
	}

	public function open(int $inquiryId, int $id): array
	{
		$saved = $this->stored($inquiryId, $id); $p = $saved['proof'];
		$current = ['status' => 'review_required', 'reasons' => [], 'dependencies' => [], 'eligible' => false];
		try {
			$m = $this->datasets->inspectMachine($p['article']['id'], $p['machine']['id'], $p['machine']['revision'], $p['machine']['sha256']);
			$current['dependencies'] = $this->evidence($m);
			if($m['blocked'] || $this->digest($this->variant($m)) !== $p['variant_sha256']) { $current['status'] = 'historical_invalid'; $current['reasons'][] = 'technical_binding_or_dependency_unavailable'; }
			elseif($this->digest($current['dependencies']) !== $this->digest($p['dependencies'])) { $current['status'] = 'historical_changed'; $current['reasons'][] = 'technical_approval_changed'; }
			foreach($current['dependencies'] as $node) {
				$table = $node['table']; $column = $table === 'technicaldataset' ? 'dataset_key' : 'profile_key';
				if($this->db->fetchOne('SELECT id FROM '.$table.' WHERE clientid = ? AND '.$column.' = ? AND revision > ? LIMIT 1', [$p['article']['clientid'], $node['key'], $node['revision']])) {
					if($current['status'] !== 'historical_invalid') $current['status'] = 'historical_changed';
					$current['reasons'][] = 'newer_technical_revision_available';
				}
			}
			$current['reasons'] = array_values(array_unique(array_merge($current['reasons'], $m['reasons'])));
		} catch(RuntimeException|InvalidArgumentException $e) { $current['status'] = 'historical_invalid'; $current['reasons'][] = 'technical_revision_integrity_or_access_changed'; }
		$v = $this->technical->open($inquiryId);
		if($p['inquiry']['data_sha256'] !== $this->customerHash($v['inquiry']['data'])) { if($current['status'] !== 'historical_invalid') $current['status'] = 'historical_changed'; $current['reasons'][] = 'inquiry_values_changed'; }
		return $saved + ['current' => $current];
	}

	public function startingInput(int $inquiryId, int $id): array
	{
		$p = $this->stored($inquiryId, $id)['proof']; $q = $p['calculation']['request'];
		$out = ['itemid' => $p['article']['id'], 'datasetid' => $p['machine']['id'], 'revision' => $p['machine']['revision'], 'hash' => $p['machine']['sha256'], 'calculation' => $p['calculation']['key'] ?? '', 'fromreview' => $id];
		if(in_array($p['calculation']['evaluation_mode'], ['synthetic_reference', 'mathematical_reference'], true)) $out['reference'] = '1';
		if($q === null) return $out;
		$method = $p['calculation']['method'];
		if($method === 'evaluateThermohydraulic') { $out['cold_inlet'] = $q['secondary_boundaries']['evaporator']['inlet_degC']; $out['hot_inlet'] = $q['secondary_boundaries']['condenser']['inlet_degC']; }
		elseif(in_array($method, ['evaluateCompressor', 'evaluateOperatingMode'], true)) { $out['Te_degC'] = $q['point']['Te_degC']; $out['Tc_degC'] = $q['point']['Tc_degC']; }
		elseif($method === 'evaluateMachineStage') { $out['stage'] = $q['stage_key']; foreach($q['circuit_points'] as $point) $out['circuits'][$point['circuit_key']] = ['Te_degC' => $point['point']['Te_degC'], 'Tc_degC' => $point['point']['Tc_degC']]; }
		else $out['request_json'] = $this->encode($q);
		return $out;
	}

	public function save(int $inquiryId, string $token): int
	{
		$v = $this->context($inquiryId, true);
		if(!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
		$pending = (new Zend_Session_Namespace('InquiryTechnicalReview'))->pending ?? [];
		$receipt = $pending[$token] ?? null;
		$actor = (int)Zend_Registry::get('User')['id'];
		if(!$receipt || $receipt['expires'] < time() || $receipt['actor'] !== $actor || $receipt['proof']['inquiry']['id'] !== $inquiryId) throw new RuntimeException('CONTACT_TECH_REVIEW_EXPIRED', 409);
		$p = $receipt['proof']; $this->validateProof($p);
		$owner = (int)$v['inquiry']['clientid']; $itemsOwner = $this->datasets->ownerForRead();
		if($p['context']['clientid'] !== $this->selectedClient() || $p['context']['actorid'] !== $actor || $p['inquiry']['clientid'] !== $owner || $p['article']['clientid'] !== $itemsOwner) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
		// Inquiry locks serialize versions; the technical owner lock also protects concurrent revocations.
		return $this->datasets->inspectMachine($p['article']['id'], $p['machine']['id'], $p['machine']['revision'], $p['machine']['sha256'], function(array $m) use($p, $receipt, $owner, $itemsOwner, $inquiryId, $actor, $token): array {
			$inquiry = (new Contacts_Model_DbTable_Inquiry())->getByIdForUpdate($inquiryId);
			if(!$inquiry || (int)$inquiry['clientid'] !== $owner) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
			$existing = $this->records->findRequest($owner, $actor, hash('sha256', $token));
			if($existing) {
				$this->verify($existing);
				if((int)$existing['inquiryid'] !== $inquiryId || !hash_equals($existing['payload_sha256'], $this->digest($p))) throw new RuntimeException('CONTACT_TECH_REVIEW_INTEGRITY', 409);
				return ['id' => (int)$existing['id']];
			}
			if($this->digest($this->evidence($m)) !== $this->digest($p['dependencies']) || $this->digest($this->variant($m)) !== $p['variant_sha256'] || $this->customerHash(json_decode($inquiry['data'], true)) !== $p['inquiry']['data_sha256']) throw new RuntimeException('CONTACT_TECH_REVIEW_STALE', 409);
			if($receipt['previousid']) {
				$previous = $this->records->findReview($owner, $inquiryId, $itemsOwner, $receipt['previousid']);
				if(!$previous) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
				$this->verify($previous);
			}
			$json = $this->encode($p);
			$row = ['clientid' => $owner, 'inquiryid' => $inquiryId, 'itemsclientid' => $itemsOwner, 'itemid' => $p['article']['id'], 'datasetid' => $p['machine']['id'], 'dataset_revision' => $p['machine']['revision'], 'dataset_sha256' => $p['machine']['sha256'],
				'previousid' => $receipt['previousid'] ?: null, 'version' => $this->records->nextVersion($owner, $inquiryId), 'request_key' => hash('sha256', $token), 'status' => $p['result']['status'], 'mathematical_status' => $p['result']['mathematical_status'],
				'payload_json' => $json, 'payload_sha256' => hash('sha256', $json), 'created' => date('Y-m-d H:i:s'), 'createdby' => $actor];
			$row['record_sha256'] = $this->headerHash($row);
			return ['id' => $this->records->append($row)];
		})['id'];
	}
}
