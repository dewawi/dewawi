<?php

class Shops_Service_OrderSnapshot
{
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
			$freight += ['taxrate' => null, 'taxes' => null, 'gross' => null, 'tax_basis' => null];
			if ($freight['status'] === 'calculated') {
				$rates = array_values(array_unique(array_map(static fn($row) => (string)(float)$row->taxrate, $rows)));
				// The existing engine has no mixed-rate ancillary allocation or foreign-country tax policy.
				$domestic = preg_match('/^[A-Z]{2}$/D', (string)($freight['origin_country'] ?? ''))
					&& $freight['origin_country'] === $shipping['country'] && $billing['country'] === $shipping['country'];
				if (!$taxKnown || !$domestic || ((float)$freight['net'] > 0 && count($rates) !== 1) || $freight['currency'] !== $currency) {
					$freight['status'] = 'quote_required';
					$freight['reasons'][] = 'shipping_tax_review_required';
					if ($freight['currency'] === $currency) $totals['subtotal'] = round($goods['subtotal'] + (float)$freight['net'], 2);
				} else {
					$freight['taxrate'] = count($rates) === 1 ? (float)$rates[0] : 0.0;
					$freight['tax_basis'] = (float)$freight['net'] === 0.0 ? 'zero_shipping' : 'single_rate_domestic';
					$rows[] = (object)['id' => count($rows) + 1, 'price' => $freight['net'], 'quantity' => 1, 'taxrate' => $freight['taxrate']];
					$totals = $calculate->calculate(['currency' => $currency, 'taxfree' => 0], $rows, 'shops', 'order', (int)$shop['clientid'], null, false)['row'];
					$freight['taxes'] = number_format(round($totals['taxes']['total'] - $goods['taxes']['total'], 2), 2, '.', '');
					$freight['gross'] = number_format(round((float)$freight['net'] + (float)$freight['taxes'], 2), 2, '.', '');
				}
			}
			$freight['goods_net'] = number_format($goods['subtotal'], 2, '.', '');
			$freight['label'] = Zend_Registry::get('DEEC_Translate')->t(($freight['destination']['method'] ?? '') === 'pickup' ? 'SHOPS_SHIPPING_PICKUP' : 'SHOPS_SHIPPING_DELIVERY');
			if ($freight['status'] !== 'calculated' && empty($freight['legacy'])) $taxKnown = false;
		}
		if (!is_finite($totals['subtotal']) || $totals['subtotal'] > 99999999 || $totals['total'] > 99999999) throw new DomainException('SHOPS_ORDER_TOTAL_MISMATCH');
		$snapshot = [
			'version' => $freight === null ? 1 : 2, 'price_basis' => 'net', 'billing' => $billing, 'shipping' => $shipping,
			'email' => trim((string)($data['email'] ?? '')), 'message' => (string)($data['message'] ?? ''),
			'shop' => array_intersect_key($shop, array_flip(['id', 'clientid', 'title', 'url', 'language', 'timezone'])),
		];
		if ($freight !== null) $snapshot['freight'] = $freight;
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
		return is_array($data) && in_array($data['version'] ?? null, [1, 2], true) ? $data : null;
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
		return array_values(array_unique($problems));
	}
}
