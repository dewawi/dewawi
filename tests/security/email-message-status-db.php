<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: python3 tests/security/email-message-status-db.py (isolated MariaDB only).
if (!isset($argv[1])) {
    echo "SKIP: run python3 tests/security/email-message-status-db.py\n";
    exit;
}
if (!str_starts_with($argv[1], '/tmp/dewawi-email-message-status-db-')) {
    throw new RuntimeException('Only isolated test sockets are accepted');
}

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
        return;
    }
    $parts = explode('_', $class);
    if (count($parts) >= 3) {
        $module = array_shift($parts);
        $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
        $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
        if (is_file($path)) require_once $path;
    }
});

class EmailMessageStatusTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $updates = [];

    public function update($table, array $bind, $where = '')
    {
        $this->updates[] = ['table' => $table, 'bind' => $bind, 'where' => (array)$where];
        return parent::update($table, $bind, $where);
    }
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

$socket = $argv[1];
$bootstrap = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bootstrap->exec('CREATE DATABASE dewawi_email_message_status_test');
$bootstrap = null;
$adapter = new EmailMessageStatusTestAdapter([
    'host' => 'localhost',
    'unix_socket' => $socket,
    'username' => 'root',
    'password' => '',
    'dbname' => 'dewawi_email_message_status_test',
    'charset' => 'utf8mb4',
]);

$structure = file_get_contents($root . '/install/config/structure.sql');
$start = strpos($structure, 'CREATE TABLE IF NOT EXISTS `emailmessage`');
$end = $start === false ? false : strpos($structure, ') ENGINE=InnoDB', $start);
if ($start === false || $end === false) throw new RuntimeException('Email message schema was not found');
$end = strpos($structure, ';', $end);
$adapter->query(substr($structure, $start, $end - $start + 1));

Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42]);
$model = new Contacts_Model_DbTable_Emailmessage();

function addEmailMessage(EmailMessageStatusTestAdapter $adapter, int $clientId, string $response = 'pending', int $deleted = 0): int
{
    $adapter->insert('emailmessage', [
        'contactid' => 100,
        'documentid' => 200,
        'module' => 'sales',
        'controller' => 'quote',
        'sender' => 'sender@example.invalid',
        'recipient' => 'recipient@example.invalid',
        'subject' => 'Fixture',
        'body' => 'Fixture',
        'clientid' => $clientId,
        'messagesent' => '2026-10-29 12:00:00',
        'messagesentby' => 42,
        'response' => $response,
        'deleted' => $deleted,
    ]);
    return (int)$adapter->lastInsertId();
}

$columns = array_column($adapter->fetchAll('SHOW COLUMNS FROM emailmessage'), 'Field');
check(!in_array('modified', $columns, true) && !in_array('modifiedby', $columns, true), 'Fixture schema unexpectedly contains audit update columns');

$sentId = addEmailMessage($adapter, 7);
$model->updateEmailmessage($sentId, ['response' => 'sent']);
check($adapter->fetchOne('SELECT response FROM emailmessage WHERE id = ?', [$sentId]) === 'sent', 'Successful SMTP status remained pending');
check($adapter->updates[0]['bind'] === ['response' => 'sent'], 'Status update added columns that are absent from emailmessage');

$failedId = addEmailMessage($adapter, 7);
$model->updateEmailmessage($failedId, ['response' => 'Fixture SMTP rejection']);
check($adapter->fetchOne('SELECT response FROM emailmessage WHERE id = ?', [$failedId]) === 'Fixture SMTP rejection', 'Negative SMTP status was not stored');

$otherTenantId = addEmailMessage($adapter, 8);
$model->updateEmailmessage($otherTenantId, ['response' => 'sent']);
check($adapter->fetchOne('SELECT response FROM emailmessage WHERE id = ?', [$otherTenantId]) === 'pending', 'Status update crossed the tenant boundary');

$deletedId = addEmailMessage($adapter, 7, 'pending', 1);
$model->updateEmailmessage($deletedId, ['response' => 'sent']);
check($adapter->fetchOne('SELECT response FROM emailmessage WHERE id = ?', [$deletedId]) === 'pending', 'Status update changed a soft-deleted message');

$deleteId = addEmailMessage($adapter, 7);
$model->deleteEmailmessage($deleteId);
check((int)$adapter->fetchOne('SELECT deleted FROM emailmessage WHERE id = ?', [$deleteId]) === 1, 'Email message deletion failed');
$model->deleteEmailmessage($otherTenantId);
check((int)$adapter->fetchOne('SELECT deleted FROM emailmessage WHERE id = ?', [$otherTenantId]) === 0, 'Email message deletion crossed the tenant boundary');

foreach ($adapter->updates as $update) {
    check(!isset($update['bind']['modified'], $update['bind']['modifiedby']), 'Email message update wrote nonexistent audit columns');
    $where = implode(' ', $update['where']);
    check(str_contains($where, 'clientid = 7'), 'Email message update lacks the tenant filter');
    check(str_contains($where, 'deleted = 0'), 'Email message update lacks the soft-delete filter');
}

check((int)$adapter->fetchOne('SELECT COUNT(*) FROM emailmessage') === 5, 'Status handling retried or duplicated an email message');
$controllerSource = file_get_contents($root . '/application/modules/contacts/controllers/EmailController.php');
check(str_contains($controllerSource, "error_log('Email send action failed: '"), 'Send action exceptions are not logged');

echo "PASS: {$checks} real-schema email message status, tenant and deletion checks\n";
