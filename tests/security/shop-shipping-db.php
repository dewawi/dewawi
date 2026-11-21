<?php
if (PHP_SAPI !== 'cli' || !preg_match('~^/tmp/dewawi-migrations-shipping-[^/]+/mysql.sock$~D', $argv[1] ?? '')) exit(1);
$root = dirname(__DIR__, 2); define('BASE_PATH', $root); define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class); if (count($parts) < 3) return;
    $module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php'; if (is_file($path)) require_once $path;
});
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity, string $message, string $file, int $line): bool { if (!(error_reporting() & $severity)) return false; throw new ErrorException($message, 0, $severity, $file, $line); });
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'shipping_upgrade', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
mkdir(dirname($argv[1]) . '/sessions'); Zend_Session::setOptions(['save_path' => dirname($argv[1]) . '/sessions', 'use_cookies' => false]);
Zend_Registry::set('Client', ['id' => 7]); Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 1]);
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
$checks = 0;
function check(bool $value, string $message): void { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
function rejected(callable $fn, int $code): void { try { $fn(); } catch (DomainException $e) { check($e->getCode() === $code, 'Unexpected denial'); return; } throw new RuntimeException('Expected denial'); }
foreach ([7,8,9] as $owner) {
    $db->insert('currency', ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'clientid' => $owner]);
    $db->insert('country', ['code' => 'DE', 'name' => 'Deutschland', 'language' => 'de_DE', 'clientid' => $owner]);
    $db->insert('country', ['code' => 'AT', 'name' => 'Austria', 'language' => 'de_DE', 'clientid' => $owner]);
}
foreach ([41 => 7,42 => 7,43 => 8] as $id => $owner) $db->insert('shop', ['id' => $id, 'clientid' => $owner, 'title' => 'Shop ' . $id]);
foreach ([101 => 41,102 => 41,103 => 42,104 => 43] as $id => $shop) $db->insert('item', ['id' => $id, 'shopid' => $shop, 'clientid' => $id === 104 ? 8 : 7, 'catid' => 0, 'shopenabled' => 1, 'price' => '100', 'currency' => 'EUR', 'packweight' => '5', 'packlength' => '50', 'packwidth' => '20', 'packheight' => '20']);
$db->insert('permission', ['userid' => 42, 'clientid' => 7, 'items' => json_encode(['item' => ['view','edit']])]);
$db->insert('permission', ['userid' => 44, 'clientid' => 7, 'items' => json_encode(['item' => ['view']])]);
$db->insert('currency', ['code' => 'JPY', 'name' => 'Yen', 'symbol' => 'Y', 'clientid' => 7]);
$service = new Shops_Service_ShippingConfiguration();
function saved(string $kind, array $data, int $id = 0, int $shopId = 41): int {
    $result = (new Shops_Service_ShippingConfiguration())->save($shopId, $kind, $id, $data);
    check(!$result['errors'], $kind . ': ' . json_encode($result)); return $result['id'];
}
function invalid(string $kind, array $data, string $field, int $id = 0): void {
    $result = (new Shops_Service_ShippingConfiguration())->save(41, $kind, $id, $data);
    check(isset($result['errors'][$field]), 'Expected field error ' . $field . ': ' . json_encode($result));
}
saved('settings', ['currency' => 'EUR', 'pickup' => 0]);
invalid('settings', ['currency' => 'JPY', 'pickup' => 0], 'currency');
$class = ['title' => 'Parcel', 'active' => 1, 'combine_same_class' => 1, 'require_packweight' => 1, 'require_packlength' => 1];
$parcel = saved('classes', $class);
$pallet = saved('classes', array_replace($class, ['title' => 'Pallet']));
$zoneData = ['title' => 'Germany', 'countries' => ['DE'], 'active' => 1]; $zone = saved('zones', $zoneData);
$parcelTariff = ['title' => 'Parcel fee', 'active' => 1, 'classid' => $parcel, 'zoneid' => $zone, 'currency' => 'EUR', 'model' => 'fixed', 'amount' => '12'];
$tariff1 = saved('tariffs', $parcelTariff);
$palletTariff = array_replace($parcelTariff, ['title' => 'Pallet fee', 'classid' => $pallet, 'model' => 'additional_packages']); unset($palletTariff['amount']); $palletTariff += ['base' => '120', 'additional' => '90'];
$tariff2 = saved('tariffs', $palletTariff);
$assignment = ['itemid' => 101, 'classid' => $parcel, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0]; $assignment1 = saved('assignments', $assignment);
$assignment2 = saved('assignments', array_replace($assignment, ['itemid' => 102, 'classid' => $pallet]));
$combination = ['hostclassid' => $pallet, 'guestclassid' => $parcel, 'priority' => 1, 'max_guest_packages' => 3, 'max_guest_weight' => '20', 'max_packlength' => '60', 'active' => 1];
$combinationId = saved('combinations', $combination);
$lines = [['itemid' => 101, 'quantity' => '1'], ['itemid' => 102, 'quantity' => '1']];
check($service->calculate(41, $lines, ['country' => 'DE'])['net'] === '120.00', 'Persisted combination');
check($service->calculate(41, [['itemid' => 102, 'quantity' => '2']], ['country' => 'DE'])['net'] === '210.00', 'Persisted additional pallet');
invalid('zones', $zoneData, 'countries');
invalid('classes', array_replace($class, ['active' => 0]), 'active', $parcel);
invalid('tariffs', array_replace($parcelTariff, ['amount' => '']), 'amount', $tariff1);
invalid('tariffs', array_replace($parcelTariff, ['amount' => '-1']), 'amount', $tariff1);
invalid('tariffs', array_replace($parcelTariff, ['min_weight' => '10', 'max_weight' => '5']), 'max_weight', $tariff1);
invalid('tariffs', array_replace($parcelTariff, ['model' => 'weight', 'tiers' => [['up_to' => '5', 'amount' => '10'], ['up_to' => '5', 'amount' => '20']]]), 'tiers', $tariff1);
invalid('combinations', array_replace($combination, ['hostclassid' => $parcel, 'guestclassid' => $pallet, 'priority' => 2]), 'hostclassid');
invalid('assignments', array_replace($assignment, ['itemid' => 103]), 'itemid');
invalid('assignments', array_replace($assignment, ['clientid' => 8]), 'active');
check($service->calculate(41, $lines, ['country' => 'DE'])['net'] === '120.00', 'Rejected edits changed active rates');
check($service->calculate(41, $lines, ['country' => 'US'])['status'] === 'unavailable', 'Unsupported country');
check($service->calculate(42, [['itemid' => 103, 'quantity' => '1']], ['country' => 'DE'])['net'] === null, 'Unconfigured shop became free shipping');
saved('combinations', array_replace($combination, ['active' => 0]), $combinationId);
check($service->calculate(41, $lines, ['country' => 'DE'])['net'] === '132.00', 'Disabled combination still used');
saved('tariffs', array_replace($parcelTariff, ['amount' => '0']), $tariff1);
check($service->calculate(41, [['itemid' => 101,'quantity' => '1']], ['country' => 'DE'])['net'] === '0.00', 'Explicit free shipping');
saved('tariffs', array_replace($parcelTariff, ['model' => 'manual']), $tariff1);
check($service->calculate(41, $lines, ['country' => 'DE'])['status'] === 'quote_required', 'Manual freight');
saved('tariffs', $parcelTariff, $tariff1);
$db->update('item', ['packweight' => null], 'id = 101');
check($service->calculate(41, $lines, ['country' => 'DE'])['net'] === null, 'Missing packaging weight');
$db->update('item', ['packweight' => '5'], 'id = 101');
$db->update('item', ['shopid' => 42], 'id = 101');
check($service->calculate(41, [['itemid' => 101, 'quantity' => '1']], ['country' => 'DE'])['status'] === 'unavailable', 'Moved item remains available in old shop');
$db->update('item', ['shopid' => 41], 'id = 101');
// Every implemented model survives persistence and uses the same calculator.
$weightTariff = array_replace($parcelTariff, ['model' => 'weight', 'tiers' => [['up_to' => '5', 'amount' => '10'], ['up_to' => '10', 'amount' => '18']]]);
saved('tariffs', $weightTariff, $tariff1);
check($service->calculate(41, [['itemid' => 101, 'quantity' => '1']], ['country' => 'DE'])['net'] === '10.00', 'Persisted weight tier');
check($service->calculate(41, [['itemid' => 101, 'quantity' => '2']], ['country' => 'DE'])['net'] === '18.00', 'Persisted next weight tier');
saved('tariffs', array_replace($parcelTariff, ['model' => 'percentage', 'percent' => '2.5']), $tariff1);
check($service->calculate(41, [['itemid' => 101, 'quantity' => '1']], ['country' => 'DE'])['net'] === '2.50', 'Persisted percentage');
saved('tariffs', $parcelTariff, $tariff1);
$spare = saved('classes', array_replace($class, ['title' => 'Unused']));
saved('classes', array_replace($class, ['title' => 'Renamed inactive', 'active' => 0]), $spare);
check($service->edit(41, 'classes', $spare)['active'] == 0 && $service->edit(41, 'classes', $spare)['title'] === 'Renamed inactive', 'Deactivate and rename class');
invalid('classes', array_replace($class, ['title' => '']), 'title', $spare);
$extraZone = saved('zones', ['title' => 'Austria', 'countries' => ['AT'], 'active' => 1]);
saved('zones', ['title' => 'Austria', 'countries' => ['AT'], 'active' => 0], $extraZone);
invalid('tariffs', $parcelTariff, 'classid');
saved('settings', ['currency' => 'EUR', 'pickup' => 0], 0, 42);
$shopClass = saved('classes', $class, 0, 42);
$shopZone = saved('zones', $zoneData, 0, 42);
saved('tariffs', array_replace($parcelTariff, ['classid' => $shopClass, 'zoneid' => $shopZone, 'amount' => '5']), 0, 42);
saved('assignments', array_replace($assignment, ['itemid' => 103, 'classid' => $shopClass]), 0, 42);
check($service->calculate(42, [['itemid' => 103, 'quantity' => '1']], ['country' => 'DE'])['net'] === '5.00' && $service->calculate(41, [['itemid' => 101, 'quantity' => '1']], ['country' => 'DE'])['net'] === '12.00', 'Different shops mixed rates');
invalid('tariffs', array_replace($parcelTariff, ['classid' => $shopClass]), 'classid', $tariff1);
try { $db->insert('shopshippingtariff', ['clientid' => 7, 'shopid' => 41, 'classid' => $shopClass, 'zoneid' => $zone, 'currency' => 'EUR']); throw new RuntimeException('Cross-shop FK accepted'); }
catch (Zend_Db_Exception $e) { check(true, 'Cross-shop FK rejected'); }
try { $db->delete('shopshippingclass', ['id = ?' => $parcel]); throw new RuntimeException('Referenced class deleted'); }
catch (Zend_Db_Exception $e) { check(true, 'Referenced class deletion rejected'); }
$db->update('shopshippingtariff', ['parameters' => 'invalid JSON'], ['id = ?' => $tariff1]);
check($service->calculate(41, $lines, ['country' => 'DE'])['net'] === null && $service->calculate(41, $lines, ['country' => 'DE'])['groups'] === [], 'Corrupt persisted JSON produced a price or an inconsistent result');
$db->update('shopshippingtariff', ['parameters' => json_encode(['amount' => '12'])], ['id = ?' => $tariff1]);
$db->insert('shoporder', ['shopid' => '41', 'orderid' => '9001', 'invoiceid' => 0, 'total' => '25', 'clientid' => 7, 'snapshot' => '{"version":1,"historical":"unchanged"}']);
$historical = $db->fetchRow('SELECT * FROM shoporder WHERE orderid = 9001');
saved('classes', array_replace($class, ['title' => 'Parcel renamed']), $parcel);
check($db->fetchRow('SELECT * FROM shoporder WHERE orderid = 9001') === $historical, 'Configuration changed a historical order');
$admin = Zend_Registry::get('User');
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 0]);
rejected(fn() => (new Shops_Service_ShippingConfiguration())->rows(41, 'tariffs'), 403);
$itemResult = (new Shops_Service_ShippingConfiguration())->saveItemAssignment(101, ['classid' => $parcel, 'active' => 1, 'packages_per_unit' => 2, 'separate' => 1]);
check(!$itemResult['errors'], 'Item editor cannot save assignment');
Zend_Registry::set('User', ['id' => 44, 'clientid' => 7, 'admin' => 0]);
check((new Shops_Service_ShippingConfiguration())->itemAssignment(101)['shopid'] === 41, 'Reader cannot view assignment');
rejected(fn() => (new Shops_Service_ShippingConfiguration())->saveItemAssignment(101, []), 403);
Zend_Registry::set('User', $admin);
Zend_Registry::set('User', array_replace($admin, ['clientid' => 9]));
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 7, 'modules' => ['items' => 7]]);
rejected(fn() => (new Shops_Service_ShippingConfiguration())->itemAssignment(101), 403);
rejected(fn() => (new Shops_Service_ShippingConfiguration())->rows(41, 'tariffs'), 404);
Zend_Registry::set('Client', ['id' => 9, 'parentid' => 7, 'modules' => ['shops' => 7, 'items' => 7]]);
rejected(fn() => (new Shops_Service_ShippingConfiguration())->rows(41, 'tariffs'), 403);
check((new Shops_Service_ShippingConfiguration())->itemAssignment(101)['shopid'] === 41, 'Shared article class context');
Zend_Registry::set('Client', ['id' => 9, 'parentid' => 7, 'modules' => ['shops' => 7, 'items' => 7, 'admin' => 7]]);
check(count((new Shops_Service_ShippingConfiguration())->rows(41, 'tariffs')) === 2, 'Explicit shared admin context');
Zend_Registry::set('Client', ['id' => 8]); Zend_Registry::set('User', array_replace($admin, ['clientid' => 8]));
rejected(fn() => (new Shops_Service_ShippingConfiguration())->rows(41, 'tariffs'), 404);
Zend_Registry::set('Client', ['id' => 7]); Zend_Registry::set('User', $admin);
rejected(fn() => $service->rows(43, 'tariffs'), 404);
check(!Shops_Service_ShippingConfiguration::validCsrf('bad') && Shops_Service_ShippingConfiguration::validCsrf(Shops_Service_ShippingConfiguration::csrfToken()), 'CSRF tokens');
file_put_contents(dirname($argv[1]) . '/shipping-fixtures.json', json_encode(compact('parcel', 'pallet', 'zone', 'tariff1', 'tariff2', 'combinationId', 'assignment1')));
echo "PASS: $checks persisted shipping, validation, item assignment, isolation and permission checks\n";
