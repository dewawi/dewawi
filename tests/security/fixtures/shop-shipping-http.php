<?php
// Isolated ERP router. Never boots production services or reads application credentials.
$work = getenv('DEWAWI_SHIPPING_TEST_WORK');
if (!is_string($work) || !preg_match('~^/tmp/dewawi-migrations-shipping-[^/]+$~D', $work) || !is_file($work . '/shipping-fixtures.json')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 3); define('BASE_PATH', $work); define('APPLICATION_PATH', $root . '/application');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/shipping-browser.html') { header('Content-Type: text/html'); readfile($work . '/shipping-browser.html'); exit; }
if (preg_match('~^/(css|js|library|images)/~', $path) && preg_match('~\.(css|js|png|svg|gif|ico)$~', $path)) {
    $asset = realpath($root . $path);
    if ($asset && str_starts_with($asset, $root . '/') && is_file($asset)) { header('Content-Type: ' . (str_ends_with($asset, '.js') ? 'application/javascript' : (str_ends_with($asset, '.css') ? 'text/css' : 'image/png'))); readfile($asset); exit; }
    http_response_code(404); exit;
}
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class); if (count($parts) < 3) return;
    $module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php'; if (is_file($path)) require_once $path;
});
error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors', '0');
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $work . '/mysql.sock', 'username' => 'root', 'password' => '', 'dbname' => 'shipping_upgrade', 'charset' => 'utf8mb4']); Zend_Db_Table::setDefaultAdapter($db);
Zend_Session::setOptions(['save_path' => $work . '/sessions']);
$role = $_GET['role'] ?? 'admin'; $userId = ['admin' => 42, 'editor' => 42, 'reader' => 44, 'denied' => 45, 'guest' => 0][$role] ?? 45;
$clientId = (int)($_GET['tenant'] ?? 7); $client = ['id' => $clientId];
if ($clientId === 9) $client['modules'] = ['shops' => 7, 'items' => 7] + (!empty($_GET['share_admin']) ? ['admin' => 7] : []);
Zend_Registry::set('Client', $client); Zend_Registry::set('User', ['id' => $userId, 'clientid' => $clientId, 'admin' => $role === 'admin' ? 1 : 0, 'name' => 'Fixture operator']);
if ($userId) Zend_Auth::getInstance()->getStorage()->write((object)['id' => $userId]); else Zend_Auth::getInstance()->clearIdentity();
$fixtureSession = new Zend_Session_Namespace('ShippingFixture');
if (isset($_GET['locale'])) $fixtureSession->locale = $_GET['locale'] === 'en_US' ? 'en_US' : 'de_DE';
$locale = $fixtureSession->locale ?? 'de_DE';
Zend_Registry::set('Config', ['language' => $locale]); Zend_Registry::set('Zend_Locale', new Zend_Locale($locale)); Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => $locale]));
$translator = new DEEC_Translate($locale); $translator->loadModule('admin'); $translator->loadModule('items'); Zend_Registry::set('DEEC_Translate', $translator);
$view = new class($translator) extends Zend_View {
    public function __construct(private DEEC_Translate $translator) { parent::__construct(); }
    public function translate($key, ...$args) { return $this->translator->t($key); }
};
$view->addScriptPath($root . '/application/views/scripts')->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper'); $view->doctype('HTML5'); $view->language = $locale;
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view); Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
$layout = Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts']); $layout->setView($view);
$request = new Zend_Controller_Request_Http(); $response = new Zend_Controller_Response_Http();
$parts = explode('/', trim($path, '/')); $module = $parts[0] ?? 'admin'; $controller = $parts[1] ?? 'shipping'; $action = $parts[2] ?? 'index';
$request->setModuleName($module)->setControllerName($controller)->setActionName($action)->setParams(['module' => $module, 'controller' => $controller, 'action' => $action]);
for ($i=3; $i+1<count($parts); $i+=2) $request->setParam($parts[$i], $parts[$i+1]);
foreach ($_GET as $key => $value) $request->setParam($key, $value);
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request)->setResponse($response);
foreach (['admin','items','shops','sales','contacts'] as $name) $front->addControllerDirectory($root . '/application/modules/' . $name . '/controllers', $name);
$front->getRouter()->addDefaultRoutes();
try {
    if ($module === 'admin' && $controller === 'shipping') {
        require_once $root . '/application/modules/admin/controllers/ShippingController.php';
        $instance = new Admin_ShippingController($request, $response); $instance->{$action . 'Action'}(); $script = 'shipping/' . $action . '.phtml';
        $view->addScriptPath($root . '/application/modules/admin/views/scripts');
    } elseif ($module === 'items' && $controller === 'item' && $action === 'shipping') {
        require_once $root . '/application/modules/items/controllers/ItemController.php';
        $instance = new Items_ItemController($request, $response); $instance->shippingAction(); $script = 'item/shipping.phtml';
        $view->addScriptPath($root . '/application/modules/items/views/scripts');
    } else throw new DomainException('Not found',404);
    if (!$response->isRedirect()) { $layout->content = $view->render($script); $response->setBody($layout->render()); }
} catch (Throwable $e) { $code = in_array($e->getCode(), [403,404,405,422], true) ? $e->getCode() : 500; $response->setHttpResponseCode($code)->setBody('Fixture request rejected'); error_log(get_class($e) . ': ' . $e->getMessage()); }
$response->sendResponse();
