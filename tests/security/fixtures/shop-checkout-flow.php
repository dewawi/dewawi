<?php
$socket = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : getenv('DEWAWI_CHECKOUT_TEST_SOCKET');
if (!is_string($socket) || !str_starts_with($socket, '/tmp/dewawi-shop-checkout-db-') || !file_exists($socket)) { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 3);
define('BASE_PATH', $root);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$path = $root . '/application/modules/' . strtolower($module) . '/' . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
if (PHP_SAPI === 'cli' && ($argv[2] ?? '') === 'init') {
	$pdo = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$pdo->exec('CREATE DATABASE dewawi_shop_checkout_flow_test');
}
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_checkout_flow_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
if (PHP_SAPI === 'cli') {
	if (($argv[2] ?? '') === 'init') {
		require_once $root . '/install/helper.php';
		restore_exception_handler();
		$sql = []; PMA_splitSqlFile($sql, file_get_contents($root . '/install/config/structure.sql'));
		foreach ($sql as $query) $db->query($query['query']);
		foreach ([7, 8] as $client) {
			$db->insert('increment', ['clientid' => $client, 'shoporderid' => 1000]);
			$db->insert('country', ['clientid' => $client, 'code' => 'DE', 'name' => 'Deutschland', 'language' => 'de_DE']);
			$db->insert('country', ['clientid' => $client, 'code' => 'AT', 'name' => 'Österreich', 'language' => 'de_DE']);
		}
		$db->insert('taxrate', ['id' => 11, 'clientid' => 7, 'name' => 'VAT', 'rate' => 19]);
		$db->insert('taxrate', ['id' => 12, 'clientid' => 7, 'name' => 'Zero', 'rate' => 0]);
		foreach ([[41, 7], [42, 7], [43, 8]] as [$site, $client]) $db->insert('shop', ['id' => $site, 'clientid' => $client, 'title' => 'Checkout fixture', 'language' => 'de_DE', 'url' => 'https://fixture.invalid', 'emailsender' => 'shop@example.invalid', 'checkoutenabled' => 1]);
		foreach ([[103, 11], [104, 11], [105, null], [106, 12]] as [$id, $tax]) $db->insert('item', ['id' => $id, 'clientid' => 7, 'shopid' => 41, 'catid' => 0, 'shopenabled' => 1, 'title' => 'Wärmepumpe <img src=x onerror=alert(1)>', 'sku' => 'ITEM-' . $id, 'price' => 500, 'specialprice' => 100, 'currency' => 'EUR', 'taxid' => $tax]);
		$db->query("CREATE TRIGGER reject_checkout_fixture BEFORE INSERT ON shoporderpos FOR EACH ROW BEGIN IF NEW.itemid = 104 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture position failure'; END IF; END");
		mkdir(dirname($socket) . '/checkout-flow-sessions', 0700);
	} elseif (($argv[2] ?? '') === 'shipping-init') {
		$db->insert('client', ['id' => 7, 'country' => 'DE']);
		$db->insert('currency', ['clientid' => 7, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);
		$db->update('shop', ['shippingcurrency' => 'EUR', 'pickupenabled' => 1, 'contactenabled' => 1], 'id = 41');
		$db->insert('shopshippingclass', ['id' => 1, 'clientid' => 7, 'shopid' => 41, 'title' => 'Parcel', 'active' => 1, 'combine_same_class' => 1, 'required_fields' => '["packweight"]']);
		$db->insert('shopshippingzone', ['id' => 1, 'clientid' => 7, 'shopid' => 41, 'title' => 'Germany', 'active' => 1, 'countries' => '["DE"]']);
		$db->insert('shopshippingtariff', ['id' => 1, 'clientid' => 7, 'shopid' => 41, 'classid' => 1, 'zoneid' => 1, 'title' => 'Parcel', 'active' => 1, 'model' => 'fixed', 'currency' => 'EUR', 'parameters' => '{"amount":"12.00"}']);
		foreach ([103,104,105,106] as $id) $db->insert('shopitemshipping', ['clientid' => 7, 'shopid' => 41, 'itemid' => $id, 'classid' => 1, 'active' => 1, 'packages_per_unit' => 2, 'separate' => 0]);
		$db->update('item', ['packweight' => 5], 'shopid = 41');
	} elseif (($argv[2] ?? '') === 'shipping-tariff') {
		$db->update('shopshippingtariff', ['model' => $argv[3] === 'manual' ? 'manual' : 'fixed', 'parameters' => $argv[3] === 'manual' ? '{}' : json_encode(['amount' => $argv[3]])], 'id = 1');
	} elseif (($argv[2] ?? '') === 'pickup') {
		$db->update('shop', ['pickupenabled' => (int)$argv[3]], 'id = 41');
	} elseif (($argv[2] ?? '') === 'tax-change') {
		$db->update('taxrate', ['rate' => (float)$argv[3]], 'id = 11');
	} elseif (($argv[2] ?? '') === 'channels') {
		$db->update('shop', ['contactenabled' => (int)$argv[3], 'inquiryenabled' => (int)$argv[4]], 'id = 41');
	} elseif (($argv[2] ?? '') === 'tax-init') {
		$db->update('shop', ['shippingtaxpolicy' => Shops_Service_ShippingConfiguration::TAX_POLICY], 'id = 41');
		$db->update('shop', ['contactenabled' => 1], 'id = 42');
		$db->insert('taxrate', ['id' => 13, 'clientid' => 7, 'name' => 'Reduced fixture', 'rate' => 7]);
		$db->insert('taxrate', ['id' => 21, 'clientid' => 8, 'name' => 'Other fixture', 'rate' => 19]);
		foreach ([[107, 41, 7, 13], [203, 42, 7, 11], [303, 43, 8, 21]] as [$id, $site, $client, $tax]) $db->insert('item', ['id' => $id, 'clientid' => $client, 'shopid' => $site, 'catid' => 0, 'shopenabled' => 1, 'title' => 'Mixed-rate article', 'sku' => 'ITEM-' . $id, 'price' => 100, 'currency' => 'EUR', 'taxid' => $tax, 'packweight' => 5]);
		$db->insert('shopitemshipping', ['clientid' => 7, 'shopid' => 41, 'itemid' => 107, 'classid' => 1, 'active' => 1, 'packages_per_unit' => 1, 'separate' => 0]);
		$db->insert('country', ['clientid' => 7, 'code' => 'CH', 'name' => 'Switzerland', 'language' => 'de_DE']);
		$db->update('shopshippingzone', ['countries' => '["DE","AT","CH"]'], 'id = 1');
	} elseif (($argv[2] ?? '') === 'tax-policy') {
		$db->update('shop', ['shippingtaxpolicy' => ($argv[3] ?? '') === 'approved' ? Shops_Service_ShippingConfiguration::TAX_POLICY : null], 'id = 41');
	} elseif (($argv[2] ?? '') === 'phase' && in_array($argv[3] ?? '', ['base', 'shipping', 'tax', 'tax-browser'], true)) {
		file_put_contents(dirname($socket) . '/checkout-phase', $argv[3]);
	} elseif (($argv[2] ?? '') === 'price-change') {
		$db->update('item', ['specialprice' => (float)$argv[3]], 'id = 103');
	} else {
		echo json_encode(['orders' => $db->fetchAll('SELECT id, orderid, shopid, clientid, token, total, taxes, grosstotal, snapshot FROM shoporder'),
			'positions' => (int)$db->fetchOne('SELECT COUNT(*) FROM shoporderpos'), 'history' => (int)$db->fetchOne('SELECT COUNT(*) FROM shoporderhistory'),
			'mails' => is_file(dirname($socket) . '/checkout-mails.log') ? count(file(dirname($socket) . '/checkout-mails.log')) : 0,
			'inquiries' => $db->fetchAll('SELECT data FROM shopinquirydata')]);
	}
	exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = ['/_assets/jquery.min.js' => '/tmp/dewawi-jquery-3.7.1.min.js', '/_assets/bootstrap.min.css' => '/tmp/dewawi-bootstrap-4.6.2.min.css', '/js/shops.js' => $root . '/js/shops.js', '/css/shop.css' => $root . '/css/shop.css'];
if (isset($assets[$path])) { header('Content-Type: ' . (str_ends_with($path, '.css') ? 'text/css' : 'application/javascript')); readfile($assets[$path]); exit; }
if ($path === '/_checkout-runner') { header('Content-Type: text/html; charset=UTF-8'); readfile($root . '/tests/site/checkout-runner.html'); exit; }
if ($path === '/_shipping-runner') { header('Content-Type: text/html; charset=UTF-8'); readfile($root . '/tests/site/checkout-shipping-runner.html'); exit; }
if ($path === '/_tax-runner') { header('Content-Type: text/html; charset=UTF-8'); readfile($root . '/tests/site/checkout-tax-runner.html'); exit; }
$site = str_starts_with($path, '/b/') ? 42 : (str_starts_with($path, '/c/') ? 43 : 41);
$client = $site === 43 ? 8 : 7;
$base = $site === 42 ? '/b' : ($site === 43 ? '/c' : '/a');
Zend_Registry::set('Client', ['id' => $client, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 0]);
$shop = $db->fetchRow($db->select()->from('shop')->where('id = ?', $site)->where('clientid = ?', $client));
Zend_Registry::set('Shop', $shop);
Zend_Session::setOptions(['save_path' => dirname($socket) . '/checkout-flow-sessions', 'use_cookies' => true]);
$languageSession = new Zend_Session_Namespace('FixtureLocale');
if (isset($_GET['lang'])) $languageSession->locale = $_GET['lang'] === 'en' ? 'en_US' : 'de_DE';
$locale = $languageSession->locale ?? 'de_DE';
$features = ['cart', 'checkout'];
foreach (['contact', 'inquiry'] as $feature) if (!empty($shop[$feature . 'enabled'])) $features[] = $feature;
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop, [], $features));
Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', $locale));
$tr = new DEEC_Translate($locale); $tr->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $tr);
if (($_SERVER['HTTP_X_FIXTURE_LEGACY_RECEIPT'] ?? '') === '1') {
	$legacy = new Zend_Session_Namespace('ShopsCheckout'); unset($legacy->receipt);
}
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
class CheckoutFlowMail extends Zend_Controller_Action_Helper_Abstract {
	public function getName() { return 'Email'; }
	public function sendEmail(...$args): array {
		file_put_contents(dirname(getenv('DEWAWI_CHECKOUT_TEST_SOCKET')) . '/checkout-mails.log', "attempt\n", FILE_APPEND | LOCK_EX);
		if ($this->getRequest()->getHeader('X-Fixture-Mail-Delay')) usleep(500000);
		if ($this->getRequest()->getHeader('X-Fixture-Mail-Fail') === 'exception') throw new RuntimeException('Fixture mail failure');
		return ['customerAccepted' => !$this->getRequest()->getHeader('X-Fixture-Mail-Fail'), 'internalAccepted' => true];
	}
}
Zend_Controller_Action_HelperBroker::addHelper(new CheckoutFlowMail());
class CheckoutFlowLimiter extends Zend_Controller_Action_Helper_Abstract {
	public function init() {
		$controller = $this->getActionController();
		if (!$controller instanceof Shops_Controller_Action) return;
		$property = new ReflectionProperty(Shops_Controller_Action::class, 'submissionLimiter');
		$property->setAccessible(true);
		$work = dirname(getenv('DEWAWI_CHECKOUT_TEST_SOCKET'));
		$phase = is_file($work . '/checkout-phase') ? file_get_contents($work . '/checkout-phase') : 'legacy';
		$property->setValue($controller, new DEEC_Site_SubmissionLimiter($work . '/' . $phase . '-checkout-rate'));
	}
}
Zend_Controller_Action_HelperBroker::addHelper(new CheckoutFlowLimiter());

$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(['shops' => $root . '/application/modules/shops/controllers'])->setBaseUrl($base)->throwExceptions(true);
$front->getRouter()->removeDefaultRoutes();
foreach (['checkout' => ['checkout', 'index'], 'checkout_send' => ['checkout/send', 'send'], 'checkout_success' => ['checkout/success', 'success'], 'shop_home' => ['', 'index']] as $name => [$route, $action]) $front->getRouter()->addRoute($name, new Zend_Controller_Router_Route_Static($route, ['module' => 'shops', 'controller' => 'checkout', 'action' => $action]));
$front->getRouter()->addRoute('contact_success', new Zend_Controller_Router_Route_Static('contact/success', ['module' => 'shops', 'controller' => 'contact', 'action' => 'success']));
foreach (['index', 'add', 'update', 'clear', 'remove', 'count'] as $action) $front->getRouter()->addRoute($action === 'index' ? 'cart' : 'cart_' . $action, new Zend_Controller_Router_Route_Static($action === 'index' ? 'cart' : 'cart/' . $action, ['module' => 'shops', 'controller' => 'cart', 'action' => $action]));
$view = new Zend_View();
$view->setScriptPath($root . '/application/modules/shops/views/scripts')->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper')->addHelperPath($root . '/application/modules/shops/views/helpers', 'Zend_View_Helper');
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
$layout = Zend_Layout::startMvc(['layoutPath' => __DIR__ . '/checkout-layout', 'layout' => 'site']);
$layout->setView($view);
$front->dispatch();
