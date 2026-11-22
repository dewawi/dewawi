<?php
// Called by shop-shipping-db.py against its disposable socket; no application credentials.
if (PHP_SAPI !== 'cli' || !preg_match('~^/tmp/dewawi-migrations-shipping-[^/]+/mysql.sock$~D', $argv[1] ?? '')) exit(1);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', dirname($argv[1])); define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class); if (count($parts) < 3) return;
	$module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php'; if (is_file($path)) require_once $path;
});
set_error_handler(static function(int $severity, string $message, string $file, int $line): bool { if (!(error_reporting() & $severity)) return false; throw new ErrorException($message, 0, $severity, $file, $line); });
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'shipping_upgrade', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Session::setOptions(['save_path' => BASE_PATH . '/sessions', 'use_cookies' => false]);
$admin = ['id' => 42, 'clientid' => 7, 'admin' => 1];
Zend_Registry::set('User', $admin); Zend_Registry::set('Client', ['id' => 7]);
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
$checks = 0;
function checkLive(bool $value, string $message): void { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
function deniedLive(callable $fn, int $code): void {
	try { $fn(); } catch (DomainException $e) { checkLive($e->getCode() === $code, 'Wrong rejection'); return; }
	throw new RuntimeException('Expected denial');
}
function statusesLive(array $report): array { return array_column($report['checks'], 'status', 'key'); }
$db->insert('client', ['id' => 7, 'country' => 'DE', 'activated' => 1]);
$db->insert('language', ['code' => 'de_DE', 'name' => 'Deutsch', 'clientid' => 7]);
$db->insert('taxrate', ['id' => 71, 'name' => 'Fixture tax', 'rate' => '19', 'clientid' => 7]);
$shop = ['id' => 51, 'clientid' => 7, 'title' => 'Synthetic go-live shop', 'url' => 'https://readiness.example.invalid/shop', 'activated' => 1, 'indexable' => 1,
	'catalogenabled' => 1, 'checkoutenabled' => 1, 'contactenabled' => 1, 'language' => 'de_DE', 'timezone' => 'Europe/Berlin', 'shippingcurrency' => 'EUR',
	'emailsender' => 'shop@example.invalid', 'smtphost' => 'smtp.example.invalid', 'smtpuser' => 'synthetic', 'smtppass' => 'fixture-secret-never-render'];
$db->insert('shop', $shop);
$db->insert('page', ['clientid' => 7, 'shopid' => 51, 'parentid' => 0, 'type' => 'home', 'title' => 'Home', 'activated' => 1]);
$db->insert('menu', ['clientid' => 7, 'shopid' => 51, 'title' => 'Navigation', 'activated' => 1]);
$db->insert('category', ['id' => 151, 'parentid' => 0, 'clientid' => 7, 'shopid' => 51, 'title' => 'Products', 'type' => 'shop', 'activated' => 1]);
foreach (['checkout', 'contact', 'inquiry'] as $type) $db->insert('emailtemplate', ['userid' => 0, 'clientid' => 7, 'module' => 'shops', 'controller' => $type, 'subject' => 'Fixture', 'body' => 'Fixture']);
$shipping = new Shops_Service_ShippingConfiguration();
$save = static function(string $kind, array $data, int $id = 0) use ($shipping): int {
	$result = $shipping->save(51, $kind, $id, $data); checkLive(!$result['errors'], json_encode($result)); return $result['id'];
};
$parcel = $save('classes', ['title' => 'Parcel', 'active' => 1, 'combine_same_class' => 1, 'require_packweight' => 1]);
$pallet = $save('classes', ['title' => 'Pallet', 'active' => 1, 'combine_same_class' => 1]);
$zone = $save('zones', ['title' => 'Germany', 'countries' => ['DE'], 'active' => 1]);
$rateData = ['title' => 'Rate', 'classid' => $parcel, 'zoneid' => $zone, 'currency' => 'EUR', 'active' => 1, 'model' => 'fixed', 'amount' => '10'];
$tariff = $save('tariffs', $rateData);
$save('tariffs', array_replace($rateData, ['classid' => $pallet, 'amount' => '100']));
$assignment = 0;
mkdir(BASE_PATH . '/media/7/0/7/images', 0777, true);
file_put_contents(BASE_PATH . '/media/7/0/7/images/fixture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
foreach ([501 => $parcel, 502 => $pallet] as $id => $class) {
	$db->insert('item', ['id' => $id, 'clientid' => 7, 'shopid' => 51, 'catid' => 0, 'shopcatid' => 151, 'shopenabled' => 1, 'price' => '100', 'currency' => 'EUR', 'taxid' => 71, 'packweight' => '5']);
	$assigned = $save('assignments', ['itemid' => $id, 'classid' => $class, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0]);
	if ($id === 501) $assignment = $assigned;
	$db->insert('media', ['clientid' => 7, 'parentid' => $id, 'module' => 'items', 'controller' => 'item', 'type' => 'image', 'url' => 'fixture.png']);
}
$service = new Admin_Service_ShopReadiness();
$report = $service->inspect(51); $states = statusesLive($report);
checkLive($report['technicalStatus'] === 'ready' && $report['status'] === 'warning', 'Complete fixture must be technically ready but require manual acceptance: ' . json_encode($report));
checkLive($states['freight'] === 'ready' && $states['images'] === 'ready', 'Multiple groups or public image failed');
checkLive($states['entitlements'] === 'warning' && $states['legal'] === 'warning' && $states['operations'] === 'warning', 'Synthetic setup certified manual acceptance');

// Reads must preserve all business rows, including historical snapshots and configuration.
$db->insert('shoporder', ['clientid' => 7, 'shopid' => 51, 'orderid' => 'C5A-OLD', 'invoiceid' => 0, 'total' => 10, 'snapshot' => '{"version":1,"historical":"unchanged"}']);
$before = [];
foreach (['shop', 'item', 'shoporder', 'shopshippingtariff', 'shopitemshipping', 'shopinquirydata'] as $table) $before[$table] = $db->fetchAll('SELECT * FROM ' . $db->quoteIdentifier($table) . ' ORDER BY id');
$service->inspect(51);
foreach ($before as $table => $rows) checkLive($db->fetchAll('SELECT * FROM ' . $db->quoteIdentifier($table) . ' ORDER BY id') === $rows, 'Readiness mutated ' . $table);

foreach ([['checkoutenabled' => 0], ['checkoutenabled' => 0, 'inquiryenabled' => 1]] as $changes) {
	$db->update('shop', $changes, 'id = 51');
	if (!empty($changes['inquiryenabled'])) $db->insert('shopinquiryform', ['shopid' => 51, 'clientid' => 7, 'quotetemplateid' => 0, 'fields' => '{"first":[{"name":"email","type":"email"}]}']);
	$report = $service->inspect(51); $states = statusesLive($report);
	checkLive($report['status'] === 'warning' && $states['checkout'] === 'warning' && !isset($states['freight']), 'Non-ordering shop incorrectly released or blocked by rates');
	if (!empty($changes['inquiryenabled'])) checkLive($states['inquiry'] === 'ready', 'Valid inquiry-only shop blocked');
}
$db->update('shop', ['checkoutenabled' => 1, 'inquiryenabled' => 0, 'shippingcurrency' => null], 'id = 51');
checkLive(statusesLive($service->inspect(51))['legacy'] === 'blocked', 'Unknown legacy shipping released');
$db->update('shop', ['shippingcurrency' => 'EUR'], 'id = 51');
$db->update('shopitemshipping', ['active' => 0], ['id = ?' => $assignment]);
checkLive(statusesLive($service->inspect(51))['freight'] === 'blocked', 'Missing assignment released');
$db->update('shopitemshipping', ['active' => 1], ['id = ?' => $assignment]);
$db->update('shopshippingtariff', ['active' => 0], ['id = ?' => $tariff]);
checkLive(statusesLive($service->inspect(51))['freight'] === 'blocked', 'Missing tariff released');
$db->update('shopshippingtariff', ['active' => 1, 'model' => 'manual', 'parameters' => '{}'], ['id = ?' => $tariff]);
checkLive(statusesLive($service->inspect(51))['freight'] === 'blocked', 'Manual freight released');
$db->update('shopshippingtariff', ['model' => 'fixed', 'parameters' => '{"amount":"10"}'], ['id = ?' => $tariff]);
$db->update('item', ['packweight' => null], 'id = 501');
checkLive(statusesLive($service->inspect(51))['freight'] === 'blocked', 'Missing packaging released');
$db->update('item', ['packweight' => 5, 'currency' => 'USD'], 'id = 501');
checkLive(statusesLive($service->inspect(51))['prices'] === 'blocked', 'Unsupported currency released');
$db->update('item', ['currency' => 'EUR', 'taxid' => 999], 'id = 501');
checkLive(statusesLive($service->inspect(51))['taxes'] === 'blocked', 'Unknown tax released');
$db->update('item', ['taxid' => 71, 'shopcatid' => 999], 'id = 501');
checkLive(statusesLive($service->inspect(51))['categories'] === 'blocked', 'Foreign/missing category released');
$db->update('item', ['shopcatid' => 151], 'id = 501');
$db->update('media', ['url' => '../private.png'], 'parentid = 501');
checkLive(statusesLive($service->inspect(51))['images'] === 'warning', 'Non-public image released');
$db->update('media', ['url' => 'fixture.png'], 'parentid = 501');
copy(BASE_PATH . '/media/7/0/7/images/fixture.png', BASE_PATH . '/media/7/0/7/images/fixture.log');
$db->update('media', ['url' => 'fixture.log'], 'parentid = 501');
checkLive(statusesLive($service->inspect(51))['images'] === 'warning', 'Publisher-denied image extension released');
$db->update('media', ['url' => 'fixture.png'], 'parentid = 501');
symlink(BASE_PATH . '/media/7/0/7/images/fixture.png', BASE_PATH . '/media/7/0/7/images/link.png');
$db->update('media', ['url' => 'link.png'], 'parentid = 501');
checkLive(statusesLive($service->inspect(51))['images'] === 'warning', 'Non-public symlink crashed or passed the image check');
$db->update('media', ['url' => 'fixture.png'], 'parentid = 501');
$db->update('shopshippingtariff', ['parameters' => 'invalid JSON'], ['id = ?' => $tariff]);
checkLive(statusesLive($service->inspect(51))['shipping_config'] === 'blocked', 'Invalid tariff JSON released');
$db->update('shopshippingtariff', ['parameters' => '{"amount":"10"}'], ['id = ?' => $tariff]);
$db->insert('country', ['clientid' => 7, 'code' => 'ZZ', 'name' => 'Synthetic invalid region', 'language' => 'de_DE']);
foreach (['Deutschland', '', 'ZZ'] as $country) {
	$db->update('client', ['country' => $country], 'id = 7');
	checkLive(statusesLive($service->inspect(51))['country'] === 'blocked', 'Invalid tenant country released');
}
$db->update('client', ['country' => 'DE'], 'id = 7');

// Each shipping table and representative missing columns are detected before shipping data reads.
$db->getProfiler()->setEnabled(true);
foreach (Shops_Service_ShippingConfiguration::TABLES as $table) {
	$db->query('RENAME TABLE ' . $db->quoteIdentifier($table) . ' TO ' . $db->quoteIdentifier($table . '_missing'));
	$db->getProfiler()->clear();
	$report = $service->inspect(51);
	checkLive(statusesLive($report)['schema'] === 'blocked', 'Missing table released: ' . $table);
	foreach ($db->getProfiler()->getQueryProfiles() ?: [] as $profile) checkLive(!preg_match('/\b(?:FROM|JOIN)\s+`?' . $table . '`?\b/i', $profile->getQuery()), 'Queried missing table: ' . $table);
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 51, 'clientid' => 7], [], ['cart', 'checkout']));
	$result = $shipping->calculateCheckout([['itemid' => 501, 'quantity' => '1']], ['country' => 'DE']);
	checkLive($result['status'] === 'quote_required' && empty($result['legacy']) && $result['reasons'] === ['shipping_schema_missing'], 'Configured checkout failed open on absent table');
	if ($table === 'shopshippingclass') {
		$db->update('shop', ['checkoutenabled' => 0], 'id = 51');
		checkLive(statusesLive($service->inspect(51))['schema'] === 'warning', 'Website-only shop with absent schema crashed or required ordering release');
		$db->update('shop', ['checkoutenabled' => 1], 'id = 51');
	}
	$db->query('RENAME TABLE ' . $db->quoteIdentifier($table . '_missing') . ' TO ' . $db->quoteIdentifier($table));
}
foreach (['shop' => ['shippingcurrency', 'pickupenabled'], 'item' => ['packweight'], 'shopshippingtariff' => ['parameters']] as $table => $fields) foreach ($fields as $field) {
	$db->query('ALTER TABLE ' . $db->quoteIdentifier($table) . ' RENAME COLUMN ' . $db->quoteIdentifier($field) . ' TO ' . $db->quoteIdentifier($field . '_missing'));
	checkLive(statusesLive($service->inspect(51))['schema'] === 'blocked', 'Missing column released: ' . $field);
	if ($field === 'shippingcurrency') {
		$result = $shipping->calculateCheckout([['itemid' => 501, 'quantity' => '1']], ['country' => 'DE']);
		checkLive(!empty($result['legacy']), 'Pre-migration legacy compatibility removed');
	}
	$db->query('ALTER TABLE ' . $db->quoteIdentifier($table) . ' RENAME COLUMN ' . $db->quoteIdentifier($field . '_missing') . ' TO ' . $db->quoteIdentifier($field));
}
$db->getProfiler()->setEnabled(false);

$db->insert('shop', ['id' => 52, 'clientid' => 8, 'activated' => 1, 'url' => $shop['url']]);
checkLive(statusesLive($service->inspect(51))['host'] === 'blocked', 'Ambiguous host released');
deniedLive(fn() => $service->inspect(52), 404);
$db->update('shop', ['deleted' => 1], 'id = 52');
checkLive((new DEEC_Site_Resolver())->resolveByHost('readiness.example.invalid', ['requestPath' => '/shop/'])->getSiteId() === 51, 'Deleted active shop still conflicts with host');
$db->insert('shop', ['id' => 53, 'clientid' => 8, 'activated' => 1, 'deleted' => 1, 'url' => 'https://deleted.example.invalid']);
checkLive((new DEEC_Site_Resolver())->resolveByHost('deleted.example.invalid') === null, 'Deleted shop is publicly accessible');
foreach ([['admin' => 0], ['id' => 0]] as $changes) {
	Zend_Registry::set('User', array_replace($admin, $changes)); deniedLive(fn() => $service->inspect(51), 403);
}
Zend_Registry::set('User', $admin);
Zend_Registry::set('Client', ['id' => 8]); deniedLive(fn() => $service->inspect(51), 404);
Zend_Registry::set('Client', ['id' => 7, 'modules' => ['shops' => 8]]);
checkLive(statusesLive($service->inspect(51))['modules'] === 'blocked', 'Mismatched shop module owner released');
Zend_Registry::set('Client', ['id' => 7, 'modules' => ['shops' => 7, 'items' => 7, 'admin' => 7]]);
Zend_Registry::set('User', array_replace($admin, ['clientid' => 9]));
checkLive($service->inspect(51)['technicalStatus'] === 'ready', 'Explicit inherited administration denied');
Zend_Registry::set('Client', ['id' => 7, 'modules' => ['shops' => 7, 'items' => 7]]);
checkLive(statusesLive($service->inspect(51))['modules'] === 'blocked', 'Item sharing granted administrative configuration access');
Zend_Registry::set('Client', ['id' => 7]); Zend_Registry::set('User', $admin);
checkLive($service->inspect(51)['technicalStatus'] === 'ready', 'Fixture not restored');
foreach (['de_DE', 'en_US'] as $locale) {
	$translator = new DEEC_Translate($locale); $translator->loadModule('admin');
	$view = new class($translator) extends Zend_View {
		public function __construct(private DEEC_Translate $translator) { parent::__construct(); }
		public function translate($key, ...$args) { return $this->translator->t($key); }
	};
	$view->setScriptPath($root . '/application/modules/admin/views/scripts');
	$view->report = $service->inspect(51);
	$view->report['checks'][0]['detail'] = '<script>synthetic</script>';
	$html = $view->render('shop/_readiness.phtml');
	checkLive(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Readiness detail XSS: ' . $locale);
	checkLive(!str_contains($html, 'ADMIN_GOLIVE_'), 'Missing readiness translation: ' . $locale);
}
echo "PASS: $checks shop go-live, schema guards, read-only snapshots, isolation and deleted-host checks\n";
