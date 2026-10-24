<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/user-profile.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
    }
});
require_once $root . '/application/modules/users/controllers/UserController.php';
require_once $root . '/application/modules/users/models/DbTable/User.php';
require_once $root . '/application/modules/users/forms/User.php';
require_once $root . '/application/modules/users/forms/Password.php';

class ProfileTestAdapter extends Zend_Test_DbAdapter
{
    public array $updates = [];

    public function update($table, array $bind, $where = '')
    {
        $this->updates[] = ['table' => $table, 'data' => $bind, 'where' => $where];
        return 1;
    }
}

class ProfileTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function getMessages() { return []; }
    public function getCurrentMessages() { return []; }
    public function clearCurrentMessages() { return $this; }
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper_');
Zend_Controller_Action_HelperBroker::addHelper(new ProfileTestFlashMessenger());
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 0]);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Layout::startMvc();

$user = [
    'id' => 42, 'clientid' => 7, 'admin' => 0, 'activated' => 1, 'deleted' => 0,
    'username' => 'profile-test', 'name' => 'Original', 'email' => 'original@example.test',
    'password' => password_hash('Current-password-1', PASSWORD_DEFAULT),
    'emailsender' => '', 'smtphost' => '', 'smtpauth' => '', 'smtpsecure' => '',
    'smtpuser' => '', 'smtppass' => 'Stored SMTP password', 'locked' => 0, 'lockedtime' => null,
];

function profileRequest(array $post, array $user, string $action = 'edit', bool $ajax = true): array
{
    $adapter = new ProfileTestAdapter();
    $metadata = [];
    foreach (array_keys($user) as $column) {
        $metadata[$column] = [
            'COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar',
            'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id',
        ];
    }
    $adapter->setDescribeTable('user', $metadata);
    $adapter->appendStatementToStack(Zend_Test_DbStatement::createSelectStatement([$user]));
    Zend_Db_Table::setDefaultAdapter($adapter);

    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
    $request = new Zend_Controller_Request_Http();
    // A supplied ID from another tenant must never select the profile being updated.
    $request->setModuleName('users')->setControllerName('user')->setActionName($action)
        ->setParams(['module' => 'users', 'controller' => 'user', 'action' => $action])
        ->setParam('id', 99)->setDispatched(true);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()
        ->setControllerDirectory(dirname(__DIR__, 2) . '/application/modules/users/controllers', 'users')
        ->setRequest($request)->setResponse($response);
    $controller = new Users_UserController($request, $response);

    ob_start();
    try {
        $controller->dispatch($action . 'Action');
        $body = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    $updates = [];
    foreach ($adapter->updates as $update) {
        check($update['table'] === 'user' && $update['where'] === 'id = 42', 'Update targeted another user');
        $data = $update['data'];
        if (array_keys($data) === ['locked', 'lockedtime']) continue;
        check(($data['modifiedby'] ?? null) === 42, 'Audit user was overwritten');
        unset($data['modified'], $data['modifiedby']);
        $updates[] = $data;
    }

    return [Zend_Json::decode($body, Zend_Json::TYPE_ARRAY), $updates, $response->getHttpResponseCode()];
}

$cases = [
    ['name', ['name' => 'Updated'], ['name' => 'Updated'], true],
    ['email', ['email' => 'updated@example.test'], ['email' => 'updated@example.test'], true],
    ['invalid email', ['email' => 'invalid'], [], false],
    ['empty request', [], [], false],
    ['password placeholder', ['password' => 'xxxxxxxxxx'], [], true],
    ['SMTP placeholder', ['smtppass' => 'xxxxxxxxxx'], [], true],
    ['SMTP placeholder with name', ['smtppass' => 'xxxxxxxxxx', 'name' => 'Updated'], ['name' => 'Updated'], true],
];
$smtp = [
    'emailsender' => 'Sender', 'smtphost' => 'smtp.example.test', 'smtpauth' => 'login',
    'smtpsecure' => 'tls', 'smtpuser' => 'mailer', 'smtppass' => 'New SMTP password',
];
foreach ($smtp as $field => $value) {
    $cases[] = [$field, [$field => $value], [$field => $value], true];
}
$forbidden = [
    'admin' => 1, 'clientid' => 99, 'id' => 99, 'activated' => 0, 'deleted' => 1,
    'username' => 'other-user', 'password' => 'Injected password',
    'created' => '2000-01-01', 'createdby' => 99, 'modified' => '2000-01-01', 'modifiedby' => 99,
    'emailsignature' => 'Injected signature', 'stayLoggedIn' => 1, 'submit' => 'Save', 'unknown' => 'value',
];
foreach ($forbidden as $field => $value) {
    $cases[] = [$field . ' alone', [$field => $value], [], false];
    $cases[] = [$field . ' first', [$field => $value, 'name' => 'Updated'], [], false];
    $cases[] = [$field . ' last', ['name' => 'Updated', $field => $value], [], false];
}
$profile = ['name' => 'Updated', 'email' => 'updated@example.test'] + $smtp;
$cases[] = ['full profile', $profile, $profile, true];
$cases[] = ['full profile with foreign ID', ['id' => 99] + $profile, [], false];

$checks = 0;
foreach ($cases as [$label, $post, $expected, $saved]) {
    [$result, $updates] = profileRequest($post, $user);
    check(($result['saved'] ?? null) === $saved, $label . ': unexpected response');
    check($updates === ($expected ? [$expected] : []), $label . ': unexpected profile fields were written');
    $checks++;
}

// Allowed field names must not admit array values, even alongside valid fields.
foreach (array_keys($profile) as $field) {
    foreach ([[], ['unexpected'], ['nested' => ['unexpected']]] as $value) {
        foreach ([true, false] as $ajax) {
            [$result, $updates, $status] = profileRequest([$field => $value] + ['name' => 'Updated'], $user, 'edit', $ajax);
            check($status === 400 && $result['saved'] === false && !$updates, 'Array profile value accepted: ' . $field);
            $checks++;
        }
    }
}

// Foreign active locks block all profile writes; own and expired locks allow them.
foreach ([true, false] as $ajax) {
    $lockedUser = array_replace($user, ['locked' => 99, 'lockedtime' => date('Y-m-d H:i:s')]);
    [$result, $updates] = profileRequest(['name' => 'Updated'], $lockedUser, 'edit', $ajax);
    check($result['saved'] === false && $result['message'] === 'locked' && !$updates, 'Active foreign lock allowed a profile write');
    $checks++;
    foreach ([[42, date('Y-m-d H:i:s')], [99, date('Y-m-d H:i:s', time() - 600)]] as [$owner, $time]) {
        $unlockedUser = array_replace($user, ['locked' => $owner, 'lockedtime' => $time]);
        [$result, $updates] = profileRequest(['name' => 'Updated'], $unlockedUser, 'edit', $ajax);
        check($result['saved'] === true && $updates === [['name' => 'Updated']], 'Own or expired lock blocked profile write');
        $checks++;
    }
}

// Password changes must continue to use the existing current-password check and hashing.
$passwordPost = [
    'passwordactual' => 'Current-password-1', 'passwordnew' => 'New-password-2',
    'passwordconfirm' => 'New-password-2', 'admin' => 1, 'clientid' => 99, 'id' => 99,
];
[$result, $updates] = profileRequest($passwordPost, $user, 'password');
check(($result['saved'] ?? false) && count($updates) === 1, 'Password change failed');
check(array_keys($updates[0]) === ['password'], 'Password change wrote other fields');
check(password_verify('New-password-2', $updates[0]['password']), 'New password was not hashed');
$checks++;
foreach (['passwordactual' => 'Wrong password', 'passwordconfirm' => 'Different password'] as $field => $value) {
    [$result, $updates] = profileRequest(array_replace($passwordPost, [$field => $value]), $user, 'password');
    check(empty($result['saved']) && !$updates, 'Invalid password change succeeded');
    $checks++;
}

foreach (['passwordactual', 'passwordnew', 'passwordconfirm'] as $field) {
    [$result, $updates, $status] = profileRequest(array_replace($passwordPost, [$field => ['unexpected']]), $user, 'password');
    check($status === 400 && $result['saved'] === false && !$updates, 'Array password value accepted: ' . $field);
    $checks++;
}
foreach ([true, false] as $ajax) {
    [$result, $updates] = profileRequest($passwordPost, $lockedUser, 'password', $ajax);
    check($result['saved'] === false && $result['message'] === 'locked' && !$updates, 'Active foreign lock allowed a password change');
    $checks++;
    foreach ([[42, date('Y-m-d H:i:s')], [99, date('Y-m-d H:i:s', time() - 600)]] as [$owner, $time]) {
        $unlockedUser = array_replace($user, ['locked' => $owner, 'lockedtime' => $time]);
        [$result, $updates] = profileRequest($passwordPost, $unlockedUser, 'password', $ajax);
        check($result['saved'] === true && count($updates) === 1, 'Own or expired lock blocked password change');
        check(array_keys($updates[0]) === ['password'] && password_verify('New-password-2', $updates[0]['password']), 'Password lock handling changed stored fields');
        $checks++;
    }
}

echo 'PASS: ' . $checks . " user profile and password cases\n";
