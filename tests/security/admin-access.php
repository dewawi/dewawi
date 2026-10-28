<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/admin-access.php
// No database or running application is required.
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    $file = str_replace('_', '/', $class) . '.php';
    if ($path = stream_resolve_include_path($file)) {
        require_once $path;
    }
});
require_once $root . '/application/plugins/Acl.php';
require_once $root . '/application/controllers/ErrorController.php';

class SecurityTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Zend_Controller_Action_HelperBroker::addHelper(new SecurityTestFlashMessenger());
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write(['id' => 42]);

$checks = 0;
foreach ([0, 1] as $admin) {
    Zend_Registry::set('User', ['id' => 42, 'admin' => $admin]);
    foreach (['user', 'config', 'permission', 'client', 'export', 'error', 'uom'] as $controller) {
        foreach (['GET', 'POST'] as $method) {
            foreach ([false, true] as $ajax) {
                $request = new Zend_Controller_Request_Http();
                $request->setModuleName('admin')->setControllerName($controller)
                    ->setActionName('edit')->setParam('id', 7)
                    ->setRequestUri('/admin/' . $controller . '/edit/id/7');
                $_SERVER['REQUEST_METHOD'] = $method;
                $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
                $response = new Zend_Controller_Response_Http();
                $plugin = new Application_Plugin_Acl();
                $plugin->setResponse($response);
                $plugin->routeShutdown($request);
                $route = [$request->getModuleName(), $request->getControllerName(), $request->getActionName()];
                if ($admin) {
                    check($route === ['admin', $controller, 'edit'], 'Administrator route changed');
                    check($response->getHttpResponseCode() === 200, 'Administrator blocked');
                } else {
                    check($route === ['default', 'error', 'forbidden'], 'Denied admin route can still dispatch');
                    check($response->getHttpResponseCode() === 403, 'Missing HTTP 403');
                }
                $checks++;
            }
        }
    }
}

// Exercise the real response action, including controller initialization and helpers.
Zend_Layout::startMvc();
$request = new Zend_Controller_Request_Http();
$request->setModuleName('default')->setControllerName('error')->setActionName('forbidden')->setDispatched(true);
$response = new Zend_Controller_Response_Http();
Zend_Controller_Front::getInstance()
    ->setControllerDirectory($root . '/application/controllers')
    ->setRequest($request)->setResponse($response);
$controller = new ErrorController($request, $response);
$controller->dispatch('forbiddenAction');
check($response->getHttpResponseCode() === 403, 'Forbidden action lost HTTP 403');
check($response->getBody() === 'Forbidden', 'Forbidden action returned unexpected content');
check(!Zend_Layout::getMvcInstance()->isEnabled(), 'Forbidden action renders layout');
check(Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->getNoRender(), 'Forbidden action renders view');
echo 'PASS: ' . $checks . " ACL cases and forbidden response dispatch\n";
