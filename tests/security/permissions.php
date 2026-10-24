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

function permissionRoute(string $module, string $controller, string $action): array {
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
foreach (['view', 'get', 'search', 'suggest', 'download', 'export'] as $action) {
    check($permission->hasActionPermission('items', 'item', $action), 'Read permission lost: ' . $action);
    $checks++;
}
foreach (['add', 'copy', 'edit', 'save', 'delete'] as $action) {
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

echo 'PASS: ' . $checks . " module permission, ACL and compatibility cases\n";
