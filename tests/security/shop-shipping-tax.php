<?php
// Synthetic stored inputs and the real ERP Calculate helper; no DB, SMTP or external requests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class); if (count($parts) < 3) return;
	$module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php'; if (is_file($path)) require_once $path;
});
set_error_handler(static function(int $severity, string $message, string $file, int $line): bool { if (!(error_reporting() & $severity)) return false; throw new ErrorException($message, 0, $severity, $file, $line); });
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE')); Zend_Registry::set('Config', ['language' => 'de_DE']);
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
$translator = new DEEC_Translate('de_DE'); $translator->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $translator);
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
$checks = 0;
function taxCheck(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function taxPositions(array $lines): array {
	$positions = [];
	foreach ($lines as $index => [$price, $quantity, $rate]) $positions[] = ['itemid' => $index + 1, 'price' => $price, 'quantity' => $quantity, 'total' => round($price * $quantity, 2),
		'snapshot' => json_encode(['version' => 1, 'sku' => 'SKU-' . ($index + 1), 'title' => 'Fixture', 'description' => '', 'currency' => 'EUR', 'uom' => null, 'taxrate' => $rate])];
	return $positions;
}
function taxFreight(array $positions, array $shipments): array {
	$groups = []; $net = 0;
	foreach ($shipments as $index => [$ids, $price]) {
		$members = [];
		foreach ($ids as $id) $members[] = ['itemid' => $id, 'quantity' => (string)$positions[$id - 1]['quantity']];
		$groups[] = ['key' => 'group:' . $index, 'net' => number_format($price, 2, '.', ''), 'members' => $members, 'combinations' => [], 'shipments' => 1];
		$net += $price;
	}
	return ['version' => 1, 'clientid' => 7, 'shopid' => 41, 'status' => 'calculated', 'net' => number_format($net, 2, '.', ''), 'currency' => 'EUR',
		'origin_country' => 'DE', 'tax_policy' => Shops_Service_ShippingConfiguration::TAX_POLICY, 'destination' => ['country' => 'DE', 'method' => 'delivery'], 'groups' => $groups, 'reasons' => []];
}
$shop = ['id' => 41, 'clientid' => 7];
$post = ['billingname' => 'Synthetic buyer', 'billingstreet' => 'Street 1', 'billingpostcode' => '10115', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'email' => 'buyer@example.invalid'];
$build = static fn(array $positions, array $freight, array $changes = []) => Shops_Service_OrderSnapshot::build($shop, array_replace($post, $changes), $positions, $freight);

// Expected amounts are independent, hand-worked reference values, including ERP group rounding.
$references = [
	'single' => [[[100, 1, 19]], [[[1], 12]], [112, 21.28, 133.28], ['19' => '12.00']],
	'mixed shared parcel' => [[[100, 1, 19], [100, 1, 7]], [[[1, 2], 12]], [212, 27.56, 239.56], ['7' => '6.00', '19' => '6.00']],
	'separate shipments' => [[[100, 1, 19], [100, 1, 7]], [[[1], 10], [[2], 100]], [310, 34.90, 344.90], ['7' => '100.00', '19' => '10.00']],
	'combined pallet and parcel' => [[[100, 2, 19], [100, 1, 7]], [[[1, 2], 120]], [420, 63, 483], ['7' => '40.00', '19' => '80.00']],
	'quantity and discounted net' => [[[25, 3, 19], [25, 1, 7]], [[[1, 2], .03]], [100.03, 16, 116.03], ['7' => '0.01', '19' => '0.02']],
	'tie to lower rate' => [[[.03, 1, 19], [.03, 1, 7]], [[[1, 2], .01]], [.07, .01, .08], ['7' => '0.01', '19' => '0.00']],
	'tiny three cents' => [[[.03, 1, 19], [.03, 1, 7]], [[[1, 2], .03]], [.09, .01, .10], ['7' => '0.02', '19' => '0.01']],
	'free article' => [[[0, 1, 19], [100, 1, 7]], [[[1, 2], 10]], [110, 7.70, 117.70], ['7' => '10.00', '19' => '0.00']],
	'zero value unambiguous rate' => [[[0, 1, 19]], [[[1], 10]], [10, 1.9, 11.9], ['19' => '10.00']],
	'fractional rate' => [[[100, 1, 19], [100, 1, 7.7]], [[[1, 2], 12]], [212, 28.30, 240.30], ['7.7' => '6.00', '19' => '6.00']],
	'large exact ratio' => [[[40000000, 1, 19], [1000000, 1, 7]], [[[1, 2], 40000000]], [81000000, 15152926.83, 96152926.83], ['7' => '975609.76', '19' => '39024390.24']],
];
foreach ($references as $name => [$lines, $groups, $totals, $shares]) {
	$positions = taxPositions($lines); $freight = taxFreight($positions, $groups); $order = $build($positions, $freight);
	$snapshot = Shops_Service_OrderSnapshot::decode($order['snapshot']);
	foreach (['total', 'taxes', 'grosstotal'] as $i => $field) taxCheck(abs((float)$order[$field] - $totals[$i]) < .000001, $name . ': ' . $field . ' ' . json_encode($order));
	$actual = [];
	foreach ($snapshot['freight']['tax_allocations'] as $part) $actual[(string)$part['taxrate']] = $part['net'];
	foreach ($shares as $rate => $value) taxCheck(($actual[$rate] ?? null) === $value, $name . ': allocation ' . $rate);
	taxCheck(abs(array_sum(array_column($snapshot['freight']['tax_allocations'], 'net')) - (float)$freight['net']) < .000001, $name . ': net cents lost');
	taxCheck(abs(array_sum(array_column($snapshot['tax']['groups'], 'taxes')) - (float)$order['taxes']) < .000001, $name . ': tax group sum');
	taxCheck($snapshot['version'] === 3 && $snapshot['tax']['version'] === 1 && Shops_Service_OrderSnapshot::canOrder($order), $name . ': missing version or binding totals');
}
$positions = taxPositions([[100, 1, 19], [100, 1, 7]]);
$freight = taxFreight($positions, [[[1, 2], 12]]);
foreach ([['destination' => ['country' => 'AT', 'method' => 'delivery']], ['destination' => ['country' => 'CH', 'method' => 'delivery']], ['origin_country' => null], ['origin_country' => 'AT'], ['tax_policy' => null], ['tax_policy' => 'unverified']] as $changes) {
	$changed = array_replace($freight, $changes);
	$country = $changed['destination']['country'];
	$order = $build($positions, $changed, ['billingcountry' => $country, 'billingcompany' => 'A company', 'vatin' => 'DE123456789', 'vat_id_verified' => 1, 'taxfree' => 1]);
	taxCheck($order['grosstotal'] === null && !Shops_Service_OrderSnapshot::canOrder($order), 'Unverified country, profile or browser claims permitted binding order');
	taxCheck($order['total'] === 212.0 && Shops_Service_OrderSnapshot::decode($order['snapshot'])['freight']['net'] === '12.00', 'Known freight hidden by tax review');
}
$foreignBilling = $build($positions, $freight, ['billingcountry' => 'AT', 'differentshippingaddress' => 1, 'shippingcountry' => 'DE', 'shippingpostcode' => '10115', 'shippingcity' => 'Berlin']);
taxCheck($foreignBilling['taxes'] === 27.56 && $foreignBilling['grosstotal'] === 239.56, 'Foreign billing changed regular DE delivery taxation');
foreach ([['billingcountry' => 'ZZ'], ['billingpostcode' => '27498'], ['billingpostcode' => '78266'], ['billingpostcode' => '8238'], ['billingcity' => 'Buesingen'], ['billingcity' => 'Freihafen'], ['billingpostcode' => 'unknown']] as $changes) taxCheck($build($positions, $freight, $changes)['grosstotal'] === null, 'Ambiguous or special territory was taxed');
foreach ([null, 0] as $rate) {
	$unknown = taxPositions([[100, 1, $rate]]);
	$order = $build($unknown, taxFreight($unknown, [[[1], 12]]), ['taxfree' => 1, 'vatin' => 'ATU12345678']);
	taxCheck($order['grosstotal'] === null && $order['total'] === 112.0, 'Missing/zero tax treated as proven exemption');
}
$zeros = taxPositions([[0, 1, 19], [0, 1, 7]]);
taxCheck($build($zeros, taxFreight($zeros, [[[1, 2], 10]]))['grosstotal'] === null, 'Zero-value mixed shipment invented a ratio');
taxCheck($build($zeros, taxFreight($zeros, [[[1, 2], 0]]))['grosstotal'] === 0.0, 'Zero-value free shipment requires ratio');
foreach (['delivery', 'pickup'] as $method) {
	$free = array_replace(taxFreight($positions, [[[1, 2], 0]]), ['destination' => ['country' => 'DE', 'method' => $method]]);
	taxCheck($build($positions, $free)['grosstotal'] === 226.0, 'Free freight/collection changed goods taxes');
}
$ambiguous = $freight; $ambiguous['groups'][0]['members'][0]['itemid'] = 999;
taxCheck($build($positions, $ambiguous)['grosstotal'] === null, 'Foreign member accepted');
$ambiguous = $freight; $ambiguous['groups'][0]['net'] = '11.99';
taxCheck($build($positions, $ambiguous)['grosstotal'] === null, 'Freight group total mismatch accepted');
$ambiguous = $freight; $ambiguous['groups'][0]['members'][0]['quantity'] = '2';
taxCheck($build($positions, $ambiguous)['grosstotal'] === null, 'Mismatched shipment quantity accepted');
$legacy = array_replace($freight, ['legacy' => true, 'status' => 'quote_required', 'net' => null, 'currency' => null]);
$legacyOrder = $build($positions, $legacy);
taxCheck($legacyOrder['grosstotal'] === null && $legacyOrder['taxes'] === null, 'New legacy order pretends final totals are known');
taxCheck(!Shops_Service_OrderSnapshot::canOrder($legacyOrder), 'Legacy marker bypasses the new binding-order rule');
taxCheck($legacyOrder['total'] === 200.0 && Shops_Service_OrderSnapshot::decode($legacyOrder['snapshot'])['freight']['net'] === null, 'Legacy inquiry lost known goods value or invented freight');
echo "PASS: $checks shipping tax reference, allocation, rounding, country, zero-rate and tampering checks\n";
