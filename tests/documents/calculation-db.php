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
    public function issue() { return $this->generatePdfDocument(1, ['finalize' => true]); }
}
class DbPositionController extends Sales_PositionController {
    public function init() { $this->_user = Zend_Registry::get('User'); $this->view = new Zend_View(); }
}
class FailingDbAdapter extends Zend_Db_Adapter_Pdo_Mysql {
    public bool $failQuote = false;
    public function update($table, array $bind, $where = '') {
        if ($this->failQuote && $table === 'quote') throw new RuntimeException('Simulated quote persistence failure');
        return parent::update($table, $bind, $where);
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
    $adapter->query('CREATE TABLE quote (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, state INT DEFAULT 100, quoteid INT NULL, quotedate DATE NULL, language VARCHAR(10) DEFAULT "de", filename VARCHAR(255), completed INT DEFAULT 0, completeddate DATETIME NULL, completedby INT DEFAULT 0, cancelled INT DEFAULT 0, taxfree INT DEFAULT 0, currency VARCHAR(3) DEFAULT "EUR", subtotal DECIMAL(12,4) DEFAULT 0, taxes DECIMAL(12,4) DEFAULT 0, total DECIMAL(12,4) DEFAULT 0, modified DATETIME NULL, modifiedby INT DEFAULT 0) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE quotepos (id INT AUTO_INCREMENT PRIMARY KEY, clientid INT, parentid INT, deleted INT DEFAULT 0, ordering INT, masterid INT NULL, possetid INT DEFAULT 0, pricerulemaster INT DEFAULT 0, price DECIMAL(12,4), quantity DECIMAL(12,4), taxrate DECIMAL(12,4), total DECIMAL(12,4), currency VARCHAR(3) DEFAULT "EUR", modified DATETIME NULL, modifiedby INT DEFAULT 0) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE pricerulepos (id INT AUTO_INCREMENT PRIMARY KEY, clientid INT, parentid INT, module VARCHAR(30), controller VARCHAR(30), deleted INT DEFAULT 0, ordering INT DEFAULT 1, amount DECIMAL(12,4), action VARCHAR(30)) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE increment (id INT PRIMARY KEY, clientid INT, quoteid INT) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE filename (id INT PRIMARY KEY, clientid INT, language VARCHAR(10), quote VARCHAR(255)) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE taxrate (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, rate DECIMAL(12,4)) ENGINE=InnoDB');
    $adapter->query('CREATE TABLE uom (id INT PRIMARY KEY, clientid INT, deleted INT DEFAULT 0, ordering INT DEFAULT 1, title VARCHAR(30)) ENGINE=InnoDB');
    $adapter->insert('quote', ['id' => 1, 'clientid' => 7]);
    $adapter->insert('quotepos', ['id' => 10, 'clientid' => 7, 'parentid' => 1, 'ordering' => 1, 'pricerulemaster' => 1, 'price' => 100, 'quantity' => 2, 'taxrate' => 19]);
    $adapter->insert('quotepos', ['id' => 11, 'clientid' => 7, 'parentid' => 1, 'ordering' => 1, 'masterid' => 10, 'price' => 20, 'quantity' => 3, 'taxrate' => 19]);
    $adapter->insert('pricerulepos', ['id' => 20, 'clientid' => 7, 'parentid' => 10, 'module' => 'sales', 'controller' => 'quotepos', 'amount' => 10, 'action' => 'bypercent']);
    $adapter->insert('increment', ['id' => 1, 'clientid' => 7, 'quoteid' => 1001]);
    $adapter->insert('filename', ['id' => 1, 'clientid' => 7, 'language' => 'de', 'quote' => 'quote_%NUMBER%.pdf']);
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
function requestController($mode) {
    global $adapter;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('sales')->setControllerName($mode === 'issue' ? 'quote' : 'position')->setActionName($mode === 'issue' ? 'save' : 'edit')->setParams(['id' => $mode === 'issue' ? 1 : 10, 'parent' => 'quote', 'parentid' => 1])->setPost(['price' => '200,00']);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    if ($mode === 'issue') { (new DbIssueController($request, $response))->issue(); return $adapter->fetchRow('SELECT * FROM quote WHERE id = 1'); }
    (new DbPositionController($request, $response))->editAction();
    return json_decode($response->getBody(), true);
}
if (isset($argv[2])) { echo json_encode(requestController($argv[2])); exit; }
function verify($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function worker($mode) {
    global $socket;
    $process = proc_open([PHP_BINARY, __FILE__, $socket, $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    return [$process, $pipes];
}
function finishWorker($job) {
    [$process, $pipes] = $job;
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    verify(proc_close($process) === 0, 'Worker failed: ' . $error . $output);
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
verify((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE id = 1') === 1002, 'Concurrent finalization consumed two numbers');
verify((float)$first['total'] === 278.46 && $first['total'] === $second['total'], 'Concurrent finalization saved stale totals');

// A stale editor that reaches the lock after issuance must not change the original.
verify(requestController('edit')['ok'] === false, 'Issued document accepted a stale position edit');
verify((float)$adapter->fetchOne('SELECT price FROM quotepos WHERE id = 10') === 100.0, 'Issued position was changed');
echo "PASS: isolated MariaDB calculation, tax exemption, real rollback, parent lock, concurrent finalization and stale edit rejection\n";
