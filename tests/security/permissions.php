<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/permissions.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});
require_once $root . '/application/models/DbTable/Permission.php';
require_once $root . '/application/plugins/Acl.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
require_once __DIR__ . '/fixtures/client-switch.php';

class PermissionTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract {
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}

$adapter = new ClientSwitchFixtureAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 70]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 7]);
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
Zend_Controller_Action_HelperBroker::addHelper(new PermissionTestFlashMessenger());
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default' => $root . '/application/controllers',
    'items' => $root . '/application/modules/items/controllers',
]);
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();

function permissionRoute(string $module, string $controller, string $action, string $method = 'GET', bool $ajax = false): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    if ($ajax) $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    else unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($module)->setControllerName($controller)->setActionName($action);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $acl = new Application_Plugin_Acl();
    $acl->setResponse($response);
    $acl->routeShutdown($request);
    return [$request, $response];
}

$checks = 0;
$modules = ['default', 'calendar', 'contacts', 'items', 'processes', 'purchases', 'sales', 'statistics'];
foreach ($modules as $module) {
    foreach ([null, '', '{invalid', 'null', 'false', '"view"', '1', '[]', '{}'] as $value) {
        $adapter->permissions = [['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 0, $module => $value]];
        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            [$request, $response] = permissionRoute($module, $module === 'default' ? 'index' : 'resource', $action);
            check($response->getHttpResponseCode() === 403 && $request->getModuleName() === 'default' && $request->getControllerName() === 'error' && $request->getActionName() === 'forbidden', 'Empty or malformed module permissions allowed dispatch: ' . $module);
            $checks++;
        }
    }
}

foreach ([
    [],
    [['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 1, 'items' => '{"item":["view","edit","delete"]}']],
    [['id' => 2, 'userid' => 99, 'clientid' => 7, 'deleted' => 0, 'items' => '{"item":["view","edit","delete"]}']],
] as $rows) {
    $adapter->permissions = $rows;
    check((new Application_Model_DbTable_Permission())->getPermissions() === [], 'Missing, deleted or another user\'s permissions were loaded');
    [$request, $response] = permissionRoute('items', 'item', 'delete');
    check($response->getHttpResponseCode() === 403, 'Missing, deleted or foreign permissions allowed deletion');
    $checks++;
}

// A valid permission still belongs to the authenticated user in a shared parent context.
$adapter->permissions = [['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 0, 'items' => '{"item":["view"]}']];
$permission = new DEEC_Permission(Zend_Registry::get('User'));
foreach (['view', 'get', 'search', 'suggest', 'download', 'export', 'xrechnung'] as $action) {
    check($permission->hasActionPermission('items', 'item', $action), 'Read permission lost: ' . $action);
    $checks++;
}
foreach (['add', 'copy', 'edit', 'save', 'tags', 'delete'] as $action) {
    check(!$permission->hasActionPermission('items', 'item', $action), 'Read permission allowed a write: ' . $action);
    $checks++;
}
[$request, $response] = permissionRoute('items', 'tag', 'view');
check($response->getHttpResponseCode() === 200 && $request->getModuleName() === 'items', 'Valid permission or shared-parent controller resolution changed');
[$request, $response] = permissionRoute('items', 'item', 'delete');
check($response->isRedirect(), 'Denied write with read permission can still dispatch');
$checks += 2;

foreach (['add', 'edit', 'delete'] as $action) {
    $adapter->permissions[0]['items'] = json_encode(['item' => [$action]]);
    [$request, $response] = permissionRoute('items', 'tag', $action);
    check($response->getHttpResponseCode() === 200 && $request->getModuleName() === 'items', 'Valid write permission was lost: ' . $action);
    $checks++;
}

$adapter->permissions[0]['contacts'] = json_encode(['contact' => ['view']]);
[$request, $response] = permissionRoute('contacts', 'contact', 'tags');
check($response->isRedirect(), 'Contact view permission allowed bulk tag changes');
$adapter->permissions[0]['contacts'] = json_encode(['contact' => ['edit']]);
[$request, $response] = permissionRoute('contacts', 'contact', 'tags');
check($response->getHttpResponseCode() === 200 && $request->getModuleName() === 'contacts', 'Contact edit permission did not allow bulk tag changes');
$checks += 2;

// Denied writes must produce a forbidden response, including view-only AJAX users.
$adapter->permissions[0]['items'] = json_encode(['item' => ['view']]);
foreach (['edit', 'copy', 'delete', 'state', 'import'] as $action) {
    [$request, $response] = permissionRoute('items', 'item', $action, 'POST', true);
    check($response->getHttpResponseCode() === 403 && !$response->isRedirect() && $request->getControllerName() === 'error', 'Denied AJAX write redirected instead of failing: ' . $action);
    $checks++;
}

// Preserve the existing explicit public and non-managed module behavior.
$adapter->permissions = [];
foreach ([
    ['users', 'user', 'login'], ['users', 'user', 'edit'], ['users', 'user', 'client'],
    ['users', 'user', 'language'], ['shops', 'cart', 'index'], ['tasks', 'task', 'index'],
    ['default', 'feedback', 'index'], ['campaigns', 'feedback', 'index'],
    ['campaigns', 'unsubscribe', 'index'], ['default', 'error', 'forbidden'],
] as [$module, $controller, $action]) {
    [$request, $response] = permissionRoute($module, $controller, $action);
    check($response->getHttpResponseCode() === 200 && [$request->getModuleName(), $request->getControllerName(), $request->getActionName()] === [$module, $controller, $action], 'Existing public or non-managed route changed');
    $checks++;
}

// File managers must enter the authenticated response flow, including for AJAX guests.
Zend_Auth::getInstance()->clearIdentity();
[$request, $response] = permissionRoute('default', 'media', 'manager');
check($response->getHttpResponseCode() === 403 && $request->getControllerName() === 'error', 'Guest manager request was not denied');
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
[$request, $response] = permissionRoute('default', 'media', 'manager');
check($response->getHttpResponseCode() === 200 && $request->getActionName() === 'manager', 'Authenticated manager gateway did not reach its context checks');
$checks += 2;

// Public storefront routes do not make the legacy ERP shop controllers public.
Zend_Auth::getInstance()->clearIdentity();
foreach (['account', 'order', 'log'] as $controller) {
    [$request, $response] = permissionRoute('shops', $controller, 'edit', 'POST', true);
    check($request->getModuleName() === 'users' && $request->getActionName() === 'session', 'Guest can dispatch shop administration: ' . $controller);
    $checks++;
}
foreach (['index', 'item', 'category', 'page', 'tag', 'sitemap', 'cart', 'checkout', 'contact', 'inquiry'] as $controller) {
    [$request, $response] = permissionRoute('shops', $controller, 'index');
    check($request->getModuleName() === 'shops' && $response->getHttpResponseCode() === 200, 'Public storefront denied: ' . $controller);
    $checks++;
}
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);

// Integration log files contain records of multiple tenants and use the existing admin role.
foreach (['shops', 'ebay'] as $module) {
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 0]);
    [$request, $response] = permissionRoute($module, 'log', 'index');
    check($response->getHttpResponseCode() === 403, 'Non-admin can read global integration logs: ' . $module);
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 1]);
    [$request, $response] = permissionRoute($module, 'log', 'index');
    check($response->getHttpResponseCode() === 200, 'Administrator lost integration diagnostics: ' . $module);
    $checks += 2;
}

// Bind each manager to the selected tenant while retaining the shared data owner.
Zend_Session::$_unitTestEnabled = true;
require_once $root . '/application/views/helpers/FileManager.php';
require_once $root . '/application/views/helpers/MediaManager.php';
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
foreach (['FileManager', 'MediaManager'] as $manager) {
    foreach ([['contacts', 'contact', 10, 'contacts/7/0/7/1/0/10'], ['users', 'user', 42, 'uploads/7/0/7' . ($manager === 'FileManager' ? '/users/42' : '')], ['campaigns', 'campaign', 0, 'uploads/7/0/7'], ['admin', 'media', 0, $manager === 'MediaManager' ? '7/0/7' : 'uploads/7/0/7']] as [$module, $controller, $id, $folder]) {
        $view = new Zend_View();
        $view->assign(['module' => $module, 'controller' => $controller, 'action' => 'edit', 'id' => $id]);
        $session = new Zend_Session_Namespace($manager);
        $session->subfolder = 'foreign';
        $session->clipboard = ['path' => 'foreign/secret.pdf'];
        $class = 'Zend_View_Helper_' . $manager;
        $helper = new $class();
        $helper->setView($view);
        $helper->$manager();
        check(trim($session->subfolder, '/') === $folder, 'Manager folder lost tenant scope: ' . $manager . '/' . $module);
        check($session->context['userid'] === 42 && $session->context['clientid'] === 70 && $session->context['ownerid'] === 7, 'Manager mixed selected tenant and shared owner');
        check(strlen($session->csrf_token) === 64 && !isset($session->clipboard), 'Manager token missing or stale clipboard retained');
        $checks += 3;
    }
}

echo 'PASS: ' . $checks . " module permission, ACL, manager context and compatibility cases\n";
