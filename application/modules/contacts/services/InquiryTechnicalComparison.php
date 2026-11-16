<?php

class Contacts_Service_InquiryTechnicalComparison
{
	public const VERSION = 1;
	public const MAX_CANDIDATES = 3;
	public const AREAS = ['compressor_electrical', 'refrigerant_curve', 'envelope_conditions', 'evaporator_maps', 'condenser_maps', 'hydraulics_pumps', 'controls_stages', 'parallel_oil', 'thermal_balance', 'operating_release', 'oem_references'];

	public function compare(int $inquiryId, array $requested, string $search = ''): array
	{
		$reviews = new Contacts_Service_InquiryTechnicalReview();
		$base = $reviews->comparisonContext($inquiryId);
		if(!array_is_list($requested) || count($requested) > self::MAX_CANDIDATES || mb_strlen($search) > 100) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
		$technical = new Contacts_Service_InquiryTechnical();
		$datasets = new Items_Service_TechnicalDataset();
		$itemDb = (new Items_Model_DbTable_Item())->setClientId($datasets->ownerForRead());
		$out = ['comparison_version' => self::VERSION, 'inquiry' => $base['inquiry'], 'requirements' => $this->requirements($base), 'candidates' => [], 'search' => $search,
			'matches' => [], 'history_scope' => 'latest_50', 'max_candidates' => self::MAX_CANDIDATES, 'status' => 'review_required', 'eligible' => false, 'commercial_status' => 'rollout_blocked'];
		$history = $reviews->history($inquiryId); $seen = [];
		foreach($requested as $entry) {
			if(!is_array($entry) || array_diff(array_keys($entry), ['itemid', 'datasetid', 'revision', 'hash', 'reviewid'])) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
			$itemId = $this->id($entry['itemid'] ?? null);
			$article = $itemDb->getById($itemId);
			if(!$article) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
			$article = array_intersect_key($article, array_flip(['id', 'sku', 'title', 'parentid']));
			$state = ['itemid' => $itemId]; $saved = null;
			if(isset($entry['reviewid'])) {
				$saved = $reviews->open($inquiryId, $this->id($entry['reviewid']));
				if($saved['proof']['article']['id'] !== $itemId) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
				$r = $saved['proof']['machine'];
				$pinned = ['datasetid' => $r['id'], 'revision' => $r['revision'], 'hash' => $r['sha256']];
				foreach($pinned as $key => $value) if(isset($entry[$key]) && (!is_scalar($entry[$key]) || (string)$entry[$key] !== (string)$value)) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
				$state += $pinned + ['reviewid' => (int)$saved['row']['id']];
			} elseif(isset($entry['datasetid']) || isset($entry['revision']) || isset($entry['hash'])) {
				if(!is_string($entry['hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['hash'])) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
				$state += ['datasetid' => $this->id($entry['datasetid'] ?? null), 'revision' => $this->id($entry['revision'] ?? null), 'hash' => $entry['hash']];
			}
			$key = implode(':', [$itemId, $state['datasetid'] ?? '', $state['reviewid'] ?? '']);
			if(isset($seen[$key])) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
			$seen[$key] = true;
			$view = null;
			try { $view = $technical->open($inquiryId, '', $itemId, $state['datasetid'] ?? 0, $state['revision'] ?? 0, $state['hash'] ?? ''); }
			catch(RuntimeException|InvalidArgumentException $e) {
				if(!$saved || $e->getCode() === 403 || $saved['current']['status'] !== 'historical_invalid') throw $e;
			}
			$m = $view['machine'] ?? null;
			$c = ['selection' => $state, 'article' => $article, 'revisions' => $view['revisions'] ?? [], 'configuration' => null, 'components' => [], 'packages' => [], 'coverage' => [],
				'calculations' => [], 'assessments' => [], 'history' => [], 'historical' => null, 'status' => 'review_required', 'eligible' => false, 'blockers' => $m['reasons'] ?? ['machine_configuration_unavailable']];
			if($m) {
				if(count($m['dependencies']) > 256 || count($m['record']->data->components) > 128) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
				$d = $m['record']->data;
				$c['configuration'] = ['identity' => $m['identity'], 'family' => $d->family_ref, 'cooling_kind' => $d->cooling_kind, 'installation_kind' => $d->installation_kind,
					'refrigerant' => $d->refrigerant, 'circuits' => $d->circuit_count, 'compressors' => count(array_filter($d->components, fn($v) => $v->role === 'compressor')),
					'modes' => $d->mode_topologies, 'variant_signature' => $d->variant_signature, 'equipment' => $d->selected_equipment, 'options' => $m['options']];
				foreach($d->components as $component) {
					$profileKey = 'itemtechnicalprofile:'.$component->profile_key.'@'.$component->profile_revision;
					$profile = $m['records'][$profileKey];
					$targetKey = 'technicaldataset:'.$profile->dataset_ref->dataset_key.'@'.$profile->dataset_ref->revision;
					$record = $m['records'][$targetKey];
					$c['components'][] = ['instance' => $component->instance_key, 'role' => $component->role, 'article' => $m['articles'][$component->component_key],
						'profile' => array_intersect_key($m['dependencies'][$profileKey], array_flip(['table', 'id', 'key', 'revision', 'sha256', 'status'])), 'dataset' => array_intersect_key($m['dependencies'][$targetKey], array_flip(['table', 'id', 'key', 'revision', 'sha256', 'status'])), 'model' => $record->data->model ?? null,
						'refrigerant' => $record->data->refrigerant ?? null, 'electrical' => $record->data->electrical ?? null];
				}
				foreach($m['dependencies'] as $node) $c['packages'][] = array_intersect_key($node, array_flip(['table', 'id', 'key', 'revision', 'sha256', 'status', 'approved', 'approvedby', 'withdrawn', 'approval_sha256']));
				$c['calculations'] = $this->calculations($view['choices'], $m['blocked']);
				foreach($m['records'] as $record) foreach($record->data->missing_evidence ?? [] as $reason) $c['blockers'][] = $record->dataset_key.':'.$reason;
			}
			$c['coverage'] = $this->coverage($m);
			$c['blockers'] = array_values(array_unique(array_merge($c['blockers'], ['real_machine_release_missing', 'saas_entitlement_unavailable'])));
			foreach($out['requirements'] as $requirement) $c['assessments'][$requirement['name']] = $this->assessment($requirement, $c);
			foreach($history as $row) if((int)$row['itemid'] === $itemId) $c['history'][] = array_intersect_key($row, array_flip(['id', 'version', 'created', 'datasetid', 'dataset_revision', 'status']));
			if($saved) $c['historical'] = ['id' => (int)$saved['row']['id'], 'version' => (int)$saved['row']['version'], 'created' => $saved['row']['created'],
				'article' => $saved['proof']['article'], 'machine' => $saved['proof']['machine'], 'variant' => $saved['proof']['variant'], 'components' => $saved['proof']['components'],
				'calculation' => $saved['proof']['calculation'], 'result' => $saved['proof']['result'], 'current' => $saved['current'], 'eligible' => false];
			$out['candidates'][] = $c;
		}
		if(mb_strlen($search) >= 2) $out['matches'] = $technical->open($inquiryId, $search)['matches'];
		return $out;
	}

	private function id($value): int
	{
		if((!is_int($value) && !is_string($value)) || !filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
		return (int)$value;
	}

	private function calculations(array $choices, bool $blocked): array
	{
		$out = [];
		foreach(['compressor', 'stage', 'thermal', 'hydraulic', 'operating', 'coupled'] as $kind) $out[$kind] = ['available' => false, 'blocked' => $blocked, 'choices' => []];
		$types = ['evaluateCompressor' => 'compressor', 'evaluateMachineStage' => 'stage', 'evaluateThermalCircuit' => 'thermal', 'evaluateThermalMachine' => 'thermal', 'evaluateHydraulics' => 'hydraulic', 'evaluateHydraulicDistribution' => 'hydraulic', 'evaluateOperatingMode' => 'operating', 'evaluateThermohydraulic' => 'coupled'];
		foreach($choices as $key => $choice) { $kind = $types[$choice['method']]; $out[$kind]['available'] = true; $out[$kind]['choices'][] = ['key' => $key, 'label' => $choice['label'], 'mode' => $choice['mode'] ?? null, 'stage' => $choice['stage'] ?? null, 'contract' => $choice['contract'] ?? null]; }
		return $out;
	}

	private function coverage(?array $m): array
	{
		$areas = [];
		foreach(self::AREAS as $key) $areas[$key] = ['state' => 'missing', 'references' => [], 'gap' => 'CONTACT_COMPARE_GAP_'.strtoupper($key)];
		if(!$m) return $areas;
		$keys = array_fill_keys(self::AREAS, []); $complete = array_fill_keys(self::AREAS, true);
		$d = $m['record']->data; $compressors = 0; $evaporators = 0; $condensers = 0; $pumps = 0;
		$add = static function(string $area, string $key) use(&$keys): void { $keys[$area][$key] = true; };
		$refKey = static fn($ref) => 'technicaldataset:'.$ref->dataset_key.'@'.$ref->revision;
		foreach($d->components as $component) {
			$profileKey = 'itemtechnicalprofile:'.$component->profile_key.'@'.$component->profile_revision;
			$p = $m['records'][$profileKey]; $key = $refKey($p->dataset_ref); $r = $m['records'][$key];
			if($component->role === 'compressor') {
				$compressors++;
				if($r->dataset_type !== 'component_spec' || $r->data->specification->oil_return_stage_limits_ref === null) $complete['parallel_oil'] = false;
				$add('compressor_electrical', $profileKey); $add('compressor_electrical', $key);
				$electrical = $r->data->electrical;
				if($electrical === null || $electrical->voltage_V === null || $electrical->phases === null || $electrical->execution_code === null) $complete['compressor_electrical'] = false;
				if($r->dataset_type === 'component_spec') {
					if($r->data->specification->oil_return_stage_limits_ref !== null) $add('parallel_oil', $refKey($r->data->specification->oil_return_stage_limits_ref));
					$ref = $r->data->specification->polynomial_ref;
					if($ref === null) { $complete['refrigerant_curve'] = false; $complete['envelope_conditions'] = false; continue; }
					$key = $refKey($ref); $r = $m['records'][$key];
				}
				$add('refrigerant_curve', $profileKey); $add('refrigerant_curve', $key); $add('envelope_conditions', $profileKey); $add('envelope_conditions', $key);
				if($r->data->operating_envelope === null || ($r->data->conditions->frequency_Hz === null && $r->data->conditions->speed_rpm === null) || $r->data->conditions->subcooling_K === null || $r->data->temperature_basis->Te === null || $r->data->temperature_basis->Tc === null) $complete['envelope_conditions'] = false;
			} elseif(in_array($component->role, ['evaporator', 'air_condenser', 'water_condenser', 'recovery_exchanger'], true)) {
				$area = $component->role === 'evaporator' ? 'evaporator_maps' : 'condenser_maps';
				if($area === 'evaporator_maps') $evaporators++; else $condensers++;
				$ref = $r->data->specification->thermal_map_ref;
				if($ref === null) $complete[$area] = false; else { $add($area, $profileKey); $add($area, $refKey($ref)); }
			} elseif($component->role === 'pump') {
				$pumps++; $ref = $r->data->specification->curve_ref;
				if($ref === null) $complete['hydraulics_pumps'] = false; else { $add('hydraulics_pumps', $profileKey); $add('hydraulics_pumps', $refKey($ref)); }
			}
		}
		if(!$compressors) foreach(['compressor_electrical', 'refrigerant_curve', 'envelope_conditions'] as $area) $complete[$area] = false;
		if(!$evaporators) $complete['evaporator_maps'] = false;
		if(!$condensers) $complete['condenser_maps'] = false;
		if(!$pumps || !$d->hydraulic_loops) $complete['hydraulics_pumps'] = false;
		foreach($d->hydraulic_loops as $loop) foreach(['fluid_dataset', 'pressure_loss_dataset'] as $name) { if($loop->$name === null) $complete['hydraulics_pumps'] = false; else $add('hydraulics_pumps', $refKey($loop->$name)); }
		foreach($d->stages as $stage) { if($stage->controls_ref === null) $complete['controls_stages'] = false; else $add('controls_stages', $refKey($stage->controls_ref)); }
		foreach($d->circuits as $circuit) { if($circuit->control_dataset === null) $complete['controls_stages'] = false; else $add('controls_stages', $refKey($circuit->control_dataset)); }
		foreach($m['records'] as $key => $r) {
			if(!str_starts_with($key, 'technicaldataset:')) continue;
			if(in_array($r->dataset_type, ['thermal_balance_model', 'operating_mode_model'], true)) $add('thermal_balance', $key);
			if($r->dataset_type === 'reference_points') foreach($r->data->records as $point) if(in_array($point->reference_kind, ['manufacturer_machine', 'measurement'], true) && $point->dataset_ref->dataset_key === $m['identity']['key'] && $point->dataset_ref->revision === $m['identity']['revision'] && $point->engineering_approval !== null) $add('oem_references', $key);
		}
		foreach($d->mode_topologies as $mode) if($mode->release_status !== 'unsupported') {
			$add('operating_release', 'technicaldataset:'.$m['identity']['key'].'@'.$m['identity']['revision']);
			if($mode->release_status !== 'approved') $complete['operating_release'] = false;
		}
		$parallel = (bool)array_filter($d->circuits, fn($c) => count($c->compressor_instances) > 1);
		foreach($keys as $area => $members) {
			$refs = []; $synthetic = false; $approved = true; $withdrawn = false;
			foreach(array_keys($members) as $key) {
				$node = $m['dependencies'][$key]; $r = $m['records'][$key];
				$refs[] = array_intersect_key($node, array_flip(['table', 'id', 'key', 'revision', 'sha256', 'status']));
				$synthetic = $synthetic || ($r->import_context->production_import_allowed ?? null) === false || ($r->data->usage ?? '') === 'synthetic_test_only';
				$approved = $approved && $node['status'] === 'approved'; $withdrawn = $withdrawn || $node['status'] === 'withdrawn';
			}
			$state = !$refs ? 'missing' : ($withdrawn ? 'withdrawn' : (!$complete[$area] ? 'incomplete' : ($synthetic ? 'synthetic_only' : ($approved ? 'approved_data_only' : 'present_unreleased'))));
			if($area === 'parallel_oil' && !$parallel) $state = 'not_applicable';
			$areas[$area]['state'] = $state; $areas[$area]['references'] = $refs;
		}
		return $areas;
	}

	private function requirements(array $view): array
	{
		$out = [];
		foreach($view['requirements'] as $field) {
			$raw = $view['inquiry']['data'][$field['name']] ?? null;
			$field['state'] = $raw === null || $raw === '' || $raw === [] ? 'requirement_missing' : 'requirement_present';
			$field['semantic'] = null; $field['meaning'] = null;
			// Only verified field meanings are recognized; units and labels are part of the mapping.
			$known = ['einsatzbereich' => ['Anwendungsbereich', '', 'application'], 'kuehlleistung' => ['Erforderliche Kälteleistung', 'kW', 'capacity'],
				'heizleistung' => ['Erforderliche Heizleistung, falls bekannt', 'kW', 'capacity'], 'vorlauf_temp' => ['Gewünschte Vorlauftemperatur zum Verbraucher', '°C', 'temperature'],
				'ruecklauf_temp' => ['Rücklauftemperatur vom Verbraucher', '°C', 'temperature'], 'volumenstrom' => ['Erforderlicher Volumenstrom, falls bekannt', 'm³/h', 'flow'],
				'aufstellung' => ['Aufstellung des Geräts', '', 'installation'], 'medium' => ['Wärmeträger', '', 'fluid']];
			$mapping = $known[$field['name']] ?? null;
			if($mapping && $field['label'] === $mapping[0] && ($field['unit'] ?? '') === $mapping[1]) $field['semantic'] = $mapping[2];
			if($field['semantic'] && in_array($field['semantic'], ['capacity', 'temperature', 'flow'], true) && $field['state'] !== 'requirement_missing') {
				$raw = $view['inquiry']['data'][$field['name']] ?? null;
				try { $number = Items_Service_TechnicalJson::decimal($raw); if(in_array($field['semantic'], ['capacity', 'flow'], true) && (float)$number <= 0) throw new InvalidArgumentException('Invalid demand'); }
				catch(InvalidArgumentException $e) { $field['state'] = 'requirement_ambiguous'; }
			} elseif($mapping && !$field['semantic'] && $field['state'] !== 'requirement_missing') $field['state'] = 'requirement_ambiguous';
			$choices = ['application' => ['0' => ['Prozesskühlung', 'cooling'], '1' => ['Komfortklimatisierung', 'cooling'], '2' => ['Heizung', 'heat_pump'], '3' => ['Warmwasser', 'heat_pump']],
				'installation' => ['innen' => ['Innenaufstellung', 'indoor'], 'aussen' => ['Außenaufstellung', 'outdoor']], 'fluid' => ['0' => ['Wasser', 'water'], '1' => ['Wasser/Glykol', 'water_glycol']]];
			if(isset($choices[$field['semantic'] ?? '']) && $field['state'] === 'requirement_present') {
				$choice = $choices[$field['semantic']][is_scalar($raw) ? (string)$raw : ''] ?? null;
				if(!$choice || !$field['translate_value'] || $field['value'] !== $choice[0]) $field['state'] = 'requirement_ambiguous';
				else $field['meaning'] = $choice[1];
			}
			$field['raw'] = $raw;
			$out[$field['name']] = $field;
		}
		$application = $out['einsatzbereich'] ?? null;
		if($application && $application['semantic'] === 'application' && $application['state'] === 'requirement_present') {
			$mode = $application['meaning'];
			if($mode === null) $out['einsatzbereich']['state'] = 'requirement_ambiguous';
			$flow = $out['vorlauf_temp'] ?? null; $return = $out['ruecklauf_temp'] ?? null;
			if($mode && $flow && $return && $flow['semantic'] === 'temperature' && $return['semantic'] === 'temperature' && $flow['state'] === 'requirement_present' && $return['state'] === 'requirement_present') {
				$order = Items_Service_TechnicalJson::compareDecimal((string)$flow['raw'], (string)$return['raw']);
				if(($mode === 'cooling' && $order >= 0) || ($mode === 'heat_pump' && $order <= 0)) foreach(['vorlauf_temp', 'ruecklauf_temp'] as $key) $out[$key]['state'] = 'requirement_conflict';
			}
		}
		return array_values($out);
	}

	private function assessment(array $requirement, array $candidate): array
	{
		$state = $requirement['state']; $detail = null;
		if($state !== 'requirement_present') return ['state' => $state, 'detail' => null];
		if(!$candidate['configuration']) return ['state' => 'feature_missing', 'detail' => null];
		$d = $candidate['configuration'];
		if($requirement['semantic'] === 'application') {
			$mode = $requirement['meaning'];
			$matches = array_values(array_filter($d['modes'], fn($v) => $v->mode === $mode));
			$state = $matches && $matches[0]->release_status !== 'unsupported' ? 'feature_present_comparable' : 'calculation_unavailable'; $detail = $mode;
		} elseif($requirement['semantic'] === 'installation') {
			$requested = $requirement['meaning'];
			$state = !$requested ? 'requirement_ambiguous' : ($d['installation_kind'] === $requested ? 'feature_present_comparable' : 'feature_difference'); $detail = $d['installation_kind'];
		} elseif(in_array($requirement['semantic'], ['capacity', 'temperature', 'flow', 'fluid'], true)) {
			$available = $requirement['semantic'] === 'flow' || $requirement['semantic'] === 'fluid' ? ($candidate['calculations']['hydraulic']['available'] || $candidate['calculations']['coupled']['available']) : ($candidate['calculations']['thermal']['available'] || $candidate['calculations']['operating']['available'] || $candidate['calculations']['coupled']['available']);
			$state = $available ? 'evidence_incomplete' : 'calculation_unavailable';
		} else $state = 'not_comparable';
		return ['state' => $state, 'detail' => $detail];
	}
}
