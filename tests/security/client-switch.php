<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/client-switch.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});
require_once $root . '/application/models/DbTable/Client.php';
require_once $root . '/application/modules/users/models/DbTable/User.php';
require_once $root . '/application/modules/users/controllers/UserController.php';
require_once $root . '/application/forms/Client.php';
require_once $root . '/application/controllers/helpers/MainMenu.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

require_once __DIR__ . '/fixtures/client-switch.php';

Zend_Session::$_unitTestEnabled = true;
Zend_Session::start();
$adapter = new ClientSwitchFixtureAdapter(['host'=>'unused', 'username'=>'unused', 'password'=>'unused', 'dbname'=>'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
$adapter->clients = [
    ['id'=>1, 'parentid'=>0, 'company'=>'Parent', 'deleted'=>0],
    ['id'=>2, 'parentid'=>1, 'company'=>'Home', 'deleted'=>0],
    ['id'=>3, 'parentid'=>1, 'company'=>'Sibling', 'deleted'=>0],
    ['id'=>4, 'parentid'=>2, 'company'=>'Child', 'deleted'=>0],
    ['id'=>5, 'parentid'=>2, 'company'=>'Deleted child', 'deleted'=>1],
    ['id'=>6, 'parentid'=>0, 'company'=>'Unrelated', 'deleted'=>0],
    ['id'=>7, 'parentid'=>4, 'company'=>'Grandchild', 'deleted'=>0],
];
$adapter->users = [['id'=>42, 'clientid'=>2, 'admin'=>0, 'activated'=>1, 'deleted'=>0]];
$checks = 0;

foreach ([1, 2, 4, 6] as $selected) {
    Zend_Registry::set('User', ['id'=>42, 'clientid'=>$selected, 'admin'=>0]);
    $clients = (new Application_Model_DbTable_Client())->getSwitchableClients();
    check(array_keys($clients) === [1, 2, 4], 'Selected client expanded or changed the home scope');
    check((new Application_Model_DbTable_Client())->getClients(6) === $clients, 'Legacy parent argument expanded access');
    $checks += 2;
}
$adapter->users[0]['admin'] = 1;
check(array_keys((new Application_Model_DbTable_Client())->getSwitchableClients()) === [1, 2, 3, 4, 6, 7], 'Administrator scope changed or includes deleted clients');
$checks++;
$adapter->users[0]['admin'] = 0;
foreach (['activated'=>0, 'deleted'=>1] as $field => $value) {
    $before = $adapter->users[0];
    $adapter->users[0][$field] = $value;
    check((new Application_Model_DbTable_Client())->getSwitchableClients() === [], 'Disabled account can switch');
    $adapter->users[0] = $before;
    $checks++;
}
$account = $adapter->users;
$adapter->users = [];
check((new Application_Model_DbTable_Client())->getSwitchableClients() === [], 'Missing account did not fail closed');
$adapter->users = $account;
$checks++;
$adapter->clients[1]['deleted'] = 1;
check((new Application_Model_DbTable_Client())->getSwitchableClients() === [], 'Deleted home client retained switch access');
$adapter->clients[1]['deleted'] = 0;
$adapter->users[0]['clientid'] = 1;
check(array_keys((new Application_Model_DbTable_Client())->getSwitchableClients()) === [1, 2, 3], 'Parent account lost direct children or gained unrelated clients');
$adapter->users[0]['clientid'] = 2;
$checks += 2;

Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_MainMenu());
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default'=>$root . '/application/controllers',
    'users'=>$root . '/application/modules/users/controllers',
]);
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

function switchClient(string $method, array $post, bool $authenticated = true, array $params = []): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = [];
    $auth = Zend_Auth::getInstance();
    $auth->clearIdentity();
    if ($authenticated) $auth->getStorage()->write((object)['id'=>42, 'clientid'=>2]);
    Zend_Registry::set('User', ['id'=>42, 'clientid'=>2, 'admin'=>0]);
    $session = new Zend_Session_Namespace('Zend_Auth');
    $session->storage = (object)['id'=>42, 'clientid'=>2];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('users')->setControllerName('user')->setActionName('client')
        ->setParams(array_merge(['module'=>'users', 'controller'=>'user', 'action'=>'client'], $params))->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $controller = new Users_UserController($request, $response);
    $controller->clientAction();
    return [$response->getHttpResponseCode(), $session->storage->clientid];
}

$form = new Application_Form_Client();
$token = $form->getValue('client_csrf');
check(strlen($token) === 64, 'Missing client switch token');
check((new Application_Form_Client())->getValue('client_csrf') === $token, 'Token changed between requests or tabs');
check(str_contains($form->getElement('client_csrf')->render(new Zend_View()), $token), 'Token is not rendered');
$checks += 3;
foreach (['GET', 'PUT', 'DELETE'] as $method) {
    check(switchClient($method, ['clientid'=>'4', 'client_csrf'=>$token]) === [405, 2], 'Non-POST changed session');
    $checks++;
}
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$token], false) === [401, 2], 'Guest changed session');
$checks++;
foreach ([
    [], ['clientid'=>'4'], ['clientid'=>'4', 'client_csrf'=>'wrong'],
    ['clientid'=>'4', 'client_csrf'=>[$token]],
    ['clientid'=>['4'], 'client_csrf'=>$token],
    ['client_csrf'=>$token],
] as $post) {
    check(switchClient('POST', $post) === [403, 2], 'Missing or malformed input changed session');
    $checks++;
}
foreach (['0', '-1', '4extra', '3', '5', '6', '7', '999'] as $id) {
    check(switchClient('POST', ['clientid'=>$id, 'client_csrf'=>$token]) === [403, 2], 'Unauthorized client accepted: ' . $id);
    $checks++;
}
check(switchClient('POST', ['client_csrf'=>$token], true, ['clientid'=>4]) === [403, 2], 'Route parameter bypassed POST validation');
$checks++;
foreach (['1', '2', '4'] as $id) {
    check(switchClient('POST', ['clientid'=>$id, 'client_csrf'=>$token]) === [200, (int)$id], 'Allowed client rejected: ' . $id);
    $checks++;
}
foreach (['FileManager', 'MediaManager'] as $manager) {
    $session = new Zend_Session_Namespace($manager);
    $session->context = ['clientid' => 2, 'ownerid' => 1];
    $session->clipboard = ['path' => 'old-tenant.pdf'];
    $session->csrf_token = 'old-token';
}
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$token]) === [200, 4], 'Client switch failed');
foreach (['FileManager', 'MediaManager'] as $manager) {
    $session = new Zend_Session_Namespace($manager);
    check(!isset($session->context) && !isset($session->clipboard) && !isset($session->csrf_token), 'Switch retained old manager authorization: ' . $manager);
    $checks++;
}
$checks++;
$adapter->users[0]['admin'] = 1;
check(switchClient('POST', ['clientid'=>'6', 'client_csrf'=>$token]) === [200, 6], 'Administrator cannot switch to unrelated active client');
$checks++;

$csrfSession = new Zend_Session_Namespace('ClientSwitch');
unset($csrfSession->token);
$otherToken = (new Application_Form_Client())->getValue('client_csrf');
check($otherToken !== $token, 'New session reused an old token');
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$token]) === [403, 2], 'Token from another session was accepted');
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$otherToken]) === [200, 4], 'Current session token was rejected');
$checks += 3;

$beforeLogout = (new Application_Form_Client())->getValue('client_csrf');
DEEC_Session::logout();
check(!isset($csrfSession->token), 'Logout retained the client switch token');
$afterLogout = (new Application_Form_Client())->getValue('client_csrf');
check($afterLogout !== $beforeLogout, 'Logout reused the previous client switch token');
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$beforeLogout]) === [403, 2], 'Token survived logout and reauthentication');
DEEC_Session::login(false);
check(!isset($csrfSession->token), 'Login retained the previous client switch token');
$afterLogin = (new Application_Form_Client())->getValue('client_csrf');
check($afterLogin !== $afterLogout, 'Login reused a preauthentication token');
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$afterLogout]) === [403, 2], 'Preauthentication token was accepted after login');
check(switchClient('POST', ['clientid'=>'4', 'client_csrf'=>$afterLogin]) === [200, 4], 'Fresh login token was rejected');
$checks += 7;

echo 'PASS: ' . $checks . " client scope, CSRF and controller cases\n";
