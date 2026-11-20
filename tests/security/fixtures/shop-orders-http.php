<?php
// Isolated test router: no application bootstrap, configured database or outgoing mail.
$socket = getenv('DEWAWI_ORDER_TEST_SOCKET');
if (!is_string($socket) || !str_starts_with($socket, '/tmp/dewawi-shop-checkout-db-') || !is_file(dirname($socket) . '/order-fixtures.json')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 3);
define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class); if (count($parts) < 3) return;
	$module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors', '0');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/order-browser.html' && is_file(dirname($socket) . '/order-browser.html')) { header('Content-Type: text/html; charset=UTF-8'); readfile(dirname($socket) . '/order-browser.html'); exit; }
if ($path === '/css/ui.css') { header('Content-Type: text/css'); readfile($root . '/css/ui.css'); exit; }
$scripts = ['/library/jQuery/jquery-1.12.4.min.js', '/library/jQuery/formatCurrency/jquery.formatCurrency-1.4.0.min.js', '/library/jQuery/formatCurrency/i18n/jquery.formatCurrency.de-DE.js', '/library/jQuery/jquery.textchange.min.js', '/library/jQuery/jquery.cookie.js', '/library/jQuery/datepicker/datepicker.min.js', '/library/jQuery/datepicker/i18n/datepicker.de-DE.js', '/library/jQuery/jquery.autosize.min.js', '/library/Leaflet/leaflet.js', '/library/Datejs/date.js', '/js/simpletreemenu.js', '/js/global.js', '/js/contacts.js', '/js/calendar-reminders.js'];
if (in_array($path, $scripts, true)) { header('Content-Type: application/javascript'); readfile($root . $path); exit; }
$fixtures = json_decode(file_get_contents(dirname($socket) . '/order-fixtures.json'), true);
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_orders_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
// All internal HTTP cases run after public checkout has been disabled.
if ((int)$db->fetchOne('SELECT checkoutenabled FROM shop WHERE id = 41 AND clientid = 7') !== 0) throw new RuntimeException('Expected disabled checkout fixture');
Zend_Session::setOptions(['save_path' => dirname($socket) . '/order-sessions']);
$role = $_GET['role'] ?? 'editor'; $userId = ['editor' => 42, 'reader' => 43, 'denied' => 44, 'guest' => 0][$role] ?? 44;
$tenant = (int)($_GET['tenant'] ?? 7);
$client = ['id' => $tenant, 'parentid' => 0];
if ($tenant === 9) $client = ['id' => 9, 'parentid' => 7, 'modules' => ['shops' => 7, 'sales' => 9]];
Zend_Registry::set('Client', $client);
Zend_Registry::set('User', ['id' => $userId, 'clientid' => $tenant, 'name' => 'Fixture reviewer']);
if ($userId) Zend_Auth::getInstance()->getStorage()->write((object)['id' => $userId]); else Zend_Auth::getInstance()->clearIdentity();
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => 'de_DE']));
Zend_Registry::set('Config', ['language' => 'de_DE']);
$translator = new DEEC_Translate('de_DE'); $translator->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $translator);
$view = new class($translator) extends Zend_View {
	public function __construct(private DEEC_Translate $translator) { parent::__construct(); }
	public function translate($key, ...$args) { return $this->translator->t($key); }
};
$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
$view->doctype('HTML5');
$view->language = 'de_DE';
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
$layout = Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts']);
$layout->setView($view);
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$action = trim($path, '/') ?: 'index'; $id = (int)($_GET['id'] ?? $fixtures['new']);
if ($path === '/shops/order') $action = 'index';
if ($path === '/shops/order/search') $action = 'search';
if (preg_match('~^/shops/order/([a-z]+)/id/(\d+)$~', $path, $match)) { $action = $match[1]; $id = (int)$match[2]; }
$request = new Zend_Controller_Request_Http();
$request->setModuleName('shops')->setControllerName('order')->setActionName($action)->setParam('id', $id);
$request->setParams(['module' => 'shops', 'controller' => 'order', 'action' => $action]);
foreach ($_GET as $key => $value) if ($key !== 'id') $request->setParam($key, $value);
// Match module helper registration after routing, before any Options helper is loaded.
if (Zend_Controller_Action_HelperBroker::hasHelper('Options')) throw new RuntimeException('Options was preloaded before module routing');
(new Shops_Plugin_Helper())->routeShutdown($request);
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request)->setResponse($response);
foreach (['shops', 'sales', 'contacts'] as $module) $front->addControllerDirectory($root . '/application/modules/' . $module . '/controllers', $module);
$front->getRouter()->addDefaultRoutes();
require_once $root . '/application/modules/shops/controllers/OrderController.php';
try {
	$controller = new Shops_OrderController($request, $response);
	$view->addScriptPath($root . '/application/views/scripts');
	$controller->{$action . 'Action'}();
	if (in_array($action, ['index', 'search'], true)) {
		$options = Zend_Controller_Action_HelperBroker::getExistingHelper('Options');
		if (!$options instanceof Application_Controller_Action_Helper_Options || $options->getActionController() !== $controller || $options->getRequest() !== $request) throw new RuntimeException('Form options lost controller/request binding');
	}
	if (Zend_Registry::isRegistered('SiteContext')) throw new RuntimeException('Internal ERP request acquired a SiteContext');
	if ($front->getRouter()->hasRoute('shop_home')) throw new RuntimeException('Internal ERP request acquired a public home route');
	if (!$response->isRedirect()) {
		$body = $view->render('order/' . (in_array($action, ['index', 'search']) ? 'index' : 'view') . '.phtml');
		$response->setBody($body);
		if ($action === 'search' && $request->isXmlHttpRequest()) {
			$plugin = new Application_Plugin_ListResponse();
			$plugin->setResponse($response)->postDispatch($request);
		} else {
			$view->headScript()->prependScript('var fixtureErrors=[];window.addEventListener("error",function(e){fixtureErrors.push(e.message)});');
			$layout->content = $body;
			$response->setBody($layout->render());
			if ((new ReflectionClass($view->getHelper('Breadcrumbs')))->getFileName() !== $root . '/application/views/helpers/Breadcrumbs.php') throw new RuntimeException('ERP layout resolved public shop breadcrumbs');
		}
	}
} catch (Throwable $e) {
	$code = (int)$e->getCode(); $response->setHttpResponseCode(in_array($code, [400, 403, 404, 405], true) ? $code : 500)->setBody('Fixture request rejected');
	error_log(get_class($e) . ': ' . $e->getMessage());
}
$response->sendResponse();
