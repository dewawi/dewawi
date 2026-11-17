<?php

class Contacts_Model_GeocodingDiagnostic extends Contacts_Model_Map
{
	public const CLIENT_ID = 100;
	private const ADDRESS_COLUMNS = ['addressid' => 'id', 'street', 'postcode', 'city', 'country', 'latitude', 'longitude', 'geoupdated', 'address_modified' => 'modified', 'address_created' => 'created'];
	private const COUNTERS = ['total', 'valid_coordinates', 'missing_coordinates', 'no_coordinates', 'partial_coordinates', 'invalid_coordinates', 'postal_complete', 'postal_incomplete', 'no_billing_address', 'other_valid_coordinates', 'other_billing_valid_coordinates', 'coordinates_only_on_other_address', 'stale_coordinates_hint', 'zero_zero_hint', 'valid_coordinates_without_timestamp', 'coordinate_gaps_with_timestamp', 'legacy_due', 'eligible_addresses', 'eligible_not_due_in_legacy_cron'];

	public function diagnose(int $clientId, array $params, array $options): array
	{
		if ($clientId !== self::CLIENT_ID) throw new InvalidArgumentException('K2A diagnosis is limited to client 100');
		$this->authorize();
		$user = Zend_Registry::get('User');
		$identity = Zend_Auth::getInstance()->getIdentity();
		$owner = $this->getClientId(['clientModule' => 'contacts']);
		if ((int)$user['clientid'] !== $clientId || (int)($identity->clientid ?? 0) !== $clientId || (int)($identity->id ?? 0) !== (int)$user['id'] || $owner !== $clientId) throw new RuntimeException('Diagnostic client context mismatch', 403);
		$params = $this->filters($params, $options);
		$select = $this->locations($params, $options, self::ADDRESS_COLUMNS, ['id', 'clientid']);
		$db = $select->getAdapter();
		$counts = array_fill_keys(self::COUNTERS, 0);
		$missingFields = array_fill_keys(['street', 'postcode', 'city', 'country'], 0);
		$countries = $roles = $groups = [];
		$roleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId($owner);
		$after = 0;
		do {
			$batch = clone $select;
			$rows = $db->fetchAll($batch->where('id > ?', $after)->order('id ASC')->limit(500));
			if (!$rows) break;
			$ids = array_map('intval', array_column($rows, 'id'));
			$selectedAddresses = array_column($rows, 'addressid', 'id');
			$contactRoles = $roleDb->getForContacts($ids);
			$otherFlags = [];
			$other = $db->select()->from('address', array_merge(self::ADDRESS_COLUMNS, ['parentid', 'type']))
				->where('parentid IN (?)', $ids)->where('clientid = ?', $owner)->where('deleted = ?', 0)
				->where('module = ?', 'contacts')->where('controller = ?', 'contact');
			foreach ($db->fetchAll($other) as $address) {
				if ((int)$address['addressid'] === (int)$selectedAddresses[$address['parentid']] || !$this->validCoordinates($address)) continue;
				$otherFlags[$address['parentid']]['any'] = true;
				if ($address['type'] === 'billing') $otherFlags[$address['parentid']]['billing'] = true;
				$postal = $this->postal($address, $options);
				if ($postal['key'] !== null) $this->donor($groups, $postal['key'], $address);
			}
			foreach ($rows as $row) {
				$postal = $this->postal($row, $options);
				$valid = $this->validCoordinates($row);
				$missing = $row['latitude'] === null || $row['longitude'] === null;
				$timestamp = $this->timestamp($row['geoupdated']);
				$changed = $row['address_modified'] ?? $row['address_created'];
				$flags = [
					'total' => true, 'valid_coordinates' => $valid, 'missing_coordinates' => $missing,
					'no_coordinates' => $row['latitude'] === null && $row['longitude'] === null,
					'partial_coordinates' => $missing && !($row['latitude'] === null && $row['longitude'] === null),
					'invalid_coordinates' => !$missing && !$valid,
					'postal_complete' => $postal['key'] !== null, 'postal_incomplete' => $postal['key'] === null,
					'no_billing_address' => $row['addressid'] === null,
					'other_valid_coordinates' => !empty($otherFlags[$row['id']]['any']),
					'other_billing_valid_coordinates' => !empty($otherFlags[$row['id']]['billing']),
					'coordinates_only_on_other_address' => !$valid && !empty($otherFlags[$row['id']]['any']),
					'stale_coordinates_hint' => $valid && $timestamp && $this->timestamp($changed) && $row['geoupdated'] < $changed,
					'zero_zero_hint' => $valid && (float)$row['latitude'] === 0.0 && (float)$row['longitude'] === 0.0,
					'valid_coordinates_without_timestamp' => $valid && !$timestamp,
					'coordinate_gaps_with_timestamp' => !$valid && $timestamp,
					'legacy_due' => $row['addressid'] !== null && $row['street'] !== null && trim($row['street'], ' ') !== '' && ($row['geoupdated'] === null || ($changed !== null && $row['geoupdated'] < $changed)),
					'eligible_addresses' => !$valid && $postal['key'] !== null,
				];
				$flags['eligible_not_due_in_legacy_cron'] = $flags['eligible_addresses'] && !$flags['legacy_due'];
				$this->addCounts($counts, $flags);
				$this->addCounts($countries[$postal['country']], $flags);
				foreach ($contactRoles[$row['id']] ?? ['unclassified'] as $role) $this->addCounts($roles[$role], $flags);
				foreach ($postal['missing'] as $field) $missingFields[$field]++;
				if ($postal['key'] === null) continue;
				$key = $postal['key'];
				$this->group($groups, $key);
				$groups[$key]['selected']++;
				if ($valid) $this->donor($groups, $key, $row);
				else $groups[$key]['pending']++;
			}
			$after = (int)end($rows)['id'];
		} while (count($rows) === 500);
		$duplicates = ['complete_address_groups' => 0, 'duplicate_groups' => 0, 'duplicate_selected_addresses' => 0, 'conflicting_coordinate_groups' => 0];
		$plan = ['eligible_addresses' => $counts['eligible_addresses'], 'unique_candidate_addresses' => 0, 'duplicate_requests_avoided' => 0, 'potential_reuse_addresses' => 0, 'potential_reuse_groups' => 0, 'estimated_requests_after_reuse' => 0];
		foreach ($groups as $group) {
			if (!$group['selected']) continue;
			$duplicates['complete_address_groups']++;
			if ($group['selected'] > 1) {
				$duplicates['duplicate_groups']++;
				$duplicates['duplicate_selected_addresses'] += $group['selected'] - 1;
			}
			if ($group['conflict']) $duplicates['conflicting_coordinate_groups']++;
			if (!$group['pending']) continue;
			$plan['unique_candidate_addresses']++;
			if ($group['reusable'] && !$group['conflict']) {
				$plan['potential_reuse_groups']++;
				$plan['potential_reuse_addresses'] += $group['pending'];
			} else $plan['estimated_requests_after_reuse']++;
		}
		$plan['duplicate_requests_avoided'] = $plan['eligible_addresses'] - $plan['unique_candidate_addresses'];
		ksort($countries); ksort($roles);
		return [
			'scope' => ['selected_clientid' => $clientId, 'contact_clientid' => $owner, 'active_filters' => array_keys(array_diff_assoc($params, self::FILTERS))],
			'counts' => $counts, 'missing_or_invalid_postal_fields' => $missingFields, 'duplicates' => $duplicates, 'plan' => $plan,
			'by_country' => $countries, 'by_role' => $roles, 'by_contact_client' => [$owner => $counts],
			'external_requests' => 0, 'database_writes' => 0,
		];
	}

	private function addCounts(?array &$counts, array $flags): void
	{
		if ($counts === null) $counts = array_fill_keys(self::COUNTERS, 0);
		foreach ($flags as $name => $flag) $counts[$name] += (int)$flag;
	}

	private function postal(array $row, array $options): array
	{
		$fields = []; $missing = [];
		foreach (['street', 'postcode', 'city', 'country'] as $field) {
			$value = (string)($row[$field] ?? '');
			$value = mb_check_encoding($value, 'UTF-8') ? Normalizer::normalize($value, Normalizer::FORM_C) : '';
			$value = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $value));
			$fields[$field] = mb_strtolower($value, 'UTF-8');
			if ($value === '' || preg_match('/[\x00-\x1f\x7f]/u', $value)) $missing[] = $field;
		}
		$country = strtoupper($fields['country']);
		if (!preg_match('/^[A-Z]{2}$/D', $country) || !isset($options['country'][$country])) {
			$country = 'unknown';
			$missing[] = 'country';
		}
		return ['country' => $country, 'missing' => array_unique($missing), 'key' => $missing ? null : hash('sha256', self::CLIENT_ID.json_encode($fields))];
	}

	private function validCoordinates(array $row): bool
	{
		foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) if (!is_numeric($row[$field]) || !is_finite((float)$row[$field]) || abs((float)$row[$field]) > $limit) return false;
		return true;
	}

	private function timestamp(?string $value): bool
	{
		if ($value === null || $value < '1000-01-01 00:00:00') return false;
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
		return $date && $date->format('Y-m-d H:i:s') === $value;
	}

	private function group(array &$groups, string $key): void
	{
		if (!isset($groups[$key])) $groups[$key] = ['selected' => 0, 'pending' => 0, 'coordinates' => null, 'conflict' => false, 'reusable' => false];
	}

	private function donor(array &$groups, string $key, array $row): void
	{
		$this->group($groups, $key);
		$point = number_format((float)$row['latitude'], 9, '.', '').','.number_format((float)$row['longitude'], 9, '.', '');
		if ($groups[$key]['coordinates'] !== null && $groups[$key]['coordinates'] !== $point) $groups[$key]['conflict'] = true;
		$groups[$key]['coordinates'] = $point;
		$changed = $row['address_modified'] ?? $row['address_created'];
		if ($this->timestamp($row['geoupdated']) && $this->timestamp($changed) && $row['geoupdated'] >= $changed && !((float)$row['latitude'] === 0.0 && (float)$row['longitude'] === 0.0)) $groups[$key]['reusable'] = true;
	}
}
