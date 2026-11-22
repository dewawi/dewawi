<?php

// Disposable M2A fixture only. It never reads a real installation's configuration.
$work = getenv('DEWAWI_UPDATES_TEST_WORK');
if (!is_string($work) || !preg_match('~^/tmp/dewawi-updates-[^/]+$~D', $work) || !is_file($work . '/states.json')) { http_response_code(404); exit; }
$root = $work . '/source';
define('BASE_PATH', $root);
define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class);
    if (count($parts) < 3) return;
    $module = array_shift($parts);
    $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
    if (is_file($path)) require_once $path;
});
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'version') {
    require $root . '/library/DEEC/Version.php';
    require $root . '/install/helper.php';
    require $root . '/install/config/config.php';
    echo json_encode(['version' => dewawiVersion(), 'installer' => $introduction['productVersion']]);
    exit;
}
$states = json_decode(file_get_contents($work . '/states.json'), true, 512, JSON_THROW_ON_ERROR);
$state = PHP_SAPI === 'cli' ? ($argv[1] ?? 'missing') : ($_GET['state'] ?? 'missing');
if (!is_string($state) || !isset($states[$state])) { http_response_code(404); exit; }
$options = (new Zend_Config_Ini($root . '/configs/operator.ini', 'production'))->toArray();
$databaseTest = ($options['systemUpdates']['mode'] ?? '') === 'database';
$hostingTest = ($options['systemUpdates']['mode'] ?? 'shared') === 'shared'
    && (!empty($options['systemUpdates']['statePath']) || is_file($root . '/configs/update-storage.json') || getenv('DEWAWI_GOBD_REPORT_DIR'));
$fixturePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$fixtureAsset = $fixturePath === '/browser.html' || (preg_match('~^/(css|js|library|images)/~', $fixturePath) && preg_match('~\.(css|js|png|svg|gif|ico)$~', $fixturePath));
if (($hostingTest || $databaseTest) && !$fixtureAsset) {
    require_once $root . '/library/DEEC/UpdateMaintenance.php';
    try { DEEC_UpdateMaintenance::enter($options['systemUpdates'] ?? [], $fixturePath === '/admin/updates' || str_starts_with($fixturePath, '/admin/updates/'), $root, $options['resources']['db'] ?? []); }
    catch (Throwable $e) { http_response_code(503); echo 'Maintenance unavailable'; exit; }
}
$db = new Zend_Db_Adapter_Pdo_Mysql(($hostingTest || $databaseTest) ? $options['resources']['db']['params'] : ['host' => 'localhost', 'unix_socket' => $work . '/mysql.sock', 'username' => 'overview', 'password' => 'fixture-secret', 'dbname' => $states[$state], 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
if (PHP_SAPI === 'cli') {
    require $root . '/tools/migrations/Inspector.php';
    echo json_encode((new DewawiMigrationInspector($db, $root . '/install/config/migrations'))->inspect(), JSON_THROW_ON_ERROR);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (($hostingTest || $databaseTest) && $path === '/ordinary-write') { $db->query('INSERT INTO tenant_fixture VALUES (99, 999)'); echo 'Written'; exit; }
if ($path === '/browser.html') { readfile($work . '/browser.html'); exit; }
if (preg_match('~^/(css|js|library|images)/~', $path) && preg_match('~\.(css|js|png|svg|gif|ico)$~', $path)) {
    $asset = realpath($root . $path);
    if ($asset && is_file($asset) && (str_starts_with($asset, $root . '/') || str_starts_with($asset, dirname(__DIR__, 2) . '/'))) {
        header('Content-Type: ' . (str_ends_with($asset, '.js') ? 'application/javascript' : (str_ends_with($asset, '.css') ? 'text/css' : 'image/png')));
        readfile($asset); exit;
    }
    http_response_code(404); exit;
}
Zend_Session::setOptions(['save_path' => $work . '/sessions']);
$role = $_GET['role'] ?? 'operator';
$user = ['id' => $role === 'tenantadmin' ? 43 : 42, 'clientid' => (int)($_GET['tenant'] ?? 7), 'admin' => $role === 'reader' ? 0 : 1, 'activated' => $role === 'inactive' ? 0 : 1, 'deleted' => $role === 'deleted' ? 1 : 0, 'name' => 'Fixture operator'];
Zend_Registry::set('User', $user);
Zend_Registry::set('Client', ['id' => $user['clientid'], 'modules' => ['admin' => 7]]);
if ($role !== 'guest') Zend_Auth::getInstance()->getStorage()->write((object)['id' => $role === 'mismatch' ? 99 : $user['id']]);
else Zend_Auth::getInstance()->clearIdentity();
$app = new Zend_Application('production', $options + ['config' => $root . '/configs/application.ini']);
$locale = ($_GET['locale'] ?? '') === 'en_US' ? 'en_US' : 'de_DE';
Zend_Registry::set('Config', ['language' => $locale]);
Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => $locale]));
$translator = new DEEC_Translate($locale); $translator->loadModule('admin'); Zend_Registry::set('DEEC_Translate', $translator);
$view = new Zend_View();
$view->addScriptPath($root . '/application/views/scripts')->addScriptPath($root . '/application/modules/admin/views/scripts');
$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper')->addHelperPath($root . '/application/modules/admin/views/helpers', 'Zend_View_Helper');
$view->doctype('HTML5'); $view->language = $locale; $view->user = $user;
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
$layout = Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts']); $layout->setView($view);
$request = new Zend_Controller_Request_Http(); $response = new Zend_Controller_Response_Http();
$parts = explode('/', trim($path, '/'));
$request->setModuleName($parts[0] ?? 'admin')->setControllerName($parts[1] ?? 'updates')->setActionName($parts[2] ?? 'index');
$request->setParams(['module' => $request->getModuleName(), 'controller' => $request->getControllerName(), 'action' => $request->getActionName()]);
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request)->setResponse($response)->setParam('bootstrap', $app->getBootstrap());
$front->addControllerDirectory($root . '/application/controllers', 'default')->addControllerDirectory($root . '/application/modules/admin/controllers', 'admin');
$front->getRouter()->addDefaultRoutes();
try {
    if ($path === '/fixture-menu') { $view->AdminMenu(); exit; }
    if ($path === '/fixture-dashboard') {
        $request->setModuleName('admin')->setControllerName('index')->setActionName('index')->setParams(['module' => 'admin', 'controller' => 'index', 'action' => 'index']);
        require $root . '/application/modules/admin/controllers/IndexController.php';
        (new Admin_IndexController($request, $response))->indexAction();
        $layout->content = $view->render('index/index.phtml');
        $response->setBody($layout->render())->sendResponse();
        exit;
    }
    if (empty($_GET['direct'])) {
        require $root . '/application/plugins/Acl.php';
        $acl = new Application_Plugin_Acl(); $acl->setResponse($response); $acl->routeShutdown($request);
    }
    if ($request->getControllerName() !== 'updates' || $request->getModuleName() !== 'admin') throw new Zend_Controller_Action_Exception('Forbidden', 403);
    require $root . '/application/modules/admin/controllers/UpdatesController.php';
    if (!$hostingTest && !$databaseTest && getenv('DEWAWI_UPDATES_PROTOCOL_TEST') === '1') {
        require_once __DIR__ . '/update-worker-support.php';
        $instance = new UpdatesProtocolController($request, $response);
    } else $instance = new Admin_UpdatesController($request, $response);
    $instance->{$request->getActionName() . 'Action'}();
    if ($response->isRedirect()) { $response->sendResponse(); exit; }
    $layout->content = $view->render('updates/index.phtml');
    $response->setBody($layout->render());
} catch (Throwable $error) {
    $code = in_array($error->getCode(), [403, 404, 405], true) ? $error->getCode() : 500;
    $response->setHttpResponseCode($code)->setBody('Fixture request rejected');
    error_log((string)$error);
}
$response->sendResponse();
