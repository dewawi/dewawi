<?php
// Isolated test router: no application bootstrap, configured database or outgoing mail.
$socket = getenv('DEWAWI_ORDER_TEST_SOCKET');
if (!is_string($socket) || !str_starts_with($socket, '/tmp/dewawi-shop-checkout-db-') || !is_file(dirname($socket) . '/order-fixtures.json')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 3);
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
if ($path === '/assets/jquery.js') { header('Content-Type: application/javascript'); readfile('/tmp/dewawi-jquery-3.7.1.min.js'); exit; }
$fixtures = json_decode(file_get_contents(dirname($socket) . '/order-fixtures.json'), true);
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_orders_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
// All internal HTTP cases run after public checkout has been disabled.
if ((int)$db->fetchOne('SELECT checkoutenabled FROM shop WHERE id = 41 AND clientid = 7') !== 0) throw new RuntimeException('Expected disabled checkout fixture');
Zend_Session::setOptions(['save_path' => dirname($socket) . '/order-sessions']);
$role = $_GET['role'] ?? 'editor'; $userId = ['editor' => 42, 'reader' => 43, 'denied' => 44, 'guest' => 0][$role] ?? 44;
$tenant = (int)($_GET['tenant'] ?? 7);
Zend_Registry::set('Client', ['id' => $tenant, 'parentid' => 0]);
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
$view->addScriptPath($root . '/application/modules/shops/views/scripts');
$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$action = trim($path, '/') ?: 'index'; $id = (int)($_GET['id'] ?? $fixtures['new']);
if (preg_match('~^/shops/order/([a-z]+)/id/(\d+)$~', $path, $match)) { $action = $match[1]; $id = (int)$match[2]; }
$request = new Zend_Controller_Request_Http();
$request->setModuleName('shops')->setControllerName('order')->setActionName($action)->setParam('id', $id);
foreach ($_GET as $key => $value) if ($key !== 'id') $request->setParam($key, $value);
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request)->setResponse($response);
foreach (['shops', 'sales', 'contacts'] as $module) $front->addControllerDirectory($root . '/application/modules/' . $module . '/controllers', $module);
$front->getRouter()->addDefaultRoutes();
require_once $root . '/application/modules/shops/controllers/OrderController.php';
try {
	$controller = new Shops_OrderController($request, $response);
	$view->addScriptPath($root . '/application/views/scripts');
	$controller->{$action . 'Action'}();
	if (!$response->isRedirect()) {
		$body = $view->render('order/' . (in_array($action, ['index', 'search']) ? 'index' : 'view') . '.phtml');
		$response->setBody('<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/css/ui.css"></head><body><main>' . $body . '</main></body></html>');
	}
} catch (Throwable $e) {
	$code = (int)$e->getCode(); $response->setHttpResponseCode(in_array($code, [400, 403, 404, 405], true) ? $code : 500)->setBody('Fixture request rejected');
	error_log(get_class($e) . ': ' . $e->getMessage());
}
$response->sendResponse();
