<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/profile-save.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
    }
});
require_once $root . '/application/modules/users/controllers/UserController.php';
require_once $root . '/application/modules/users/forms/User.php';
require_once $root . '/application/modules/users/forms/Password.php';
require_once $root . '/application/controllers/helpers/Options.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

// Capture writes without opening a database connection.
class Users_Model_DbTable_User {
    public static array $writes = [];
    public static string $password;
    public function getUser($id) {
        check((int)$id === 42, 'Controller read another user');
        return ['id'=>42, 'locked'=>0, 'lockedtime'=>null, 'password'=>self::$password];
    }
    public function updateUser($id, $data, $modifiedby = null) {
        self::$writes[] = ['id'=>$id, 'data'=>$data];
    }
}
class ProfileTestAccess extends Zend_Controller_Action_Helper_Abstract {
    public static array $locks = [];
    public function getName() { return 'Access'; }
    public function lock($id, $userid, $locked, $lockedtime) { self::$locks[] = [$id, $userid]; }
}
class ProfileTestMainMenu extends Zend_Controller_Action_Helper_Abstract {
    public function getName() { return 'MainMenu'; }
    public function getMainMenu() { return []; }
}
class ProfileTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract {
    public function getName() { return 'FlashMessenger'; }
    public function getMessages() { return []; }
    public function getCurrentMessages() { return []; }
    public function clearCurrentMessages() { return $this; }
}

Zend_Controller_Action_HelperBroker::addHelper(new ProfileTestAccess());
Zend_Controller_Action_HelperBroker::addHelper(new ProfileTestMainMenu());
Zend_Controller_Action_HelperBroker::addHelper(new ProfileTestFlashMessenger());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Options());
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default'=>$root . '/application/controllers',
    'users'=>$root . '/application/modules/users/controllers',
]);
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
Users_Model_DbTable_User::$password = password_hash('current-test-password', PASSWORD_DEFAULT);

function saveProfile(array $post, string $action = 'edit'): array {
    Users_Model_DbTable_User::$writes = [];
    ProfileTestAccess::$locks = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = [];
    $request = new Zend_Controller_Request_Http();
    // Request identifiers must never select a different user or tenant.
    $request->setModuleName('users')->setControllerName('user')->setActionName($action)
        ->setParams(['module'=>'users', 'controller'=>'user', 'action'=>$action])
        ->setParam('id', 99)->setParam('clientid', 999)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $controller = new Users_UserController($request, $response);
    ob_start();
    try {
        $controller->{$action . 'Action'}();
        $body = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    return [$response->getHttpResponseCode(), json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}

$checks = 0;
foreach ([0, 1] as $admin) {
    Zend_Registry::set('User', ['id'=>42, 'admin'=>$admin, 'clientid'=>7]);
    $forbidden = [
        'admin'=>'1', 'clientid'=>'999', 'id'=>'99', 'username'=>'another-user',
        'activated'=>'1', 'deleted'=>'0', 'createdby'=>'99', 'modifiedby'=>'99',
        'password'=>'new-password', 'stayLoggedIn'=>'1', 'submit'=>'Save', 'unknown'=>'value',
    ];
    foreach ($forbidden as $field => $value) {
        foreach ([['name'=>'Updated', $field=>$value], [$field=>$value], [$field=>$value, 'name'=>'Updated']] as $post) {
            [$status, $body] = saveProfile($post);
            check($status === 403 && $body['saved'] === false, 'Forbidden field accepted: ' . $field);
            check(Users_Model_DbTable_User::$writes === [], 'Forbidden request changed user data: ' . $field);
            check(ProfileTestAccess::$locks === [], 'Forbidden request locked a user: ' . $field);
            $checks++;
        }
    }
    foreach ([
        'name'=>'Updated', 'email'=>'profile@example.invalid', 'emailsender'=>'Sender',
        'smtphost'=>'smtp.example.invalid', 'smtpauth'=>'1', 'smtpsecure'=>'tls',
        'smtpuser'=>'profile', 'smtppass'=>'smtp-test-password',
    ] as $field => $value) {
        [$status, $body] = saveProfile([$field=>$value]);
        check($status === 200 && $body['saved'] === true, 'Allowed field rejected: ' . $field);
        check(Users_Model_DbTable_User::$writes === [['id'=>42, 'data'=>[$field=>$value]]], 'Unexpected profile write: ' . $field);
        check(ProfileTestAccess::$locks === [[42, 42]], 'Profile lock targets another user');
        $checks++;
    }
    foreach ([[], ['email'=>'invalid-email']] as $post) {
        [$status, $body] = saveProfile($post);
        check($body['saved'] === false && Users_Model_DbTable_User::$writes === [], 'Invalid profile saved');
        $checks++;
    }
    foreach (['password', 'smtppass'] as $field) {
        [$status, $body] = saveProfile([$field=>'xxxxxxxxxx']);
        check($status === 200 && $body['saved'] === true, 'Password placeholder rejected');
        check(Users_Model_DbTable_User::$writes === [], 'Password placeholder was stored');
        $checks++;
    }
    [$status, $body] = saveProfile(['name'=>'Updated', 'email'=>'profile@example.invalid']);
    check($status === 200 && $body['saved'] === true, 'Multiple allowed fields rejected');
    check(Users_Model_DbTable_User::$writes === [['id'=>42, 'data'=>['name'=>'Updated', 'email'=>'profile@example.invalid']]], 'Multiple fields saved incorrectly');
    $checks++;
}

// Password changes still require the current password and store a hash.
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>7]);
[$status, $body] = saveProfile([
    'passwordactual'=>'current-test-password', 'passwordnew'=>'new-test-password',
    'passwordconfirm'=>'new-test-password', 'admin'=>'1', 'clientid'=>'999',
], 'password');
$writes = Users_Model_DbTable_User::$writes;
check($status === 200 && $body['saved'] === true, 'Password change failed');
check(count($writes) === 1 && $writes[0]['id'] === 42, 'Password change targets another user');
check(array_keys($writes[0]['data']) === ['password'], 'Password change persisted extra fields');
check(password_verify('new-test-password', $writes[0]['data']['password']), 'Password was not hashed');
$checks++;
[$status, $body] = saveProfile([
    'passwordactual'=>'wrong-password', 'passwordnew'=>'new-test-password',
    'passwordconfirm'=>'new-test-password',
], 'password');
check(Users_Model_DbTable_User::$writes === [], 'Wrong current password allowed an update');
$checks++;
echo 'PASS: ' . $checks . " profile and password security cases\n";
