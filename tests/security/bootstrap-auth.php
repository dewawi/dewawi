<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/bootstrap-auth.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

// Use the real resource loader, bootstrap and table models with isolated database I/O.
$application = new Zend_Application('testing', [
    'bootstrap' => ['path' => APPLICATION_PATH . '/Bootstrap.php', 'class' => 'Bootstrap'],
    'appnamespace' => 'Application',
    'resources' => [
        'frontController' => [
            'controllerDirectory' => APPLICATION_PATH . '/controllers',
            'moduleDirectory' => APPLICATION_PATH . '/modules',
        ],
        'modules' => [],
        'view' => [],
    ],
]);
require_once __DIR__ . '/fixtures/client-switch.php';
require_once 'DEEC/Session.php';

class BootstrapAuthDbResource extends Zend_Application_Resource_ResourceAbstract {
    public $_explicitType = 'db';
    public ClientSwitchFixtureAdapter $adapter;
    public function init() {
        Zend_Db_Table::setDefaultAdapter($this->adapter);
        return $this->adapter;
    }
}

Zend_Session::$_unitTestEnabled = true;
Zend_Session::start();

$account = [
    'id' => 42, 'clientid' => 2, 'admin' => 0, 'activated' => 1, 'deleted' => 0,
    'username' => 'current-user', 'name' => 'Current name', 'email' => 'current@example.test',
    'emailsender' => 'Current sender', 'emailsignature' => 'Current signature',
    'smtphost' => 'smtp.example.test', 'smtpuser' => 'current-mailer', 'smtppass' => 'fixture-secret',
];
$clients = [
    ['id' => 1, 'parentid' => 0, 'company' => 'Parent', 'deleted' => 0],
    ['id' => 2, 'parentid' => 1, 'company' => 'Home', 'deleted' => 0],
    ['id' => 3, 'parentid' => 1, 'company' => 'Sibling', 'deleted' => 0],
    ['id' => 4, 'parentid' => 2, 'company' => 'Child', 'deleted' => 0],
    ['id' => 5, 'parentid' => 2, 'company' => 'Deleted child', 'deleted' => 1],
    ['id' => 6, 'parentid' => 0, 'company' => 'Other company', 'deleted' => 0],
];

function bootstrapAccount(?array $account, array $clients, $selected, array $identityChanges = [], string $resource = 'Client', bool $legacyIdentity = false): array {
    global $application;
    Zend_Controller_Front::getInstance()->resetInstance();
    Zend_Registry::_unsetInstance();
    $auth = Zend_Auth::getInstance();
    $auth->setStorage(new Zend_Auth_Storage_Session());
    $identity = (object)array_replace([
        'id' => 42, 'clientid' => $selected, 'admin' => 1, 'activated' => 1, 'deleted' => 0,
        'username' => 'old-user', 'name' => 'Old name', 'email' => 'old@example.test',
        'emailsender' => '', 'emailsignature' => '', 'smtphost' => '', 'smtpuser' => '', 'smtppass' => '',
        'language' => 'de_DE',
    ], $identityChanges);
    if ($legacyIdentity) $identity = new Zend_Db_Table_Row(['data' => (array)$identity]);
    $auth->getStorage()->write($identity);
    $_SESSION[DEEC_Session::REMEMBER_KEY] = false;

    $adapter = new ClientSwitchFixtureAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
    $adapter->users = $account ? [$account] : [];
    $adapter->clients = $clients;
    $database = new BootstrapAuthDbResource();
    $database->adapter = $adapter;
    $bootstrap = new Bootstrap($application);
    $bootstrap->registerPluginResource($database);
    $bootstrap->bootstrap($resource);
    return [$bootstrap, $adapter, $auth];
}

$checks = 0;
foreach ([1, 2, 4] as $selected) {
    [$bootstrap, $adapter, $auth] = bootstrapAccount($account, $clients, $selected);
    check($auth->hasIdentity(), 'Authorized client lost authentication');
    $user = Zend_Registry::get('User');
    check($user['clientid'] === $selected && Zend_Registry::get('Client')['id'] === $selected, 'Selected client was reset or not loaded');
    foreach (['admin', 'username', 'name', 'email', 'emailsender', 'emailsignature', 'smtphost', 'smtpuser', 'smtppass'] as $field) {
        check($user[$field] === $account[$field] && $auth->getIdentity()->$field === $account[$field], 'Stale identity field retained: ' . $field);
    }
    check($auth->getIdentity()->language === 'de_DE', 'Session language was lost');
    check($adapter->users[0]['clientid'] === 2, 'Switching modified the account assignment');
    check($bootstrap->hasResource('db') && $bootstrap->hasResource('modules'), 'Authentication dependencies were not bootstrapped');
    if ($selected !== 1) {
        $client = Zend_Registry::get('Client');
        check($client['modules']['contacts'] === $client['parentid'] && $client['modules']['items'] === $client['parentid'], 'Shared contacts or items changed owner');
        foreach (['sales', 'purchases', 'processes', 'statistics'] as $module) {
            check($client['modules'][$module] === $selected, 'Business data changed owner: ' . $module);
        }
    }
    $checks++;
}

// The existing client plugin must still resolve shared data for the real table models.
foreach (['items' => 1, 'contacts' => 1, 'sales' => 2, 'purchases' => 2, 'processes' => 2, 'statistics' => 2] as $module => $owner) {
    [$bootstrap] = bootstrapAccount($account, $clients, 2);
    $bootstrap->bootstrap('view');
    Zend_Controller_Front::getInstance()->setParam('bootstrap', $bootstrap);
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($module)->setParams(['module' => $module]);
    (new Application_Plugin_Client())->routeShutdown($request);
    check(Zend_Registry::get('Client')['id'] === $owner, 'Client plugin changed the data owner: ' . $module);
    check(Zend_Registry::get('User')['clientid'] === 2, 'Client plugin changed the selected account client');
    if ($module === 'items') check((new Items_Model_DbTable_Item())->getClientId() === 1, 'Item model lost parent context');
    if ($module === 'contacts') check((new Contacts_Model_DbTable_Contact())->getClientId() === 1, 'Contact model lost parent context');
    $checks++;
}

[, , $auth] = bootstrapAccount($account, $clients, 4, [], 'Client', true);
check($auth->hasIdentity() && $auth->getIdentity()->clientid === 4 && $auth->getIdentity()->language === 'de_DE', 'Legacy row identity lost its selected client or language');
$checks++;

foreach ([3, 5, 6, 999, 0, -1, '2extra', ['2']] as $selected) {
    [, , $auth] = bootstrapAccount($account, $clients, $selected);
    check(!$auth->hasIdentity(), 'Unauthorized or malformed session client retained authentication');
    foreach (['User', 'Client', 'Config'] as $key) check(!Zend_Registry::isRegistered($key), 'Rejected identity published ' . $key);
    check(!isset($_SESSION[DEEC_Session::REMEMBER_KEY]), 'Rejected identity retained remember flag');
    $checks++;
}

foreach ([null, array_replace($account, ['activated' => 0]), array_replace($account, ['deleted' => 1])] as $invalidAccount) {
    [, , $auth] = bootstrapAccount($invalidAccount, $clients, 2);
    check(!$auth->hasIdentity() && !Zend_Registry::isRegistered('User') && !Zend_Registry::isRegistered('Client'), 'Missing or disabled user retained access');
    $checks++;
}

$deletedHome = $clients;
$deletedHome[1]['deleted'] = 1;
[, , $auth] = bootstrapAccount($account, $deletedHome, 1);
check(!$auth->hasIdentity(), 'Deleted home client retained access to parent');
$checks++;

$movedAccount = array_replace($account, ['clientid' => 6]);
[, , $auth] = bootstrapAccount($movedAccount, $clients, 2);
check(!$auth->hasIdentity(), 'Account reassignment retained old company access');
$checks++;

[, , $auth] = bootstrapAccount($account, $clients, 6, ['admin' => 1]);
check(!$auth->hasIdentity(), 'Revoked administrator retained access to another company');
[, , $auth] = bootstrapAccount(array_replace($account, ['admin' => 1]), $clients, 6, ['admin' => 0]);
check($auth->hasIdentity() && Zend_Registry::get('User')['admin'] === 1 && Zend_Registry::get('Client')['id'] === 6, 'Current administrator lost global access');
$checks += 2;

// Current roles must reach the real ACL, including revocation while staying in an allowed client.
class BootstrapAuthFlashMessenger extends Zend_Controller_Action_Helper_Abstract {
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}
Zend_Controller_Action_HelperBroker::addHelper(new BootstrapAuthFlashMessenger());
bootstrapAccount($account, $clients, 2, ['admin' => 1]);
$request = new Zend_Controller_Request_Http();
$request->setModuleName('admin')->setControllerName('user')->setActionName('edit');
$response = new Zend_Controller_Response_Http();
$acl = new Application_Plugin_Acl();
$acl->setResponse($response);
$acl->routeShutdown($request);
check($response->getHttpResponseCode() === 403, 'Revoked administrator passed the admin ACL');
$checks++;

// Verify that invalid authentication also blocks tenant configuration loading.
[, , $auth] = bootstrapAccount($account, $clients, 6, [], 'Locale');
check(!$auth->hasIdentity() && !Zend_Registry::isRegistered('Config'), 'Locale loaded configuration for an unauthorized client');
$checks++;

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote')->setActionName('edit');
$acl = new Application_Plugin_Acl();
$acl->routeShutdown($request);
check([$request->getModuleName(), $request->getControllerName(), $request->getActionName()] === ['users', 'user', 'session'], 'Rejected AJAX session can still dispatch its original action');
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
$checks++;

// Guests must not trigger account or client queries.
$auth->clearIdentity();
Zend_Registry::_unsetInstance();
$guestBootstrap = new Bootstrap($application);
$guestBootstrap->bootstrap('Client');
check(!$guestBootstrap->hasResource('db') && !Zend_Registry::isRegistered('Client'), 'Guest initialized tenant data');
$checks++;

echo 'PASS: ' . $checks . " bootstrap identity, tenant and sharing cases\n";
