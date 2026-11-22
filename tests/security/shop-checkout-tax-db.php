<?php
// Only the disposable MariaDB instance owned by shop-checkout-db.py is accepted.
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
set_error_handler(static function(int $severity, string $message, string $file, int $line): bool { if (!(error_reporting() & $severity)) return false; throw new ErrorException($message, 0, $severity, $file, $line); });
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_orders_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('Client', ['id' => 7]); Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 1]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE')); Zend_Registry::set('Config', ['language' => 'de_DE']);
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
$translator = new DEEC_Translate('de_DE'); $translator->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $translator);
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
Zend_Session::setOptions(['save_path' => dirname($argv[1]) . '/order-sessions', 'use_cookies' => false]);
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
$checks = 0;
function taxDbCheck(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
$shop = ['id' => 151, 'clientid' => 7, 'checkoutenabled' => 1, 'title' => 'Mixed shipping fixture']; $db->insert('shop', $shop);
$shipping = new Shops_Service_ShippingConfiguration();
$save = static function(string $kind, array $data, int $id = 0) use ($shipping): int {
	$result = $shipping->save(151, $kind, $id, $data); taxDbCheck(!$result['errors'], json_encode($result)); return $result['id'];
};
$save('settings', ['currency' => 'EUR', 'pickup' => 1, 'taxpolicy' => Shops_Service_ShippingConfiguration::TAX_POLICY]);
taxDbCheck($shipping->edit(151, 'settings', 0)['taxpolicy'] === Shops_Service_ShippingConfiguration::TAX_POLICY, 'Tax approval not persisted');
$invalid = $shipping->save(151, 'settings', 0, ['currency' => 'EUR', 'pickup' => 1, 'taxpolicy' => ['forged']]);
taxDbCheck(isset($invalid['errors']['taxpolicy']), 'Malformed tax policy accepted');
$parcel = $save('classes', ['title' => 'Parcel', 'active' => 1, 'combine_same_class' => 1]);
$pallet = $save('classes', ['title' => 'Pallet', 'active' => 1, 'combine_same_class' => 1]);
$db->insert('country', ['clientid' => 7, 'code' => 'AT', 'name' => 'Austria', 'language' => 'de_DE']);
$zone = $save('zones', ['title' => 'Countries', 'active' => 1, 'countries' => ['DE', 'AT']]);
$tariff = $save('tariffs', ['title' => 'Parcel', 'classid' => $parcel, 'zoneid' => $zone, 'currency' => 'EUR', 'active' => 1, 'model' => 'fixed', 'amount' => '12']);
$save('tariffs', ['title' => 'Pallet', 'classid' => $pallet, 'zoneid' => $zone, 'currency' => 'EUR', 'active' => 1, 'model' => 'fixed', 'amount' => '120']);
foreach ([901 => 19, 902 => 7] as $id => $rate) $db->insert('taxrate', ['id' => $id, 'clientid' => 7, 'name' => 'Fixture tax', 'rate' => $rate]);
$assignments = [];
foreach ([501 => 901, 502 => 902] as $id => $tax) {
	$db->insert('item', ['id' => $id, 'clientid' => 7, 'shopid' => 151, 'catid' => 0, 'shopenabled' => 1, 'title' => 'Tax fixture ' . $id, 'sku' => 'TAX-' . $id,
		'price' => '500', 'specialprice' => '100', 'currency' => 'EUR', 'taxid' => $tax, 'packweight' => 5]);
	$assignments[$id] = $save('assignments', ['itemid' => $id, 'classid' => $parcel, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0]);
}
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop, [], ['cart', 'checkout']));
$post = ['billingname' => 'Buyer', 'billingstreet' => 'Street 1', 'billingpostcode' => '10115', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'email' => 'buyer@example.invalid'];
$positions = static function(array $quantities = [501 => 1, 502 => 1]) use ($db): array {
	$result = [];
	foreach ($quantities as $id => $quantity) {
		$item = (new Shops_Model_DbTable_Item())->getCartItem($id);
		$rate = $db->fetchOne($db->select()->from('taxrate', ['rate'])->where('id = ?', $item['taxid'])->where('clientid = 7'));
		$price = Shops_Model_ShoppingCart::itemPrice($item);
		$result[] = ['itemid' => $id, 'price' => $price, 'quantity' => $quantity, 'total' => round($price * $quantity, 2), 'snapshot' => json_encode([
			'version' => 1, 'sku' => $item['sku'], 'title' => $item['title'], 'description' => '', 'uom' => null, 'currency' => 'EUR', 'taxrate' => $rate === false ? null : (float)$rate])];
	}
	return $result;
};
$calculate = static function(array $rows, string $country = 'DE', string $method = 'delivery') use ($shipping, $shop, $post): array {
	$selection = array_map(static fn(array $row): array => ['itemid' => $row['itemid'], 'quantity' => $row['quantity']], $rows);
	$freight = $shipping->calculateCheckout($selection, ['country' => $country, 'method' => $method]);
	return Shops_Service_OrderSnapshot::build($shop, array_replace($post, ['billingcountry' => $country]), $rows, $freight);
};
$rows = $positions(); $shared = $calculate($rows); $snapshot = Shops_Service_OrderSnapshot::decode($shared['snapshot']);
$single = $calculate($positions([501 => 1]));
taxDbCheck($shared['total'] === 212.0 && $shared['taxes'] === 27.56 && $shared['grosstotal'] === 239.56, 'Actual shared shipping group or special prices differ');
taxDbCheck(array_column($snapshot['freight']['tax_allocations'], 'net') === ['6.00', '6.00'], 'Shared group was not allocated');
$hash = $snapshot['freight']['configuration_hash'];
$save('settings', ['currency' => 'EUR', 'pickup' => 1, 'taxpolicy' => '']);
taxDbCheck($calculate($rows)['grosstotal'] === null, 'Mixed tax without approved dispatch/ancillary context accepted');
taxDbCheck(Shops_Service_OrderSnapshot::decode($calculate($rows)['snapshot'])['freight']['configuration_hash'] !== $hash, 'Tax approval change does not invalidate review');
$save('settings', ['currency' => 'EUR', 'pickup' => 1, 'taxpolicy' => Shops_Service_ShippingConfiguration::TAX_POLICY]);
$save('assignments', ['itemid' => 502, 'classid' => $pallet, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0], $assignments[502]);
$separate = $calculate($rows);
taxDbCheck($separate['total'] === 332.0 && $separate['taxes'] === 36.68 && $separate['grosstotal'] === 368.68, 'Freight leaked to a different shipment group');
$stored = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(151, hash('sha256', 'c5b-separate'), $separate + ['orderdate' => '2026-11-22', 'invoiceid' => 0], $rows)['order'];
$save('combinations', ['hostclassid' => $pallet, 'guestclassid' => $parcel, 'active' => 1, 'priority' => 1, 'max_guest_packages' => 3, 'max_guest_weight' => 20]);
$combined = $calculate($positions([501 => 2, 502 => 1]));
taxDbCheck($combined['total'] === 420.0 && $combined['taxes'] === 63.0 && $combined['grosstotal'] === 483.0, 'Actual combined pallet/parcel members or quantities differ');
$foreign = $calculate($rows, 'AT');
taxDbCheck($foreign['total'] === 320.0 && $foreign['grosstotal'] === null, 'Foreign tariff invented tax rules or hid known freight');
$collection = $calculate($rows, 'DE', 'pickup');
taxDbCheck($collection['total'] === 200.0 && $collection['taxes'] === 26.0 && $collection['grosstotal'] === 226.0, 'Mixed-rate pickup broke goods taxes');
$db->update('taxrate', ['rate' => 0], 'id = 902');
taxDbCheck($calculate($positions())['grosstotal'] === null, 'Unverified zero tax treated as exemption');
$db->update('taxrate', ['rate' => 7], 'id = 902');

// Historical conversion must use stored amounts after tariff, price, tax and policy changes.
$db->update('item', ['specialprice' => 999], 'id IN (501, 502)');
$db->update('taxrate', ['rate' => 15], 'id = 902');
$db->update('shopshippingtariff', ['parameters' => '{"amount":"99"}'], ['id = ?' => $tariff]);
$save('settings', ['currency' => 'EUR', 'pickup' => 0, 'taxpolicy' => '']);
Zend_Registry::getInstance()->offsetUnset('SiteContext');
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
$workflow = new Shops_Service_OrderWorkflow();
$detail = $workflow->detail((int)$stored['id']);
taxDbCheck(!Shops_Service_OrderSnapshot::problems($detail, $detail['positions']), 'Stored version 3 did not reproduce from immutable inputs');
$beforeOrder = $db->fetchRow('SELECT * FROM shoporder WHERE id = ' . (int)$stored['id']);
$bad = $detail; $badSnapshot = Shops_Service_OrderSnapshot::decode($bad['snapshot']);
$badSnapshot['tax']['groups'][0]['taxes'] = '15.41'; $badSnapshot['tax']['groups'][1]['taxes'] = '21.27'; $bad['snapshot'] = json_encode($badSnapshot);
taxDbCheck(Shops_Service_OrderSnapshot::problems($bad, $bad['positions']) !== [], 'Offsetting tax-group corruption passed stored validation');
foreach ([['legacy' => true], ['currency' => 'USD'], ['destination' => ['method' => 'delivery', 'country' => 'AT']], ['destination' => ['country' => 'DE']]] as $changes) {
	$bad = $detail; $badSnapshot = Shops_Service_OrderSnapshot::decode($bad['snapshot']);
	$badSnapshot['freight'] = array_replace($badSnapshot['freight'], $changes); $bad['snapshot'] = json_encode($badSnapshot);
	taxDbCheck(Shops_Service_OrderSnapshot::problems($bad, $bad['positions']) !== [], 'Version 3 accepted a legacy bypass or invalid freight context');
}

class TaxMismatchCalculate extends Application_Controller_Action_Helper_Calculate {
	public function getName() { return 'Calculate'; }
	public function direct(int $id, string $module, string $controller, ?int $clientId = null) {
		$result = parent::direct($id, $module, $controller, $clientId);
		$result['row']['taxes'][7] += .01; $result['row']['taxes'][19] -= .01;
		return $result;
	}
}
$original = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate');
Zend_Controller_Action_HelperBroker::removeHelper('Calculate'); Zend_Controller_Action_HelperBroker::addHelper(new TaxMismatchCalculate());
$before = [];
foreach (['salesorder', 'salesorderpos', 'increment', 'shoporderhistory'] as $table) $before[$table] = $db->fetchAll('SELECT * FROM ' . $table);
try { $workflow->createSalesorder((int)$stored['id'], 21); throw new RuntimeException('ERP tax-group mismatch accepted'); }
catch (DomainException $e) { taxDbCheck($e->getMessage() === 'SHOPS_ORDER_TOTAL_MISMATCH', 'Unexpected ERP rollback reason'); }
foreach ($before as $table => $values) taxDbCheck($db->fetchAll('SELECT * FROM ' . $table) === $values, 'ERP tax mismatch left partial ' . $table);
taxDbCheck($db->fetchRow('SELECT * FROM shoporder WHERE id = ' . (int)$stored['id']) === $beforeOrder, 'Mismatch changed order or snapshot');
Zend_Controller_Action_HelperBroker::removeHelper('Calculate'); Zend_Controller_Action_HelperBroker::addHelper($original);
$result = $workflow->createSalesorder((int)$stored['id'], 21);
$sales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($result['id']);
$converted = (new Sales_Model_DbTable_Salesorderpos())->setClientId(7)->getPositions($result['id'])->toArray();
taxDbCheck(count($converted) === 4 && (float)$sales['subtotal'] === 332.0 && (float)$sales['taxes'] === 36.68 && (float)$sales['total'] === 368.68, 'ERP did not reproduce immutable split freight');
$freightRows = array_values(array_filter($converted, static fn(array $row): bool => $row['itemid'] === null));
taxDbCheck(count($freightRows) === 2 && (float)$freightRows[0]['price'] === 12.0 && (float)$freightRows[0]['taxrate'] === 19.0 && (float)$freightRows[1]['price'] === 120.0 && (float)$freightRows[1]['taxrate'] === 7.0, 'ERP invented a SKU or lost per-shipment rates');
taxDbCheck($freightRows[0]['sku'] === null && $freightRows[1]['sku'] === null, 'Freight got an invented item number');
taxDbCheck($workflow->createSalesorder((int)$stored['id'], 21)['created'] === false, 'Version 3 ERP replay duplicated freight');
taxDbCheck($db->fetchOne('SELECT snapshot FROM shoporder WHERE id = ' . (int)$stored['id']) === $stored['snapshot'], 'Conversion mutated stored tax decision');

// A historical version-2 zero-rate snapshot remains readable and transferable as originally stored.
$oldRow = $rows[0]; $oldItem = json_decode($oldRow['snapshot'], true); $oldItem['taxrate'] = 0; $oldRow['snapshot'] = json_encode($oldItem);
$oldSnapshot = Shops_Service_OrderSnapshot::decode($single['snapshot']); $oldSnapshot['version'] = 2; unset($oldSnapshot['tax']);
$oldSnapshot['freight'] = array_replace($oldSnapshot['freight'], ['taxrate' => 0, 'taxes' => '0.00', 'gross' => '12.00', 'goods_net' => '100.00', 'tax_basis' => 'single_rate_domestic']);
unset($oldSnapshot['freight']['tax_allocations']);
$oldData = array_replace($single, ['snapshot' => json_encode($oldSnapshot), 'total' => 112, 'taxes' => 0, 'grosstotal' => 112, 'orderdate' => '2026-11-21', 'invoiceid' => 0]);
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop));
$historical = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(151, hash('sha256', 'c5b-history-v2'), $oldData, [$oldRow])['order'];
Zend_Registry::getInstance()->offsetUnset('SiteContext');
$oldConverted = $workflow->createSalesorder((int)$historical['id'], 21);
$oldSales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($oldConverted['id']);
taxDbCheck((float)$oldSales['subtotal'] === 112.0 && (float)$oldSales['taxes'] === 0.0 && (float)$oldSales['total'] === 112.0, 'Historical zero-rate version 2 was reinterpreted');
taxDbCheck($db->fetchOne('SELECT snapshot FROM shoporder WHERE id = ' . (int)$historical['id']) === $historical['snapshot'], 'Historical snapshot changed');
$legacySnapshot = Shops_Service_OrderSnapshot::decode($single['snapshot']); $legacySnapshot['version'] = 2; unset($legacySnapshot['tax']);
$legacySnapshot['freight'] = ['version' => 1, 'clientid' => 7, 'shopid' => 151, 'status' => 'quote_required', 'legacy' => true, 'net' => null, 'currency' => null,
	'goods_net' => '100.00', 'label' => 'Historical delivery', 'taxrate' => null, 'taxes' => null, 'gross' => null, 'groups' => [], 'reasons' => []];
$legacyData = array_replace($single, ['snapshot' => json_encode($legacySnapshot), 'total' => 100, 'taxes' => 19, 'grosstotal' => 119, 'orderdate' => '2026-11-21', 'invoiceid' => 0]);
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop));
$legacy = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(151, hash('sha256', 'c5b-history-legacy'), $legacyData, [$rows[0]])['order'];
Zend_Registry::getInstance()->offsetUnset('SiteContext');
$legacyConverted = $workflow->createSalesorder((int)$legacy['id'], 21);
$legacySales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($legacyConverted['id']);
taxDbCheck((float)$legacySales['subtotal'] === 100.0 && (float)$legacySales['taxes'] === 19.0 && (float)$legacySales['total'] === 119.0, 'Historical legacy shipping contract was retroactively blocked');
taxDbCheck($db->fetchOne('SELECT snapshot FROM shoporder WHERE id = ' . (int)$legacy['id']) === $legacy['snapshot'], 'Historical legacy snapshot changed');
echo "PASS: $checks real shipping tax groups, approval, immutable snapshots, ERP equality, tax-group rollback and history checks\n";
