<?php

class Shops_Service_OrderSnapshot
{
	public static function build(array $shop, array $data, array $positions): array
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
		$totals = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate(
			['currency' => $currency ?: 'EUR', 'taxfree' => 0], $rows, 'shops', 'order', (int)$shop['clientid'], null, false
		)['row'];
		$snapshot = [
			'version' => 1, 'price_basis' => 'net', 'billing' => $billing, 'shipping' => $shipping,
			'email' => trim((string)($data['email'] ?? '')), 'message' => (string)($data['message'] ?? ''),
			'shop' => array_intersect_key($shop, array_flip(['id', 'clientid', 'title', 'url', 'language', 'timezone'])),
		];
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
		return is_array($data) && ($data['version'] ?? null) === 1 ? $data : null;
	}

	public static function problems(array $order, array $positions): array
	{
		$snapshot = self::decode($order['snapshot'] ?? null);
		$problems = [];
		if (!$snapshot || ($snapshot['price_basis'] ?? '') !== 'net') return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
		if ((int)($snapshot['shop']['id'] ?? 0) !== (int)$order['shopid'] || (int)($snapshot['shop']['clientid'] ?? 0) !== (int)$order['clientid']) return ['SHOPS_ORDER_SNAPSHOT_MISSING'];
		foreach (['billing', 'shipping'] as $type) {
			if (!is_array($snapshot[$type] ?? null)) { $problems[] = 'SHOPS_ORDER_ADDRESS_INCOMPLETE'; continue; }
			foreach (['name', 'street', 'postcode', 'city', 'country'] as $field) {
				if (trim((string)($snapshot[$type][$field] ?? '')) === '') $problems[] = 'SHOPS_ORDER_ADDRESS_INCOMPLETE';
			}
		}
		if (!filter_var($snapshot['email'] ?? '', FILTER_VALIDATE_EMAIL)) $problems[] = 'SHOPS_ORDER_EMAIL_INVALID';
		if (($order['currency'] ?? '') !== 'EUR') $problems[] = 'SHOPS_ORDER_CURRENCY_INVALID';
		if (!isset($order['taxes'], $order['grosstotal']) || !is_numeric($order['taxes']) || !is_numeric($order['grosstotal'])) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
		$date = DateTime::createFromFormat('!Y-m-d', (string)($order['orderdate'] ?? ''));
		if (!$date || $date->format('Y-m-d') !== ($order['orderdate'] ?? '') || !$positions) $problems[] = 'SHOPS_ORDER_POSITIONS_INCOMPLETE';
		foreach ($positions as $position) {
			$row = self::decode($position['snapshot'] ?? null);
			if (!$row || trim((string)($row['sku'] ?? '')) === '' || trim((string)($row['title'] ?? '')) === ''
				|| ($row['currency'] ?? null) !== ($order['currency'] ?? null)
				|| !isset($position['shoporderid']) || (int)$position['shoporderid'] !== (int)$order['id']
				|| !is_numeric($position['quantity']) || !is_finite((float)$position['quantity']) || (float)$position['quantity'] <= 0
				|| !is_numeric($position['price']) || !is_finite((float)$position['price']) || (float)$position['price'] < 0
				|| abs(round((float)$position['price'] * (float)$position['quantity'], 2) - (float)$position['total']) > 0.0001) $problems[] = 'SHOPS_ORDER_POSITIONS_INCOMPLETE';
			if (!isset($row['taxrate']) || !is_numeric($row['taxrate']) || !is_finite((float)$row['taxrate']) || (float)$row['taxrate'] < 0 || (float)$row['taxrate'] > 100) $problems[] = 'SHOPS_ORDER_TAX_UNKNOWN';
		}
		return array_values(array_unique($problems));
	}
}
