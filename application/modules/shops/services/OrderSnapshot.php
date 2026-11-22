<?php

class Shops_Service_OrderSnapshot
{
	public static function canOrder(array $order): bool
	{
		$freight = self::decode($order['snapshot'] ?? null)['freight'] ?? null;
		return isset($order['taxes'], $order['grosstotal']) && $freight && $freight['status'] === 'calculated';
	}

	private static function money($amount): string { return number_format((float)$amount, 2, '.', ''); }
	private static function validCountry(string $country): bool
	{
		$numeric = preg_match('/^[A-Z]{2}$/D', $country) ? Zend_Locale_Data::getContent('en', 'numerictoterritory', $country) : '';
		return $numeric !== '' && (int)$numeric > 0 && (int)$numeric < 900;
	}

	public static function taxDecision(array $freight, array $billing, array $shipping, array $rates): array
	{
		$origin = (string)($freight['origin_country'] ?? '');
		$policy = $freight['tax_policy'] ?? null;
		$extended = $policy === Shops_Service_ShippingConfiguration::TAX_POLICY;
		$context = ['clientid' => $freight['clientid'], 'shopid' => $freight['shopid'], 'seller_country' => $origin,
			'dispatch_country' => $extended ? 'DE' : null, 'billing_country' => $billing['country'], 'delivery_country' => $shipping['country'],
			'customer_type' => 'unverified', 'vat_id_verified' => false, 'exemption' => 'not_applied', 'shop_policy' => $policy];
		$reasons = [];
		if (!$rates || array_filter($rates, static fn($rate): bool => !is_numeric($rate) || !is_finite((float)$rate) || (float)$rate < 0 || (float)$rate > 100)) $reasons[] = 'unknown_item_tax';
		elseif (in_array(0.0, array_map('floatval', $rates), true)) $reasons[] = 'zero_tax_basis_missing';
		$domestic = self::validCountry($origin) && $origin === $shipping['country'];
		if (!$domestic) $reasons[] = 'destination_tax_rules_missing';
		if ($extended && $origin !== 'DE') $reasons[] = 'seller_tax_context_mismatch';
		if ($policy !== null && $policy !== '' && !$extended) $reasons[] = 'unsupported_tax_policy';
		if (!$extended && $billing['country'] !== $shipping['country']) $reasons[] = 'billing_tax_context_unverified';
		if (!self::validCountry((string)$billing['country'])) $reasons[] = 'billing_tax_context_unverified';
		if (!$extended && (float)($freight['net'] ?? 0) > 0 && count(array_unique($rates)) > 1) $reasons[] = 'mixed_tax_policy_missing';
		if ($origin === 'DE' && $shipping['country'] === 'DE') {
			$postcode = trim((string)($shipping['postcode'] ?? ''));
			$city = (string)($shipping['city'] ?? '');
			if (in_array($postcode, ['27498', '78266', '8238'], true) || preg_match('/helgoland|b(?:ü|ue|u)singen|freihafen|freeport/iu', $city)
				|| ($extended && !preg_match('/^[0-9]{5}$/D', $postcode))) $reasons[] = 'special_territory_review_required';
		}
		return ['version' => 1, 'rule' => $extended ? Shops_Service_ShippingConfiguration::TAX_POLICY : 'c4b_domestic_v1',
			'status' => $reasons ? 'quote_required' : 'calculated', 'context' => $context, 'reasons' => array_values(array_unique($reasons)),
			'rounding' => 'cent_largest_remainder_rate_asc;erp_tax_group_half_up', 'calculator' => 'Calculate', 'calculation_version' => 1];
	}

	private static function cents($value): int
	{
		if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 99999999
			|| abs(round((float)$value, 2) - (float)$value) > 0.0000001) throw new DomainException('invalid_tax_allocation_amount');
		return (int)round((float)$value * 100);
	}

	/** Exact quotient and remainder without multiplying two large monetary amounts. */
	private static function ratio(int $amount, int $weight, int $base): array
	{
		$quotient = 0; $remainder = 0;
		foreach (str_split(decbin($amount)) as $bit) {
			$quotient *= 2; $remainder = $remainder * 2 + ($bit === '1' ? $weight : 0);
			$quotient += intdiv($remainder, $base); $remainder %= $base;
		}
		return [$quotient, $remainder];
	}

	private static function shippingAllocations(array $freight, array $positions, array $goods): array
	{
		$items = []; $allWeights = [];
		foreach ($positions as $index => $position) {
			$rate = (string)(float)self::decode($position['snapshot'])['taxrate'];
			$weight = self::cents($goods[$index + 1]['total']);
			$allWeights[$rate] = ($allWeights[$rate] ?? 0) + $weight;
			$items[(int)$position['itemid']] = ['rate' => $rate, 'net' => $weight, 'quantity' => (float)$position['quantity']];
		}
		$amount = self::cents($freight['net']);
		uksort($allWeights, static fn($a, $b): int => (float)$a <=> (float)$b);
		if (count($allWeights) === 1 || $amount === 0) {
			$rate = array_key_first($allWeights);
			return [['group' => 'all', 'taxrate' => (float)$rate, 'goods_net' => self::money($goods['subtotal']), 'net' => self::money($amount / 100)]];
		}
		if (count($items) !== count($positions)) throw new DomainException('ambiguous_shipping_membership');
		$groups = $freight['groups'] ?? []; $seen = []; $sum = 0; $result = [];
		usort($groups, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
		foreach ($groups as $group) {
			$net = self::cents($group['net']); $sum += $net; $weights = [];
			$members = $group['members'];
			foreach ($group['combinations'] as $combination) $members = array_merge($members, $combination['members']);
			foreach ($members as $member) {
				$id = (int)$member['itemid']; $item = $items[$id] ?? null;
				if (!$item || isset($seen[$id]) || (int)round((float)$member['quantity'] * 10000) * (int)$group['shipments'] !== (int)round($item['quantity'] * 10000)) throw new DomainException('ambiguous_shipping_membership');
				$seen[$id] = true;
				$weights[$item['rate']] = ($weights[$item['rate']] ?? 0) + $item['net'];
			}
			if (!$weights || ($net > 0 && count($weights) > 1 && array_sum($weights) === 0)) throw new DomainException('zero_value_shipping_basis');
			$base = array_sum($weights); $shares = []; $remainders = [];
			foreach ($weights as $rate => $weight) {
				[$shares[$rate], $remainders[$rate]] = count($weights) === 1 ? [$net, 0] : ($base > 0 ? self::ratio($net, $weight, $base) : [0, 0]);
			}
			$order = array_keys($weights);
			usort($order, static fn($a, $b): int => ($remainders[$b] <=> $remainders[$a]) ?: ((float)$a <=> (float)$b));
			$rest = $net - array_sum($shares);
			foreach (array_slice($order, 0, $rest) as $rate) $shares[$rate]++;
			uksort($shares, static fn($a, $b): int => (float)$a <=> (float)$b);
			foreach ($shares as $rate => $share) $result[] = ['group' => $group['key'], 'taxrate' => (float)$rate, 'goods_net' => self::money($weights[$rate] / 100), 'net' => self::money($share / 100)];
		}
		if (count($seen) !== count($items) || $sum !== $amount) throw new DomainException('ambiguous_shipping_membership');
		return $result;
	}

	private static function taxGroups(array $positions, array $goods, array $totals, array $allocations): array
	{
		$groups = [];
		foreach ($positions as $index => $position) {
			$rate = (string)(float)self::decode($position['snapshot'])['taxrate'];
			$groups[$rate] = $groups[$rate] ?? ['taxrate' => (float)$rate, 'goods_net' => 0, 'shipping_net' => 0];
			$groups[$rate]['goods_net'] += self::cents($goods[$index + 1]['total']);
		}
		foreach ($allocations as $allocation) $groups[(string)(float)$allocation['taxrate']]['shipping_net'] += self::cents($allocation['net']);
		uksort($groups, static fn($a, $b): int => (float)$a <=> (float)$b);
		foreach ($groups as $rate => &$group) {
			$group['net'] = self::money(($group['goods_net'] + $group['shipping_net']) / 100);
			$group['goods_net'] = self::money($group['goods_net'] / 100);
			$group['shipping_net'] = self::money($group['shipping_net'] / 100);
			$group['taxes'] = self::money($totals['taxes'][$rate]);
			$group['gross'] = self::money((float)$group['net'] + (float)$group['taxes']);
		}
		unset($group);
		return array_values($groups);
	}

	public static function build(array $shop, array $data, array $positions, ?array $freight = null): array
	{
		$address = static function(string $prefix) use ($data): array {
			$result = [];
			foreach (['name', 'company', 'department', 'street', 'postcode', 'city', 'country', 'phone'] as $field) $result[$field] = trim((string)($data[$prefix . $field] ?? ''));
			return $result;
		};
		$billing = $address('billing');
		$shipping = !empty($data['differentshippingaddress']) ? $address('shipping') : $billing;
		$currency = null;
		$taxKnown = true;
		$rows = [];
		foreach ($positions as $index => $position) {
			$snapshot = self::decode($position['snapshot'] ?? null);
			if (!$snapshot || !isset($snapshot['currency'], $snapshot['taxrate'])) $taxKnown = false;
			if ($currency !== null && $currency !== ($snapshot['currency'] ?? null)) throw new DomainException('SHOPS_ORDER_CURRENCY_INVALID');
			$currency = $snapshot['currency'] ?? null;
			$rows[] = (object)['id' => $index + 1, 'price' => $position['price'], 'quantity' => $position['quantity'], 'taxrate' => $snapshot['taxrate'] ?? 0];
		}
		$calculate = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate');
		$goods = $calculate->calculate(
			['currency' => $currency ?: 'EUR', 'taxfree' => 0], $rows, 'shops', 'order', (int)$shop['clientid'], null, false
		)['row'];
		$totals = $goods;
		if ($freight !== null) {
			$freight = array_replace($freight, ['taxrate' => null, 'taxes' => null, 'gross' => null, 'tax_basis' => null]);
			$decision = self::taxDecision($freight, $billing, $shipping, array_map(static fn($row) => $row->taxrate, $rows));
			if ($freight['status'] !== 'calculated') $decision['status'] = $freight['status'];
			$freight['tax_allocations'] = [];
			if ($freight['status'] === 'calculated') {
				if ($taxKnown && $decision['status'] === 'calculated' && $freight['currency'] === $currency) {
					try {
						$allocations = self::shippingAllocations($freight, $positions, $goods);
						$previous = $goods;
						foreach ($allocations as &$allocation) {
							$rows[] = (object)['id' => count($rows) + 1, 'price' => $allocation['net'], 'quantity' => 1, 'taxrate' => $allocation['taxrate']];
							$totals = $calculate->calculate(['currency' => $currency, 'taxfree' => 0], $rows, 'shops', 'order', (int)$shop['clientid'], null, false)['row'];
							$key = (string)(float)$allocation['taxrate'];
							$allocation['taxes'] = self::money(($totals['taxes'][$key] ?? 0) - ($previous['taxes'][$key] ?? 0));
							$allocation['gross'] = self::money((float)$allocation['net'] + (float)$allocation['taxes']);
							$previous = $totals;
						}
						unset($allocation);
						$freight['tax_allocations'] = $allocations;
						$rates = array_values(array_unique(array_column($allocations, 'taxrate')));
						$freight['taxrate'] = count($rates) === 1 ? (float)$rates[0] : null;
						$freight['tax_basis'] = $decision['rule'];
						$freight['taxes'] = self::money($totals['taxes']['total'] - $goods['taxes']['total']);
						$freight['gross'] = self::money((float)$freight['net'] + (float)$freight['taxes']);
					} catch (DomainException $e) {
						$decision['status'] = 'quote_required';
						$decision['reasons'][] = $e->getMessage();
					}
				} else {
					$decision['status'] = 'quote_required';
					if (!$taxKnown) $decision['reasons'][] = 'unknown_item_tax';
					if ($freight['currency'] !== $currency) $decision['reasons'][] = 'tax_currency_mismatch';
				}
				if ($decision['status'] !== 'calculated') {
					$freight['status'] = 'quote_required';
					$freight['reasons'] = array_values(array_unique(array_merge($freight['reasons'], ['shipping_tax_review_required'], $decision['reasons'])));
					$totals = $goods;
					if ($freight['currency'] === $currency) $totals['subtotal'] = round($goods['subtotal'] + (float)$freight['net'], 2);
				}
			}
			$freight['goods_net'] = number_format($goods['subtotal'], 2, '.', '');
			$freight['label'] = Zend_Registry::get('DEEC_Translate')->t(($freight['destination']['method'] ?? '') === 'pickup' ? 'SHOPS_SHIPPING_PICKUP' : 'SHOPS_SHIPPING_DELIVERY');
			if ($freight['status'] !== 'calculated') $taxKnown = false;
			if (array_intersect($decision['reasons'], ['unknown_item_tax', 'zero_tax_basis_missing'])) $taxKnown = false;
		}
		if (!is_finite($totals['subtotal']) || $totals['subtotal'] > 99999999 || $totals['total'] > 99999999) throw new DomainException('SHOPS_ORDER_TOTAL_MISMATCH');
		$snapshot = [
			'version' => $freight === null ? 1 : 3, 'price_basis' => 'net', 'billing' => $billing, 'shipping' => $shipping,
			'email' => trim((string)($data['email'] ?? '')), 'message' => (string)($data['message'] ?? ''),
			'shop' => array_intersect_key($shop, array_flip(['id', 'clientid', 'title', 'url', 'language', 'timezone'])),
		];
		if ($freight !== null) {
			$snapshot['freight'] = $freight;
			$decision['groups'] = $taxKnown ? self::taxGroups($positions, $goods, $totals, $freight['tax_allocations']) : [];
			$snapshot['tax'] = $decision;
		}
		return [
			'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
			'customername' => $billing['company'] ?: $billing['name'], 'email' => $snapshot['email'], 'currency' => $currency,
			'total' => $totals['subtotal'], 'taxes' => $taxKnown ? $totals['taxes']['total'] : null,
			'grosstotal' => $taxKnown ? $totals['total'] : null, 'status' => 'new',
		];
	}

	public static function decode($json): ?array
	{
		if (!is_string($json) || $json === '') return null;
		$data = json_decode($json, true);
		return is_array($data) && in_array($data['version'] ?? null, [1, 2, 3], true) ? $data : null;
	}

	public static function problems(array $order, array $positions): array
	{
		$snapshot = self::decode($order['snapshot'] ?? null);
		$problems = [];
		if (!$snapshot || ($snapshot['price_basis'] ?? '') !== 'net' || !is_array($snapshot['shop'] ?? null) || !is_string($snapshot['message'] ?? null)) return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
		if ((int)($snapshot['shop']['id'] ?? 0) !== (int)$order['shopid'] || (int)($snapshot['shop']['clientid'] ?? 0) !== (int)$order['clientid']) return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
		if ($snapshot['version'] === 2) {
			$freight = $snapshot['freight'] ?? [];
			if (!is_array($freight) || ($freight['version'] ?? null) !== 1 || (int)($freight['clientid'] ?? 0) !== (int)$order['clientid']
				|| (int)($freight['shopid'] ?? 0) !== (int)$order['shopid'] || !is_string($freight['label'] ?? null)) return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
			$legacy = ($freight['legacy'] ?? false) === true && ($freight['status'] ?? '') === 'quote_required' && ($freight['net'] ?? null) === null;
			if (!$legacy && (($freight['status'] ?? '') !== 'calculated' || ($freight['currency'] ?? '') !== ($order['currency'] ?? '')
				|| !in_array($freight['destination']['method'] ?? '', ['delivery', 'pickup'], true)
				|| ($freight['destination']['country'] ?? '') !== ($snapshot['shipping']['country'] ?? '')
				|| !isset($freight['net'], $freight['taxes'], $freight['gross'], $freight['taxrate'])
				|| !is_numeric($freight['net']) || !is_finite((float)$freight['net']) || (float)$freight['net'] < 0
				|| !is_numeric($freight['taxrate']) || !is_finite((float)$freight['taxrate']) || (float)$freight['taxrate'] < 0 || (float)$freight['taxrate'] > 100
				|| !is_numeric($freight['taxes']) || !is_finite((float)$freight['taxes']) || !is_numeric($freight['gross']) || !is_finite((float)$freight['gross'])
				|| abs(round((float)$freight['net'] + (float)$freight['taxes'], 2) - (float)$freight['gross']) > 0.0001)) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
		}
		foreach (['billing', 'shipping'] as $type) {
			if (!is_array($snapshot[$type] ?? null)) { $problems[] = 'SHOPS_ORDER_ADDRESS_INCOMPLETE'; continue; }
			foreach (['name', 'street', 'postcode', 'city', 'country'] as $field) {
				if (!is_scalar($snapshot[$type][$field] ?? null) || trim((string)$snapshot[$type][$field]) === '') $problems[] = 'SHOPS_ORDER_ADDRESS_INCOMPLETE';
			}
			foreach (['company', 'department', 'phone'] as $field) if (isset($snapshot[$type][$field]) && !is_scalar($snapshot[$type][$field])) $problems[] = 'SHOPS_ORDER_ADDRESS_INCOMPLETE';
		}
		if (!filter_var($snapshot['email'] ?? '', FILTER_VALIDATE_EMAIL)) $problems[] = 'SHOPS_ORDER_EMAIL_INVALID';
		if (($order['currency'] ?? '') !== 'EUR') $problems[] = 'SHOPS_ORDER_CURRENCY_INVALID';
		if (!isset($order['taxes'], $order['grosstotal']) || !is_numeric($order['taxes']) || !is_numeric($order['grosstotal'])) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
		$date = DateTime::createFromFormat('!Y-m-d', (string)($order['orderdate'] ?? ''));
		if (!$date || $date->format('Y-m-d') !== ($order['orderdate'] ?? '') || !$positions) $problems[] = 'SHOPS_ORDER_POSITIONS_INCOMPLETE';
		foreach ($positions as $position) {
			$row = self::decode($position['snapshot'] ?? null);
			if (!$row || !is_scalar($row['sku'] ?? null) || trim((string)$row['sku']) === '' || !is_scalar($row['title'] ?? null) || trim((string)$row['title']) === ''
				|| (isset($row['description']) && !is_string($row['description'])) || (isset($row['uom']) && !is_string($row['uom']))
				|| ($row['currency'] ?? null) !== ($order['currency'] ?? null)
				|| !isset($position['shoporderid']) || (int)$position['shoporderid'] !== (int)$order['id']
				|| !is_numeric($position['quantity']) || !is_finite((float)$position['quantity']) || (float)$position['quantity'] <= 0
				|| !is_numeric($position['price']) || !is_finite((float)$position['price']) || (float)$position['price'] < 0
				|| !is_numeric($position['total']) || !is_finite((float)$position['total'])
				|| abs(round((float)$position['price'] * (float)$position['quantity'], 2) - (float)$position['total']) > 0.0001) $problems[] = 'SHOPS_ORDER_POSITIONS_INCOMPLETE';
			if (!isset($row['taxrate']) || !is_numeric($row['taxrate']) || !is_finite((float)$row['taxrate']) || (float)$row['taxrate'] < 0 || (float)$row['taxrate'] > 100) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
		}
		if (!$problems && $snapshot['version'] === 3) {
			$freight = $snapshot['freight'] ?? null;
			if (!is_array($freight) || ($freight['version'] ?? null) !== 1 || !is_string($freight['label'] ?? null)
				|| (int)($freight['clientid'] ?? 0) !== (int)$order['clientid'] || (int)($freight['shopid'] ?? 0) !== (int)$order['shopid']) return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
			if (($freight['status'] ?? '') !== 'calculated' || !empty($freight['legacy']) || ($freight['currency'] ?? '') !== $order['currency']
				|| !in_array($freight['destination']['method'] ?? '', ['delivery', 'pickup'], true)
				|| ($freight['destination']['country'] ?? '') !== $snapshot['shipping']['country']) return ['SHOPS_ORDER_TAX_UNKNOWN'];
			$data = ['differentshippingaddress' => 1, 'email' => $snapshot['email'], 'message' => $snapshot['message']];
			foreach (['billing', 'shipping'] as $prefix) foreach ($snapshot[$prefix] as $field => $value) $data[$prefix . $field] = $value;
			try {
				// Reproduce only stored inputs with the ERP calculator, never current tariffs or item data.
				$rebuilt = self::build($snapshot['shop'], $data, $positions, $freight);
				$expected = self::decode($rebuilt['snapshot']);
				if (($snapshot['tax'] ?? null) !== $expected['tax']) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
				foreach (['tax_allocations', 'taxrate', 'taxes', 'gross', 'tax_basis', 'goods_net'] as $field) if (($freight[$field] ?? null) !== ($expected['freight'][$field] ?? null)) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
				foreach (['total', 'taxes', 'grosstotal'] as $field) if (!isset($rebuilt[$field]) || abs((float)$rebuilt[$field] - (float)$order[$field]) > 0.0001) $problems[] = 'SHOPS_ORDER_TOTAL_MISMATCH';
			} catch (Throwable $e) { $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN'; }
		}
		return array_values(array_unique($problems));
	}
}
