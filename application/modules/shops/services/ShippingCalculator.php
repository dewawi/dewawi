<?php

class Shops_Service_ShippingCalculator
{
	private const MAX_MONEY = 9999999999;
	private const PACK_FIELDS = ['packweight', 'packlength', 'packwidth', 'packheight'];

	/** Configuration and item rows are trusted server data; selections contain only item IDs and quantities. */
	public function calculate(array $context, array $configuration, array $items, array $selections, array $destination): array
	{
		$result = ['version' => 1, 'status' => 'quote_required', 'price_basis' => 'net', 'net' => null, 'currency' => null, 'goods_net' => null,
			'units' => ['currency_minor_units' => 2, 'packaging_decimal_places' => 4, 'weight' => 'kg', 'dimensions' => 'cm', 'percentage_decimal_places' => 4],
			'clientid' => null, 'shopid' => null, 'zoneid' => null, 'destination' => null, 'selection' => [], 'groups' => [], 'reasons' => []];
		try {
			$clientId = self::id($context['clientid'] ?? null);
			$shopId = self::id($context['shopid'] ?? null);
			$result['clientid'] = $clientId;
			$result['shopid'] = $shopId;
			$config = $this->configuration($configuration, $clientId, $shopId);
			$result['currency'] = $config['currency'];
			if (array_diff(array_keys($destination), ['country', 'method']) || !is_string($destination['country'] ?? null)
				|| !preg_match('/^[A-Z]{2}$/D', $destination['country']) || !in_array($destination['method'] ?? 'delivery', ['delivery', 'pickup'], true)) throw new DomainException('invalid_destination');
			$method = $destination['method'] ?? 'delivery';
			$result['destination'] = ['country' => $destination['country'], 'method' => $method];
			$catalog = [];
			foreach ($items as $item) {
				if (!is_array($item)) throw new DomainException('invalid_item');
				$this->scope($item, $clientId, $shopId);
				$id = self::id($item['id'] ?? null);
				if (isset($catalog[$id])) throw new DomainException('duplicate_item');
				$catalog[$id] = $item;
			}
			$quantities = [];
			if (!$selections) throw new DomainException('empty_selection');
			foreach ($selections as $selection) {
				if (!is_array($selection) || array_diff(array_keys($selection), ['itemid', 'quantity'])
					|| !Shops_Model_ShoppingCart::validQuantity($selection['quantity'] ?? null)) throw new DomainException('invalid_selection');
				$id = self::id($selection['itemid'] ?? null);
				$quantities[$id] = self::add($quantities[$id] ?? 0, self::decimal($selection['quantity'], 4, 1000000000));
				if ($quantities[$id] > 1000000000) throw new DomainException('invalid_quantity');
			}
			ksort($quantities, SORT_NUMERIC);
			foreach ($quantities as $id => $quantity) $result['selection'][] = ['itemid' => $id, 'quantity' => self::format($quantity, 4)];
			$prices = []; $goodsValue = 0;
			foreach ($quantities as $id => $quantity) {
				$item = $catalog[$id] ?? null;
				if (!$item || !in_array($item['deleted'] ?? 0, [0, '0'], true) || !in_array($item['shopenabled'] ?? null, [1, '1'], true)) throw new DomainException('item_unavailable', 403);
				if (($item['currency'] ?? null) !== $config['currency']) throw new DomainException('item_currency_mismatch');
				$special = $item['specialprice'] ?? null;
				$special = $special === null || $special === '' ? 0 : self::decimal($special, 4, 999999999999);
				$price = $special > 0 ? $special : self::decimal($item['price'] ?? null, 4, 999999999999);
				$goodsValue = self::add($goodsValue, self::roundedProduct($price, $quantity, 1000000));
				if ($goodsValue > self::MAX_MONEY) throw new DomainException('amount_limit_exceeded');
				$prices[$id] = ['price' => $price, 'source' => $special > 0 ? 'specialprice' : 'price'];
			}
			$result['goods_net'] = self::format($goodsValue, 2);
			if ($method === 'pickup') {
				if (!$config['pickup']) throw new DomainException('pickup_unavailable', 403);
				$result['status'] = 'calculated';
				$result['net'] = '0.00';
				$result['reasons'] = ['explicit_pickup'];
				return $result;
			}
			foreach ($config['zones'] as $zone) if (in_array($destination['country'], $zone['countries'], true)) $result['zoneid'] = $zone['id'];
			if ($result['zoneid'] === null) throw new DomainException('destination_unavailable', 403);

			$groups = [];
			foreach ($quantities as $id => $quantity) {
				$item = $catalog[$id];
				$assignment = $config['assignments'][$id] ?? null;
				$class = $assignment ? $config['classes'][$assignment['classid']] : null;
				$separate = !$class || !$class['combine_same_class'] || $assignment['separate'];
				$key = $separate ? 'item:' . $id : 'class:' . $class['id'];
				if (!isset($groups[$key])) $groups[$key] = ['key' => $key, 'classid' => $class['id'] ?? null, 'class_title' => $class['title'] ?? null, 'shipments' => 1,
					'members' => [], 'combinations' => [], 'tariff' => null, 'status' => 'quote_required', 'net' => null, 'net_per_shipment' => null, 'reasons' => [], 'basis' => []];
				$group = &$groups[$key];
				if (!$assignment) { $group['reasons'][] = 'missing_shipping_class'; $group['members'][] = ['itemid' => $id, 'quantity' => self::format($quantity, 4)]; unset($group); continue; }
				if ($separate && $quantity % 10000 !== 0) { $group['reasons'][] = 'indeterminate_separate_shipments'; unset($group); continue; }
				$group['shipments'] = $separate ? intdiv($quantity, 10000) : 1;
				$unitQuantity = $separate ? 10000 : $quantity;
				$packages = self::multiply($unitQuantity, $assignment['packages_per_unit']);
				if ($packages % 10000 !== 0) { $group['reasons'][] = 'indeterminate_package_count'; unset($group); continue; }
				$physical = [];
				foreach (self::PACK_FIELDS as $field) {
					$value = $item[$field] ?? null;
					$physical[$field] = $value === null || $value === '' ? null : self::decimal($value, 4, 999999999999);
					if ($physical[$field] === 0) $physical[$field] = null;
					if (in_array($field, $class['required_fields'], true) && $physical[$field] === null) $group['reasons'][] = 'missing_' . $field;
				}
				$price = $prices[$id]['price'];
				$value = self::roundedProduct($price, $unitQuantity, 1000000);
				if ($value > self::MAX_MONEY) throw new DomainException('amount_limit_exceeded');
				$group['members'][] = ['itemid' => $id, 'classid' => $class['id'], 'quantity' => self::format($unitQuantity, 4),
					'unit_net_price' => self::format($price, 4), 'price_source' => $prices[$id]['source'],
					'packages' => intdiv($packages, 10000), 'packaging' => $physical, 'net_value_cents' => $value];
				unset($group);
			}
			ksort($groups, SORT_STRING);
			foreach ($groups as &$group) $group = $this->price($group, $config, $result['zoneid']);
			unset($group);
			foreach ($config['combinations'] as $rule) {
				$hostKey = 'class:' . $rule['hostclassid']; $guestKey = 'class:' . $rule['guestclassid'];
				if (!isset($groups[$hostKey], $groups[$guestKey]) || $groups[$hostKey]['status'] !== 'calculated' || $groups[$guestKey]['status'] !== 'calculated') continue;
				$host = $groups[$hostKey]; $guest = $groups[$guestKey];
				$host['combinations'][] = ['rule' => $rule, 'members' => $guest['members']];
				if (!$this->combinationFits($host['combinations'])) continue;
				$combined = $this->price($host, $config, $result['zoneid']);
				if ($combined['status'] === 'calculated') { $groups[$hostKey] = $combined; unset($groups[$guestKey]); }
			}
			$total = 0; $status = 'calculated';
			foreach ($groups as $group) {
				if ($group['status'] !== 'calculated') $status = 'quote_required';
				else $total = self::add($total, self::decimal($group['net'], 2, self::MAX_MONEY));
			}
			if ($total > self::MAX_MONEY) throw new DomainException('amount_limit_exceeded');
			$result['groups'] = array_values($groups);
			$result['status'] = $status;
			$result['net'] = $status === 'calculated' ? self::format($total, 2) : null;
		} catch (DomainException $e) {
			$result['status'] = $e->getCode() === 403 ? 'unavailable' : 'quote_required';
			$result['reasons'][] = $e->getMessage();
		}
		return $result;
	}

	private function combinationFits(array $combinations): bool
	{
		$members = array_merge(...array_column($combinations, 'members'));
		$weight = 0; $packages = 0;
		foreach ($members as $member) {
			if ($member['packaging']['packweight'] === null) return false;
			$weight = self::add($weight, self::multiply($member['packaging']['packweight'], self::decimal($member['quantity'], 4, 1000000000)));
			$packages = self::add($packages, $member['packages']);
		}
		foreach ($combinations as $combination) {
			$rule = $combination['rule'];
			if ($packages > $rule['max_guest_packages'] || $weight > $rule['max_guest_weight'] * 10000) return false;
			foreach ($members as $member) foreach (['packlength', 'packwidth', 'packheight'] as $field) {
				if (isset($rule['max_' . $field]) && ($member['packaging'][$field] === null || $member['packaging'][$field] > $rule['max_' . $field])) return false;
			}
		}
		return true;
	}

	private function price(array $group, array $config, int $zoneId): array
	{
		$members = $group['members'];
		foreach ($group['combinations'] as $combination) $members = array_merge($members, $combination['members']);
		$packages = 0; $totalPackages = 0; $weight = 0; $value = 0; $basisKnown = (bool)$members; $weightKnown = (bool)$members;
		foreach ($members as $member) {
			if (!isset($member['packages'])) { $basisKnown = $weightKnown = false; continue; }
			if ($member['classid'] === $group['classid']) $packages = self::add($packages, $member['packages']);
			$totalPackages = self::add($totalPackages, $member['packages']);
			$value = self::add($value, $member['net_value_cents']);
			if ($member['packaging']['packweight'] === null) $weightKnown = false;
			else $weight = self::add($weight, self::multiply($member['packaging']['packweight'], self::decimal($member['quantity'], 4, 1000000000)));
		}
		$group['basis'] = ['chargeable_packages' => $basisKnown ? $packages : null, 'total_packages' => $basisKnown ? $totalPackages : null, 'weight_kg' => $weightKnown ? self::format($weight, 8) : null, 'net_value' => $basisKnown ? self::format($value, 2) : null];
		$tariff = $config['tariffs'][$group['classid'] . ':' . $zoneId] ?? null;
		$group['tariff'] = $tariff;
		if (!$tariff) $group['reasons'][] = 'missing_tariff';
		elseif ($tariff['model'] === 'manual') $group['reasons'][] = 'manual_freight';
		if ($group['reasons']) return $group;
		foreach (['weight' => $weightKnown ? $weight : null, 'packages' => $packages, 'value' => $value] as $field => $basis) {
			foreach (['min', 'max'] as $bound) if (isset($tariff[$bound . '_' . $field])) {
				$limit = $tariff[$bound . '_' . $field] * ($field === 'weight' ? 10000 : 1);
				if ($basis === null || ($bound === 'min' ? $basis < $limit : $basis > $limit)) $group['reasons'][] = $field . '_outside_tariff';
			}
		}
		$amount = null;
		switch ($tariff['model']) {
			case 'fixed': $amount = $tariff['amount']; break;
			case 'additional_packages': $amount = self::add($tariff['base'], self::multiply(max(0, $packages - 1), $tariff['additional'])); break;
			case 'percentage': $amount = self::roundedProduct($value, $tariff['percent'], 1000000); break;
			case 'weight':
				if (!$weightKnown) { $group['reasons'][] = 'missing_packweight'; break; }
				foreach ($tariff['tiers'] as $tier) if ($weight <= $tier['up_to'] * 10000) { $amount = $tier['amount']; break; }
				if ($amount === null) $group['reasons'][] = 'weight_outside_tariff';
				break;
		}
		if ($amount !== null && self::multiply($amount, $group['shipments']) > self::MAX_MONEY) $group['reasons'][] = 'amount_limit_exceeded';
		if (!$group['reasons'] && $amount !== null) { $group['status'] = 'calculated'; $group['net_per_shipment'] = self::format($amount, 2); $group['net'] = self::format(self::multiply($amount, $group['shipments']), 2); }
		else { $group['status'] = 'quote_required'; $group['net'] = $group['net_per_shipment'] = null; }
		return $group;
	}

	private function configuration(array $data, int $clientId, int $shopId): array
	{
		$this->fields($data, ['version', 'clientid', 'shopid', 'currency', 'minor_units', 'pickup', 'zones', 'classes', 'assignments', 'tariffs', 'combinations']);
		$this->scope($data, $clientId, $shopId);
		if (($data['version'] ?? null) !== 1 || !is_string($data['currency'] ?? null) || !preg_match('/^[A-Z]{3}$/D', $data['currency'])
			|| ($data['minor_units'] ?? null) !== 2 || !is_bool($data['pickup'] ?? null)) throw new DomainException('invalid_configuration');
		$config = array_intersect_key($data, array_flip(['currency', 'pickup']));
		foreach (['zones', 'classes', 'assignments', 'tariffs', 'combinations'] as $table) {
			if (!isset($data[$table]) || !is_array($data[$table])) throw new DomainException('invalid_configuration');
			$config[$table] = [];
			foreach ($data[$table] as $row) {
				if (!is_array($row)) throw new DomainException('invalid_configuration');
				$this->scope($row, $clientId, $shopId);
				$fields = [
					'zones' => ['countries'], 'classes' => ['title', 'combine_same_class', 'required_fields'],
					'assignments' => ['itemid', 'classid', 'packages_per_unit', 'separate'],
					'tariffs' => ['classid', 'zoneid', 'model', 'amount', 'base', 'additional', 'percent', 'tiers', 'min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'],
					'combinations' => ['hostclassid', 'guestclassid', 'priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight'],
				][$table];
				$this->fields($row, array_merge(['id', 'clientid', 'shopid', 'deleted'], $fields));
				$id = self::id($row['id'] ?? null);
				if (isset($config[$table][$id]) || !in_array($row['deleted'] ?? 0, [0, '0'], true)) throw new DomainException('invalid_configuration');
				$row['id'] = $id; $row['clientid'] = $clientId; $row['shopid'] = $shopId;
				$config[$table][$id] = $row;
			}
			ksort($config[$table], SORT_NUMERIC);
		}
		$countries = [];
		foreach ($config['zones'] as &$zone) {
			if (!is_array($zone['countries'] ?? null) || !$zone['countries']) throw new DomainException('invalid_zone');
			foreach ($zone['countries'] as $country) {
				if (!is_string($country) || !preg_match('/^[A-Z]{2}$/D', $country) || isset($countries[$country])) throw new DomainException('ambiguous_zone');
				$countries[$country] = true;
			}
			sort($zone['countries'], SORT_STRING);
		}
		unset($zone);
		foreach ($config['classes'] as $class) {
			if (!is_string($class['title'] ?? null) || trim($class['title']) === '' || !is_bool($class['combine_same_class'] ?? null)
				|| !is_array($class['required_fields'] ?? null) || array_filter($class['required_fields'], static fn($field): bool => !is_string($field) || !in_array($field, self::PACK_FIELDS, true))) throw new DomainException('invalid_class');
		}
		$assignments = [];
		foreach ($config['assignments'] as $assignment) {
			$id = self::id($assignment['itemid'] ?? null);
			if (isset($assignments[$id]) || !isset($config['classes'][self::id($assignment['classid'] ?? null)]) || !is_bool($assignment['separate'] ?? null)) throw new DomainException('invalid_assignment');
			$assignment['packages_per_unit'] = self::id($assignment['packages_per_unit'] ?? null);
			$assignments[$id] = $assignment;
		}
		$config['assignments'] = $assignments;
		$tariffs = [];
		foreach ($config['tariffs'] as $tariff) {
			$classId = self::id($tariff['classid'] ?? null); $zoneId = self::id($tariff['zoneid'] ?? null);
			$key = $classId . ':' . $zoneId;
			if (!isset($config['classes'][$classId], $config['zones'][$zoneId]) || isset($tariffs[$key])
				|| !in_array($tariff['model'] ?? null, ['fixed', 'weight', 'additional_packages', 'percentage', 'manual'], true)) throw new DomainException('invalid_tariff');
			$modelFields = ['fixed' => ['amount'], 'additional_packages' => ['base', 'additional'], 'percentage' => ['percent'], 'weight' => ['tiers'], 'manual' => []][$tariff['model']];
			$this->fields($tariff, array_merge(['id', 'clientid', 'shopid', 'deleted', 'classid', 'zoneid', 'model', 'min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'], $modelFields));
			foreach (['min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'] as $field) if (array_key_exists($field, $tariff)) {
				$precision = str_ends_with($field, 'weight') ? 4 : (str_ends_with($field, 'value') ? 2 : 0);
				$tariff[$field] = self::decimal($tariff[$field], $precision, $precision === 2 ? self::MAX_MONEY : 999999999999);
			}
			foreach (['weight', 'packages', 'value'] as $field) if (isset($tariff['min_' . $field], $tariff['max_' . $field]) && $tariff['min_' . $field] > $tariff['max_' . $field]) throw new DomainException('invalid_tariff_bounds');
			foreach (['fixed' => ['amount'], 'additional_packages' => ['base', 'additional'], 'percentage' => ['percent']][$tariff['model']] ?? [] as $field) {
				$tariff[$field] = self::decimal($tariff[$field] ?? null, $field === 'percent' ? 4 : 2, $field === 'percent' ? 1000000 : self::MAX_MONEY);
			}
			if ($tariff['model'] === 'weight') {
				if (!is_array($tariff['tiers'] ?? null) || !$tariff['tiers']) throw new DomainException('invalid_weight_tiers');
				$last = 0;
				foreach ($tariff['tiers'] as &$tier) {
					if (!is_array($tier)) throw new DomainException('invalid_weight_tiers');
					$this->fields($tier, ['up_to', 'amount']);
					$tier['up_to'] = self::decimal($tier['up_to'] ?? null, 4, 999999999999);
					$tier['amount'] = self::decimal($tier['amount'] ?? null, 2, self::MAX_MONEY);
					if ($tier['up_to'] <= $last) throw new DomainException('invalid_weight_tiers');
					$last = $tier['up_to'];
				}
				unset($tier);
			}
			$tariffs[$key] = $tariff;
		}
		$config['tariffs'] = $tariffs;
		$hosts = []; $guests = []; $priorities = []; $pairs = [];
		foreach ($config['combinations'] as &$rule) {
			$host = self::id($rule['hostclassid'] ?? null); $guest = self::id($rule['guestclassid'] ?? null);
			$priority = self::id($rule['priority'] ?? null);
			if (!isset($config['classes'][$host], $config['classes'][$guest]) || $host === $guest || isset($priorities[$priority]) || isset($pairs[$host . ':' . $guest])
				|| !$config['classes'][$host]['combine_same_class'] || !$config['classes'][$guest]['combine_same_class']) throw new DomainException('invalid_combination');
			$pairs[$host . ':' . $guest] = true; $priorities[$priority] = true; $hosts[$host] = true; $guests[$guest] = true;
			$rule['max_guest_packages'] = self::id($rule['max_guest_packages'] ?? null);
			$rule['max_guest_weight'] = self::decimal($rule['max_guest_weight'] ?? null, 4, 999999999999);
			if ($rule['max_guest_weight'] === 0) throw new DomainException('invalid_combination');
			foreach (['packlength', 'packwidth', 'packheight'] as $field) if (array_key_exists('max_' . $field, $rule)) {
				$rule['max_' . $field] = self::decimal($rule['max_' . $field], 4, 999999999999);
				if ($rule['max_' . $field] === 0) throw new DomainException('invalid_combination');
			}
		}
		unset($rule);
		if (array_intersect_key($hosts, $guests)) throw new DomainException('combination_chain_not_supported');
		usort($config['combinations'], static fn(array $a, array $b): int => (int)$a['priority'] <=> (int)$b['priority']);
		return $config;
	}

	private function fields(array $row, array $allowed): void
	{
		if (array_diff(array_keys($row), $allowed)) throw new DomainException('unsupported_configuration_field');
	}

	private function scope(array $row, int $clientId, int $shopId): void
	{
		if (self::id($row['clientid'] ?? null) !== $clientId || self::id($row['shopid'] ?? null) !== $shopId) throw new DomainException('scope_mismatch', 403);
	}

	private static function id($value): int
	{
		$value = self::decimal($value, 0, 2147483647);
		if ($value <= 0) throw new DomainException('invalid_identifier');
		return $value;
	}

	private static function decimal($value, int $precision, int $maximum): int
	{
		if (!is_string($value) && !is_int($value) && !is_float($value)) throw new DomainException('invalid_decimal');
		$pattern = $precision ? '/^([0-9]+)(?:\.([0-9]{1,' . $precision . '}))?$/D' : '/^([0-9]+)$/D';
		if (!preg_match($pattern, (string)$value, $match)) throw new DomainException('invalid_decimal');
		$digits = ltrim($match[1] . str_pad($match[2] ?? '', $precision, '0'), '0');
		if (strlen($digits) > strlen((string)$maximum) || (strlen($digits) === strlen((string)$maximum) && strcmp($digits, (string)$maximum) > 0)) throw new DomainException('decimal_limit_exceeded');
		return $digits === '' ? 0 : (int)$digits;
	}

	private static function add(int $a, int $b): int
	{
		if ($a > PHP_INT_MAX - $b) throw new DomainException('calculation_overflow');
		return $a + $b;
	}

	private static function multiply(int $a, int $b): int
	{
		if ($b !== 0 && $a > intdiv(PHP_INT_MAX, $b)) throw new DomainException('calculation_overflow');
		return $a * $b;
	}

	private static function roundedProduct(int $a, int $b, int $divisor): int
	{
		return self::add(self::multiply(intdiv($a, $divisor), $b), intdiv(self::add(self::multiply($a % $divisor, $b), intdiv($divisor, 2)), $divisor));
	}

	private static function format(int $value, int $precision): string
	{
		$digits = str_pad((string)$value, $precision + 1, '0', STR_PAD_LEFT);
		return $precision ? substr($digits, 0, -$precision) . '.' . substr($digits, -$precision) : $digits;
	}
}
