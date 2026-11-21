<?php
// Run through shop-checkout-db.py; all rows belong to its disposable MariaDB instance.
if (PHP_SAPI !== 'cli' || !preg_match('~^/tmp/dewawi-shop-checkout-db-[^/]+/mysql.sock$~D', $argv[1] ?? '')) exit(1);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2); define('BASE_PATH', $root);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class); if (count($parts) < 3) return;
	$module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php'; if (is_file($path)) require_once $path;
});
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_orders_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('Client', ['id' => 7]); Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 1]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE')); Zend_Registry::set('Config', ['language' => 'de_DE']);
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
$tr = new DEEC_Translate('de_DE'); $tr->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $tr);
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
Zend_Session::setOptions(['save_path' => dirname($argv[1]) . '/order-sessions', 'use_cookies' => false]);
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
$checks = 0;
function check(bool $value, string $message): void { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
function deny(callable $fn): void { try { $fn(); } catch (DomainException $e) { check(in_array($e->getCode(), [403,404], true), 'Unexpected denial'); return; } throw new RuntimeException('Expected denial'); }
$db->insert('client', ['id' => 7, 'country' => 'DE']);
$shop = ['id' => 141, 'clientid' => 7, 'title' => 'Shipping checkout fixture', 'checkoutenabled' => 1]; $db->insert('shop', $shop);
$service = new Shops_Service_ShippingConfiguration();
$save = static function(string $kind, array $values, int $id = 0) use ($service): int {
	$result = $service->save(141, $kind, $id, $values); check(!$result['errors'], json_encode($result)); return $result['id'];
};
$save('settings', ['currency' => 'EUR', 'pickup' => 1]);
$parcel = $save('classes', ['title' => 'Parcel', 'active' => 1, 'combine_same_class' => 1, 'require_packweight' => 1]);
$pallet = $save('classes', ['title' => 'Pallet', 'active' => 1, 'combine_same_class' => 1, 'require_packweight' => 1]);
$zone = $save('zones', ['title' => 'Domestic', 'active' => 1, 'countries' => ['DE']]);
$tariffValues = ['title' => 'Parcel', 'active' => 1, 'classid' => $parcel, 'zoneid' => $zone, 'currency' => 'EUR', 'model' => 'fixed', 'amount' => '12.00'];
$tariff = $save('tariffs', $tariffValues);
$save('tariffs', ['title' => 'Pallet', 'active' => 1, 'classid' => $pallet, 'zoneid' => $zone, 'currency' => 'EUR', 'model' => 'additional_packages', 'base' => '120', 'additional' => '90']);
foreach ([301 => $parcel, 302 => $pallet] as $id => $class) {
	$db->insert('item', ['id' => $id, 'clientid' => 7, 'shopid' => 141, 'catid' => 0, 'shopenabled' => 1, 'sku' => 'SHIP-' . $id, 'title' => 'Shipping item', 'price' => '100', 'currency' => 'EUR', 'packweight' => '5']);
	$save('assignments', ['itemid' => $id, 'classid' => $class, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0]);
}
$combination = $save('combinations', ['hostclassid' => $pallet, 'guestclassid' => $parcel, 'active' => 1, 'priority' => 1, 'max_guest_packages' => 3, 'max_guest_weight' => 20]);
$context = static function(int $id = 141, int $owner = 7, array $features = ['cart', 'checkout']): void {
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => $id, 'clientid' => $owner], [], $features));
};
$context(); Zend_Registry::set('User', ['id' => 0]); Zend_Auth::getInstance()->clearIdentity();
$public = new Shops_Service_ShippingConfiguration();
$calculate = static fn(array $lines, array $destination = ['country' => 'DE', 'method' => 'delivery']) => $public->calculateCheckout($lines, $destination);
$one = [['itemid' => 301, 'quantity' => '1']];
$freight = $calculate($one);
check($freight['status'] === 'calculated' && $freight['net'] === '12.00', 'Guest shipping calculation requires admin or misses server tariff');
deny(fn() => $public->calculate(141, $one, ['country' => 'DE']));
deny(fn() => $public->rows(141, 'tariffs'));
check($calculate([['itemid' => 301, 'quantity' => '3']])['net'] === '12.00', 'Same-class grouping changed');
check($calculate([['itemid' => 301, 'quantity' => '1'], ['itemid' => 302, 'quantity' => '2']])['net'] === '210.00', 'Combined parcel and additional pallets differ');
check($calculate($one, ['country' => 'AT'])['status'] === 'unavailable', 'Unsupported country accepted');
check($calculate($one, ['country' => 'DE', 'method' => 'pickup'])['net'] === '0.00', 'Enabled pickup unavailable');
$db->update('shop', ['pickupenabled' => 0], 'id = 141');
check($calculate($one, ['country' => 'DE', 'method' => 'pickup'])['status'] === 'unavailable', 'Revoked pickup accepted');
check($calculate([['itemid' => 301, 'quantity' => 1, 'net' => 0]])['status'] !== 'calculated', 'Browser shipping amount accepted');
check($calculate([['itemid' => 103, 'quantity' => 1]])['status'] === 'unavailable', 'Other shop item accepted');
$context(141, 8); deny(fn() => $calculate($one));
$context(141, 7, ['cart']); deny(fn() => $calculate($one)); $context();
Zend_Registry::set('Client', ['id' => 9, 'modules' => ['shops' => 8]]);
check($calculate($one)['net'] === '12.00', 'Guest shipping trusts ERP registry instead of SiteContext');
Zend_Registry::set('Client', ['id' => 7]);
$db->update('item', ['packweight' => null], 'id = 301');
check($calculate($one)['status'] === 'quote_required', 'Missing packaging became free');
$db->update('item', ['packweight' => 5], 'id = 301');
$db->update('shopitemshipping', ['active' => 0], 'itemid = 301');
check($calculate($one)['status'] === 'quote_required', 'Missing class became free');
$db->update('shopitemshipping', ['active' => 1], 'itemid = 301');
$post = ['billingname' => 'Buyer', 'billingstreet' => 'Street 1', 'billingpostcode' => '10115', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'email' => 'buyer@example.test'];
$positions = [['itemid' => 301, 'price' => 100, 'quantity' => 1, 'total' => 100, 'snapshot' => json_encode(['version' => 1, 'sku' => 'SHIP-301', 'title' => 'Historical shipping item', 'description' => '', 'currency' => 'EUR', 'taxrate' => 7.7, 'uom' => null])]];
$order = Shops_Service_OrderSnapshot::build($shop, $post, $positions, $freight);
$storedFreight = Shops_Service_OrderSnapshot::decode($order['snapshot'])['freight'];
check($order['total'] === 112.0 && $order['taxes'] === 8.62 && $order['grosstotal'] === 120.62, 'Fractional shipping tax differs from ERP');
check($storedFreight['taxrate'] === 7.7 && $storedFreight['taxes'] === '0.92' && $storedFreight['gross'] === '12.92', 'Shipping tax and gross were not snapshotted');
$mixed = $positions; $second = $positions[0]; $row = json_decode($second['snapshot'], true); $row['taxrate'] = 19; $second['snapshot'] = json_encode($row); $mixed[] = $second;
foreach ([[$mixed, $post, $freight], [$positions, array_replace($post, ['billingcountry' => 'AT']), $freight], [$positions, $post, array_replace($freight, ['origin_country' => null])]] as [$rows, $address, $shipping]) {
	$review = Shops_Service_OrderSnapshot::build($shop, $address, $rows, $shipping);
	check($review['grosstotal'] === null && Shops_Service_OrderSnapshot::decode($review['snapshot'])['freight']['status'] === 'quote_required', 'Ambiguous tax permitted binding checkout');
}
$tiny = $positions; $tiny[0]['price'] = $tiny[0]['total'] = 0.03; $row = json_decode($tiny[0]['snapshot'], true); $row['taxrate'] = 19; $tiny[0]['snapshot'] = json_encode($row);
$rounded = Shops_Service_OrderSnapshot::build($shop, $post, $tiny, array_replace($freight, ['net' => '0.03']));
check($rounded['taxes'] === 0.01 && Shops_Service_OrderSnapshot::decode($rounded['snapshot'])['freight']['taxes'] === '0.00', 'Shipping tax rounded separately instead of within ERP tax group');
$free = Shops_Service_OrderSnapshot::build($shop, $post, $mixed, array_replace($freight, ['net' => '0.00']));
check(Shops_Service_OrderSnapshot::decode($free['snapshot'])['freight']['status'] === 'calculated' && $free['grosstotal'] !== null, 'Zero shipping requires an unnecessary mixed-rate allocation');
$zeroRate = $positions; $row = json_decode($zeroRate[0]['snapshot'], true); $row['taxrate'] = 0; $zeroRate[0]['snapshot'] = json_encode($row);
$zeroTax = Shops_Service_OrderSnapshot::build($shop, $post, $zeroRate, $freight);
check($zeroTax['total'] === 112.0 && $zeroTax['taxes'] === 0.0 && $zeroTax['grosstotal'] === 112.0, 'Explicit zero article tax was replaced with a default shipping rate');
$row['taxrate'] = null; $zeroRate[0]['snapshot'] = json_encode($row);
$unknown = Shops_Service_OrderSnapshot::build($shop, $post, $zeroRate, $freight);
check($unknown['total'] === 112.0 && $unknown['grosstotal'] === null, 'Unknown shipping tax invented a total or excluded known freight net');
$db->update('shopshippingzone', ['countries' => '["DE","AT"]'], ['id = ?' => $zone]);
$foreignFreight = $calculate($one, ['country' => 'AT', 'method' => 'delivery']);
$foreignTax = Shops_Service_OrderSnapshot::build($shop, array_replace($post, ['billingcountry' => 'AT']), $positions, $foreignFreight);
check($foreignFreight['status'] === 'calculated' && $foreignTax['grosstotal'] === null && $foreignTax['total'] === 112.0, 'Supported foreign tariff bypassed country tax review');
$db->update('shopshippingzone', ['countries' => '["DE"]'], ['id = ?' => $zone]);
$token = hash('sha256', 'c4b-shipping-order');
$stored = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(141, $token, $order + ['orderdate' => '2026-11-21', 'invoiceid' => 0], $positions)['order'];
$db->update('shopshippingtariff', ['parameters' => '{"amount":"99.00"}'], ['id = ?' => $tariff]);
$changed = $calculate($one);
check($changed['configuration_hash'] !== $freight['configuration_hash'], 'Tariff change does not invalidate review');
Zend_Registry::getInstance()->offsetUnset('SiteContext'); Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
$result = (new Shops_Service_OrderWorkflow())->createSalesorder((int)$stored['id'], 21);
$sales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($result['id']);
$rows = (new Sales_Model_DbTable_Salesorderpos())->setClientId(7)->getPositions($result['id'])->toArray();
check(count($rows) === 2 && $rows[1]['itemid'] === null && $rows[1]['sku'] === null && (float)$rows[1]['price'] === 12.0 && (float)$rows[1]['taxrate'] === 7.7, 'ERP repriced freight or invented a stock item');
check((float)$sales['subtotal'] === 112.0 && (float)$sales['taxes'] === 8.62 && (float)$sales['total'] === 120.62, 'ERP shipping totals differ from saved order');
check(!(new Shops_Service_OrderWorkflow())->createSalesorder((int)$stored['id'], 21)['created'], 'Repeated ERP conversion duplicated shipping');
check((new Shops_Model_DbTable_Order())->setClientId(7)->getById((int)$stored['id'])['snapshot'] === $stored['snapshot'], 'ERP transfer or tariff update mutated snapshot');
$context();
$detail = (new Shops_Model_DbTable_Order())->setClientId(7)->getDetail((int)$stored['id']);
$bad = $detail; $badSnapshot = Shops_Service_OrderSnapshot::decode($bad['snapshot']); $badSnapshot['freight']['shopid'] = 42; $bad['snapshot'] = json_encode($badSnapshot);
check(Shops_Service_OrderSnapshot::problems($bad, $detail['positions']) === ['SHOPS_ORDER_SNAPSHOT_MISSING'], 'Foreign shipping snapshot passed ERP validation');
$view = new Zend_View(); $view->setScriptPath($root . '/application/modules/shops/views/scripts')->addHelperPath($root . '/application/modules/shops/views/helpers', 'Zend_View_Helper');
$html = $view->partial('checkout/_shipping.phtml', ['order' => $stored]);
check(str_contains($html, '12,00') && str_contains($html, '100,00') && !str_contains($html, 'nicht enthalten'), 'ERP shipping summary lost stored amounts or claims missing freight');
$before = (int)$db->fetchOne('SELECT COUNT(*) FROM shoporder');
$other = new PDO('mysql:unix_socket=' . $argv[1] . ';dbname=dewawi_shop_orders_test', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$other->exec('SET SESSION innodb_lock_wait_timeout = 1');
try {
	(new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(141, hash('sha256', 'stale-final-check'), $order, $positions, static function() use ($other): void {
		try { $other->exec('UPDATE shop SET pickupenabled = 1 WHERE id = 141 AND clientid = 7'); throw new RuntimeException('Final verification did not lock the shipping configuration owner'); }
		catch (PDOException $e) { check((int)($e->errorInfo[1] ?? 0) === 1205, 'Unexpected competing writer failure'); }
		throw new DomainException('SHOPS_CHECKOUT_REVIEW_CHANGED');
	});
	throw new RuntimeException('Final verification ignored');
} catch (DomainException $e) { check($e->getMessage() === 'SHOPS_CHECKOUT_REVIEW_CHANGED' && (int)$db->fetchOne('SELECT COUNT(*) FROM shoporder') === $before, 'Final check did not roll back'); }
foreach (['pickup', 'delivery'] as $method) {
	$zero = Shops_Service_OrderSnapshot::build($shop, $post, $positions, array_replace($freight, ['net' => '0.00', 'destination' => ['country' => 'DE', 'method' => $method]]));
	$savedZero = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(141, hash('sha256', 'zero-' . $method), $zero + ['orderdate' => '2026-11-21', 'invoiceid' => 0], $positions)['order'];
	Zend_Registry::getInstance()->offsetUnset('SiteContext');
	$converted = (new Shops_Service_OrderWorkflow())->createSalesorder((int)$savedZero['id'], 21);
	$zeroSales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($converted['id']);
	check((float)$zeroSales['subtotal'] === 100.0 && (float)$zeroSales['taxes'] === 7.7 && (float)$zeroSales['total'] === 107.7, 'Zero shipping ERP conversion changed totals');
	$context();
}
echo "PASS: $checks public shipping, snapshot tax, rounding, isolation, immutable ERP and final transaction checks\n";
