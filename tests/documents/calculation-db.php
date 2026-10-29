<?php

// Run with: python3 tests/documents/calculation-db.py (isolated MariaDB, no existing databases).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function ($class) use ($root) {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class);
    if (count($parts) < 3) return;
    $module = array_shift($parts);
    $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
    if (is_file($path)) require_once $path;
});
foreach (['Calculate', 'Currency', 'PriceRule'] as $name) require_once $root . '/application/controllers/helpers/' . $name . '.php';
require_once $root . '/application/modules/sales/controllers/PositionController.php';
class DEEC_Pdf { public function generate(array $request): array { return $request; } }
class DbIssueController extends DEEC_Controller_DocumentAction {
    public function init() { $this->_user = Zend_Registry::get('User'); }
    public function issue(int $id) { return $this->generatePdfDocument($id, ['finalize' => true]); }
}
class DbPositionController extends Sales_PositionController {
    public function init() { $this->_user = Zend_Registry::get('User'); $this->view = new Zend_View(); }
}
class FailingDbAdapter extends Zend_Db_Adapter_Pdo_Mysql {
    public bool $failQuote = false;
    public bool $failIncrement = false;
    public function update($table, array $bind, $where = '') {
        if ($this->failQuote && $table === 'quote') throw new RuntimeException('Simulated quote persistence failure');
        $affected = parent::update($table, $bind, $where);
        if ($this->failIncrement && $table === 'increment') throw new RuntimeException('Simulated failure after counter persistence');
        return $affected;
    }
}
if (!isset($argv[1])) { echo "SKIP: start an isolated MariaDB with python3 tests/documents/calculation-db.py\n"; exit; }
$socket = $argv[1];
if (!str_starts_with($socket, '/tmp/dewawi-calculation-db-')) throw new RuntimeException('Only isolated test sockets are accepted');
$adapter = new FailingDbAdapter(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_calculation_test', 'charset' => 'utf8mb4']);
if (!isset($argv[2])) {
    $bootstrap = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $bootstrap->exec('CREATE DATABASE dewawi_calculation_test');
    $bootstrap = null;
    $adapter->query('CREATE TABLE quote (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, contactid INT DEFAULT 100, state INT DEFAULT 100, quoteid INT NULL, quotedate DATE NULL, language VARCHAR(10) DEFAULT "de", filename VARCHAR(255), completed INT DEFAULT 0, completeddate DATETIME NULL, completedby INT DEFAULT 0, cancelled INT DEFAULT 0, taxfree INT DEFAULT 0, currency VARCHAR(3) DEFAULT "EUR", subtotal DECIMAL(12,4) DEFAULT 0, taxes DECIMAL(12,4) DEFAULT 0, total DECIMAL(12,4) DEFAULT 0, modified DATETIME NULL, modifiedby INT DEFAULT 0) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE quotepos (id INT AUTO_INCREMENT PRIMARY KEY, clientid INT, parentid INT, title VARCHAR(255) DEFAULT "Fixture position", deleted INT DEFAULT 0, ordering INT, masterid INT NULL, possetid INT DEFAULT 0, pricerulemaster INT DEFAULT 0, price DECIMAL(12,4), quantity DECIMAL(12,4), taxrate DECIMAL(12,4), total DECIMAL(12,4), currency VARCHAR(3) DEFAULT "EUR", modified DATETIME NULL, modifiedby INT DEFAULT 0) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE pricerulepos (id INT AUTO_INCREMENT PRIMARY KEY, clientid INT, parentid INT, module VARCHAR(30), controller VARCHAR(30), deleted INT DEFAULT 0, ordering INT DEFAULT 1, amount DECIMAL(12,4), action VARCHAR(30)) ENGINE=InnoDB');
    // Finalization uses real contacts and position content, as required by the validator.
    $adapter->query('CREATE TABLE contact (id INT PRIMARY KEY, contactid INT, clientid INT, deleted INT DEFAULT 0) ENGINE=InnoDB');
    $adapter->insert('contact', ['id' => 1, 'contactid' => 100, 'clientid' => 7]);
    $adapter->insert('contact', ['id' => 2, 'contactid' => 100, 'clientid' => 8]);
    $adapter->query('CREATE TABLE increment (clientid INT PRIMARY KEY, quoteid INT, invoiceid INT) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE filename (id INT PRIMARY KEY, clientid INT, language VARCHAR(10), quote VARCHAR(255), invoice VARCHAR(255)) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE invoice LIKE quote');
    $adapter->query('ALTER TABLE invoice CHANGE quoteid invoiceid INT NULL, CHANGE quotedate invoicedate DATE NULL');
    $adapter->query('CREATE TABLE invoicepos LIKE quotepos');
    $adapter->query('CREATE TABLE taxrate (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, rate DECIMAL(12,4)) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE uom (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, ordering INT DEFAULT 1, title VARCHAR(30)) ENGINE=InnoDB');
    $adapter->insert('quote', ['id' => 1, 'clientid' => 7]);
    $adapter->insert('quotepos', ['id' => 10, 'clientid' => 7, 'parentid' => 1, 'ordering' => 1, 'pricerulemaster' => 1, 'price' => 100, 'quantity' => 2, 'taxrate' => 19]);
    $adapter->insert('quotepos', ['id' => 11, 'clientid' => 7, 'parentid' => 1, 'ordering' => 1, 'masterid' => 10, 'price' => 20, 'quantity' => 3, 'taxrate' => 19]);
    $adapter->insert('pricerulepos', ['id' => 20, 'clientid' => 7, 'parentid' => 10, 'module' => 'sales', 'controller' => 'quotepos', 'amount' => 10, 'action' => 'bypercent']);
    $adapter->insert('increment', ['clientid' => 7, 'quoteid' => 1001, 'invoiceid' => 7300]);
    $adapter->insert('increment', ['clientid' => 8, 'quoteid' => 2001, 'invoiceid' => 7300]);
    $adapter->insert('filename', ['id' => 1, 'clientid' => 7, 'language' => 'de', 'quote' => 'quote_%NUMBER%.pdf', 'invoice' => 'invoice_%NUMBER%.pdf']);
    $adapter->insert('filename', ['id' => 2, 'clientid' => 8, 'language' => 'de', 'invoice' => 'tenant8_%NUMBER%.pdf']);
    foreach ([1 => 7, 2 => 7, 3 => 8, 4 => 7, 5 => 7, 6 => 7] as $id => $clientId) {
        $adapter->insert('invoice', ['id' => $id, 'clientid' => $clientId, 'deleted' => (int)($id === 5), 'total' => 999]);
        $adapter->insert('invoicepos', ['id' => 100 + $id, 'clientid' => $clientId, 'parentid' => $id, 'ordering' => 1, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 999]);
    }
    $adapter->update('invoice', ['invoiceid' => 6100, 'filename' => 'legacy.pdf', 'state' => 105], ['id = ?' => 6]);
    $adapter->insert('taxrate', ['id' => 1, 'clientid' => 7, 'rate' => 19]);
    $adapter->insert('uom', ['id' => 1, 'clientid' => 7, 'title' => 'Piece']);
}
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42, 'name' => 'Fixture']);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers', 'sales' => $root . '/application/modules/sales/controllers']);
foreach (['Calculate', 'Currency', 'PriceRule'] as $name) { $class = 'Application_Controller_Action_Helper_' . $name; Zend_Controller_Action_HelperBroker::addHelper(new $class()); }
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
function requestController($mode, string $document = 'quote', int $id = 1, int $clientId = 7) {
    global $adapter;
    Zend_Registry::set('Client', ['id' => $clientId]);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('sales')->setControllerName($mode === 'issue' ? $document : 'position')->setActionName($mode === 'issue' ? 'save' : 'edit')->setParams(['id' => $mode === 'issue' ? $id : 10, 'parent' => 'quote', 'parentid' => 1])->setPost(['price' => '200,00']);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    if ($mode === 'issue') { (new DbIssueController($request, $response))->issue($id); return $adapter->fetchRow('SELECT * FROM ' . $adapter->quoteIdentifier($document) . ' WHERE id = ?', [$id]); }
    (new DbPositionController($request, $response))->editAction();
    return json_decode($response->getBody(), true);
}
if (isset($argv[2])) { echo json_encode(requestController($argv[2], $argv[3], (int)$argv[4], (int)$argv[5])); exit; }
function verify($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function worker($mode, string $document = 'quote', int $id = 1, int $clientId = 7) {
    global $socket;
    $process = proc_open([PHP_BINARY, __FILE__, $socket, $mode, $document, (string)$id, (string)$clientId], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    return [$process, $pipes];
}
function finishWorker($job) {
    [$process, $pipes] = $job;
    $deadline = microtime(true) + 10;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) break;
        if (microtime(true) >= $deadline) { proc_terminate($process); throw new RuntimeException('Worker timed out'); }
        usleep(20000);
    } while (true);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($process);
    verify($status['exitcode'] === 0, 'Worker failed: ' . $error . $output);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
$result = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct(1, 'sales', 'quote');
verify(abs($result['row']['total'] - 278.46) < .00001, 'Real database discount calculation failed');
$adapter->update('quote', ['taxfree' => 1], ['id = ?' => 1]);
Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct(1, 'sales', 'quote');
verify((float)$adapter->fetchOne('SELECT total FROM quotepos WHERE id = 10') === 180.0, 'Real database tax-free gross was incorrect');
$adapter->update('quote', ['taxfree' => 0], ['id = ?' => 1]);
$before = $adapter->fetchAll('SELECT * FROM quotepos ORDER BY id');
$adapter->failQuote = true;
verify(requestController('edit')['ok'] === false, 'Failed real-database calculation was successful');
verify($adapter->fetchAll('SELECT * FROM quotepos ORDER BY id') === $before, 'Real database rollback left a partial position edit');
$adapter->failQuote = false;

// Two finalizers are held behind the same parent lock and must issue exactly once.
$adapter->beginTransaction();
(new Sales_Model_DbTable_Quote())->getByIdForUpdate(1);
$one = worker('issue'); $two = worker('issue');
usleep(200000);
verify(proc_get_status($one[0])['running'] && proc_get_status($two[0])['running'], 'Finalizers did not wait for the parent lock');
$adapter->commit();
$first = finishWorker($one); $second = finishWorker($two);
verify((int)$first['quoteid'] === 1001 && (int)$second['quoteid'] === 1001, 'Concurrent finalizers allocated different numbers');
verify((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 7') === 1002, 'Concurrent finalization consumed two numbers');
verify((float)$first['total'] === 278.46 && $first['total'] === $second['total'], 'Concurrent finalization saved stale totals');

// A stale editor that reaches the lock after issuance must not change the original.
verify(requestController('edit')['ok'] === false, 'Issued document accepted a stale position edit');
verify((float)$adapter->fetchOne('SELECT price FROM quotepos WHERE id = 10') === 100.0, 'Issued position was changed');

// Different invoices must contend on the existing tenant counter, not just their own row.
$adapter->beginTransaction();
$adapter->fetchRow('SELECT * FROM increment WHERE clientid = ? FOR UPDATE', [7]);
$one = worker('issue', 'invoice', 1); $two = worker('issue', 'invoice', 2);
$deadline = microtime(true) + 5;
do {
    $waiters = (int)$adapter->fetchOne("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID <> CONNECTION_ID() AND COMMAND = 'Query' AND INFO LIKE '%increment%'");
    if ($waiters === 2) break;
    usleep(20000);
} while (microtime(true) < $deadline);
if ($waiters !== 2) {
    $finished = [];
    foreach ([$one, $two] as $job) if (!proc_get_status($job[0])['running']) $finished[] = finishWorker($job);
    throw new RuntimeException('The two invoice finalizers did not reach the counter concurrently: ' . json_encode([$finished, $adapter->fetchAll('SELECT ID, COMMAND, STATE, INFO FROM information_schema.PROCESSLIST')]));
}

// Another tenant can finish while both invoices of the first tenant are still blocked.
$other = finishWorker(worker('issue', 'invoice', 3, 8));
verify((int)$other['invoiceid'] === 7300 && $other['filename'] === 'tenant8_7300.pdf', 'Another tenant did not use its own counter and filename');
verify(proc_get_status($one[0])['running'] && proc_get_status($two[0])['running'], 'First tenant finalizers escaped the counter lock');
$adapter->commit();
$first = finishWorker($one); $second = finishWorker($two);
$numbers = [(int)$first['invoiceid'], (int)$second['invoiceid']];
sort($numbers);
verify($numbers === [7300, 7301], 'Different concurrent invoices received duplicate or unexpected numbers');
foreach ([$first, $second, $other] as $issued) {
    verify((int)$issued['completed'] === 1 && (int)$issued['state'] === 105 && (int)$issued['completedby'] === 42 && !empty($issued['completeddate']), 'Concurrent invoice was only partially finalized');
    verify((float)$issued['total'] === 119.0, 'Concurrent invoice retained stale totals');
}
verify((int)$adapter->fetchOne('SELECT invoiceid FROM increment WHERE clientid = 7') === 7302, 'First tenant counter did not advance twice');
verify((int)$adapter->fetchOne('SELECT invoiceid FROM increment WHERE clientid = 8') === 7301, 'Second tenant counter was changed by the first tenant');
verify(requestController('issue', 'invoice', 1) === $first, 'Repeated finalization changed the first invoice');
verify(requestController('issue', 'invoice', 2) === $second, 'Repeated finalization changed the second invoice');
$legacy = $adapter->fetchRow('SELECT * FROM invoice WHERE id = 6');
verify(requestController('issue', 'invoice', 6) === $legacy, 'Finalization changed existing document data');
verify((int)$adapter->fetchOne('SELECT invoiceid FROM increment WHERE clientid = 7') === 7302, 'Repeated finalization consumed a number');

// Fail after both document and counter writes; calculation and finalization must roll back together.
$draft = $adapter->fetchRow('SELECT * FROM invoice WHERE id = 4');
$positions = $adapter->fetchAll('SELECT * FROM invoicepos ORDER BY id');
$counters = $adapter->fetchAll('SELECT * FROM increment ORDER BY clientid');
$adapter->failIncrement = true;
try {
    requestController('issue', 'invoice', 4);
    throw new LogicException('Failed finalization succeeded');
} catch (RuntimeException $expected) {
    verify($expected->getMessage() === 'Simulated failure after counter persistence', 'Unexpected finalization failure');
} finally {
    $adapter->failIncrement = false;
}
verify($adapter->fetchRow('SELECT * FROM invoice WHERE id = 4') === $draft, 'Rollback retained a partially finalized invoice');
verify($adapter->fetchAll('SELECT * FROM invoicepos ORDER BY id') === $positions, 'Rollback retained recalculated positions');
verify($adapter->fetchAll('SELECT * FROM increment ORDER BY clientid') === $counters, 'Rollback consumed or changed a tenant counter');
$retried = requestController('issue', 'invoice', 4);
verify((int)$retried['invoiceid'] === 7302 && $retried['filename'] === 'invoice_7302.pdf', 'Retry did not reuse the rolled-back number');

// Tenant and soft-delete access checks must run before any counter reservation.
$invoices = $adapter->fetchAll('SELECT * FROM invoice ORDER BY id');
$counters = $adapter->fetchAll('SELECT * FROM increment ORDER BY clientid');
foreach ([[1, 8], [3, 7], [5, 7], [999, 7]] as [$id, $clientId]) {
    try {
        requestController('issue', 'invoice', $id, $clientId);
        throw new LogicException('Inaccessible invoice was finalized');
    } catch (RuntimeException $expected) {
        verify($expected->getMessage() === 'Document not found', 'Unexpected invoice access failure');
    }
}
verify($adapter->fetchAll('SELECT * FROM invoice ORDER BY id') === $invoices && $adapter->fetchAll('SELECT * FROM increment ORDER BY clientid') === $counters, 'Denied finalization changed documents or counters');
echo "PASS: isolated MariaDB calculation, tax exemption, rollback, parent lock, same-document concurrency and stale edit rejection\n";
echo "PASS: parallel distinct invoices, independent tenants, existing numbers, repeat finalization, counter rollback/retry and denied access\n";
