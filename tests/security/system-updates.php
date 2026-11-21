<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
    if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $file;
});
require $root . '/application/plugins/Acl.php';

class UpdateAccessFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}
Zend_Controller_Action_HelperBroker::addHelper(new UpdateAccessFlashMessenger());
$auth = Zend_Auth::getInstance();
$auth->setStorage(new Zend_Auth_Storage_NonPersistent());
$account = ['id' => 42, 'admin' => 1, 'activated' => 1, 'deleted' => 0];
$cases = [
    ['active admin', $account, (object)['id' => 42], true],
    ['another admin', array_replace($account, ['id' => 43]), (object)['id' => 43], true],
    ['PDO flags', array_replace($account, ['id' => '42', 'admin' => '1', 'activated' => '1', 'deleted' => '0']), (object)['id' => 42], true],
    ['ordinary user', array_replace($account, ['admin' => 0]), (object)['id' => 42], false],
    ['disabled admin', array_replace($account, ['activated' => 0]), (object)['id' => 42], false],
    ['deleted admin', array_replace($account, ['deleted' => 1]), (object)['id' => 42], false],
    ['unknown admin flag', array_replace($account, ['admin' => 2]), (object)['id' => 42], false],
    ['missing admin flag', array_diff_key($account, ['admin' => true]), (object)['id' => 42], false],
    ['missing active flag', array_diff_key($account, ['activated' => true]), (object)['id' => 42], false],
    ['missing deleted flag', array_diff_key($account, ['deleted' => true]), (object)['id' => 42], false],
    ['invalid account ID', array_replace($account, ['id' => 0]), (object)['id' => 42], false],
    ['mismatched identity', $account, (object)['id' => 43], false],
    ['malformed identity', $account, ['id' => 42], false],
    ['guest', $account, null, false],
];
$checks = 0;
$check = static function(bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$check(Zend_Controller_Front::getInstance()->getParam('bootstrap') === null, 'Test must not supply an INI configuration or bootstrap');
foreach ($cases as [$name, $user, $identity, $expected]) {
    foreach ([7, 8, 9] as $tenant) {
        Zend_Registry::set('User', $user + ['clientid' => $tenant]);
        Zend_Registry::set('Client', ['id' => $tenant, 'modules' => ['admin' => 7]]);
        $auth->clearIdentity();
        if ($identity !== null) $auth->getStorage()->write($identity);
        $check(DEEC_SystemOperator::allowed() === $expected, $name . ': permission differs by tenant or requires configuration');
        if ($identity === null) continue;
        foreach (['index' => 'GET', 'approve' => 'POST'] as $action => $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $request = new Zend_Controller_Request_Http();
            $request->setModuleName('admin')->setControllerName('updates')->setActionName($action);
            $response = new Zend_Controller_Response_Http();
            $acl = new Application_Plugin_Acl(); $acl->setResponse($response); $acl->routeShutdown($request);
            $check($request->getModuleName() === ($expected ? 'admin' : 'default') && $response->getHttpResponseCode() === ($expected ? 200 : 403), $name . ': ACL differs from administrator gate');
        }
    }
}
Zend_Registry::getInstance()->offsetUnset('User');
$auth->getStorage()->write((object)['id' => 42]);
$check(!DEEC_SystemOperator::allowed(), 'An authenticated identity without a current account was accepted');
echo 'PASS: ' . $checks . " system update administrator/identity/tenant/ACL cases without user configuration\n";
