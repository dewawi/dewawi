<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/documents/position-read.php (real models/helpers, in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
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
require_once $root . '/application/controllers/helpers/Calculate.php';
require_once $root . '/application/controllers/helpers/Currency.php';
require_once $root . '/application/controllers/helpers/PriceRule.php';
require_once $root . '/application/controllers/helpers/Position.php';
require_once $root . '/application/modules/sales/controllers/PositionController.php';
require_once $root . '/application/modules/purchases/controllers/PositionController.php';
require_once $root . '/application/modules/processes/controllers/PositionController.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function positionWhereMatches(string $where, array $row): bool
{
    $where = trim(str_replace('`', '', preg_replace('/^AND /', '', $where)));
    foreach ([' OR ', ' AND '] as $operator) {
        $depth = 0;
        for ($i = 0; $i < strlen($where); $i++) {
            if ($where[$i] === '(') $depth++;
            if ($where[$i] === ')') $depth--;
            if ($depth === 0 && substr($where, $i, strlen($operator)) === $operator) {
                $left = positionWhereMatches(substr($where, 0, $i), $row);
                $right = positionWhereMatches(substr($where, $i + strlen($operator)), $row);
                return $operator === ' OR ' ? $left || $right : $left && $right;
            }
        }
    }
    if ($where[0] === '(' && substr($where, -1) === ')') return positionWhereMatches(substr($where, 1, -1), $row);
    if (preg_match('/^([a-z]+) IS NULL$/', $where, $match)) return ($row[$match[1]] ?? null) === null;
    if (preg_match('/^([a-z]+) IN \(([^)]+)\)$/', $where, $match)) return in_array((string)($row[$match[1]] ?? ''), array_map('trim', explode(',', $match[2])), true);
    if (preg_match('/^([a-z]+) > (.*)$/', $where, $match)) return $row[$match[1]] > trim($match[2], " '");
    if (!preg_match('/^([a-z]+)\s*=\s*(.*)$/', $where, $match)) throw new RuntimeException('Unsupported fixture predicate: ' . $where);
    return (string)($row[$match[1]] ?? '') === trim($match[2], " '");
}

class PositionReadTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $writes = [];
    public bool $allowWrites = false;
    public ?string $failUpdateTable = null;
    public ?Closure $beforeTransaction = null;
    private ?array $transactionSnapshot = null;
    private int $lastId = 100;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function beginTransaction()
    {
        if ($this->transactionSnapshot !== null) throw new RuntimeException('Nested fixture transaction');
        if ($callback = $this->beforeTransaction) {
            $this->beforeTransaction = null;
            $callback();
        }
        $this->transactionSnapshot = [$this->rows, $this->writes];
        return $this;
    }
    public function commit() { $this->transactionSnapshot = null; return $this; }
    public function rollBack()
    {
        if ($this->transactionSnapshot === null) throw new RuntimeException('No fixture transaction');
        [$this->rows, $this->writes] = $this->transactionSnapshot;
        $this->transactionSnapshot = null;
        return $this;
    }
    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'clientid', 'parentid', 'deleted', 'ordering'];
        foreach ($this->rows[$tableName] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
        $metadata = [];
        foreach (array_unique($columns) as $column) {
            $metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
        }
        return $metadata;
    }
    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (!positionWhereMatches($where, $row)) return false;
            }
            return true;
        }));
        usort($rows, static function ($left, $right) use ($sql): int {
            foreach ($sql->getPart(Zend_Db_Select::ORDER) as [$field, $direction]) {
                $comparison = $left[$field] <=> $right[$field];
                if ($comparison) return $direction === 'DESC' ? -$comparison : $comparison;
            }
            return 0;
        });
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function insert($table, array $bind)
    {
        if (!$this->allowWrites) throw new RuntimeException('Read action attempted an insert');
        $bind += ['id' => ++$this->lastId, 'deleted' => 0];
        $this->rows[$table][] = $bind;
        $this->writes[] = [$table, $bind];
        return 1;
    }
    public function update($table, array $bind, $where = '')
    {
        if (!$this->allowWrites) throw new RuntimeException('Read action attempted an update');
        if ($this->failUpdateTable === $table) throw new RuntimeException('Simulated calculation write failure');
        $affected = 0;
        foreach ($this->rows[$table] as &$row) {
            foreach ((array)$where as $condition) {
                if (!positionWhereMatches($condition, $row)) continue 2;
            }
            $row = array_replace($row, $bind);
            $affected++;
        }
        $this->writes[] = [$table, $bind];
        return $affected;
    }
    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class ReadSalesPositionController extends Sales_PositionController { public function init() { $this->view = new Zend_View(); } }
class ReadPurchasesPositionController extends Purchases_PositionController { public function init() { $this->view = new Zend_View(); } }
class ReadProcessesPositionController extends Processes_PositionController { public function init() { $this->view = new Zend_View(); } }

// PDF generation itself is covered in pdf.php; observe the row sent to finalization here.
class DEEC_Pdf { public function generate(array $request): array { return $request; } }
class CalculationDocumentController extends DEEC_Controller_DocumentAction
{
    public array $finalizedRow = [];
    public function init() { $this->_user = Zend_Registry::get('User'); }
    public function issue(int $id): array { return $this->generatePdfDocument($id, ['finalize' => true]); }
    public function defaults(array $data): array { return $this->beforeCreate($data); }
    protected function finalizeDocument(array $row): array { $this->finalizedRow = $row; return $row; }
}

$adapter = new PositionReadTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42, 'name' => 'Fixture user']);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default' => $root . '/application/controllers',
    'sales' => $root . '/application/modules/sales/controllers',
    'purchases' => $root . '/application/modules/purchases/controllers',
    'processes' => $root . '/application/modules/processes/controllers',
]);
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Calculate());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Currency());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_PriceRule());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Position());

$parent = ['id' => 1, 'clientid' => 7, 'contactid' => 12, 'deleted' => 0, 'currency' => 'EUR', 'language' => 'de_DE', 'taxfree' => 0,
    'state' => 105, 'completeddate' => '2026-09-09 12:00:00', 'completedby' => 17,
    'subtotal' => '999.0000', 'taxes' => '199.0000', 'total' => '1198.0000',
    'modified' => '2026-09-10 12:00:00', 'modifiedby' => 17];
$position = ['id' => 10, 'clientid' => 7, 'parentid' => 1, 'deleted' => 0, 'ordering' => 1,
    'possetid' => 0, 'masterid' => null, 'itemid' => 0, 'sku' => 'FIXTURE', 'title' => 'Fixture position',
    'image' => '', 'description' => '', 'price' => 100, 'quantity' => 2, 'taxrate' => 19.0, 'total' => 999,
    'cost' => 0, 'currency' => 'EUR', 'uom' => 'Piece', 'pricerulemaster' => 1,
    'modified' => '2026-09-10 12:00:00', 'modifiedby' => 17];
$child = array_replace($position, ['id' => 11, 'masterid' => 10, 'price' => 20, 'quantity' => 3, 'pricerulemaster' => 0]);
$rule = ['id' => 20, 'clientid' => 7, 'parentid' => 10, 'module' => 'sales', 'controller' => 'quotepos',
    'deleted' => 0, 'ordering' => 1, 'masterid' => null, 'possetid' => 0, 'amount' => 10, 'action' => 'bypercent'];
$adapter->rows['uom'] = [['id' => 1, 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'title' => 'Piece']];
$adapter->rows['taxrate'] = [['id' => 1, 'clientid' => 7, 'deleted' => 0, 'rate' => 19.0]];
$adapter->rows['contact'] = [['id' => 12, 'contactid' => 12, 'clientid' => 7, 'deleted' => 0]];
$modules = ['sales' => ['quote', 'invoice', 'salesorder', 'deliveryorder', 'creditnote', 'reminder'], 'purchases' => ['quoterequest', 'purchaseorder'], 'processes' => ['process']];

// Run the complete position-index action twice, with deliberately stale stored totals.
foreach ($modules as $module => $documents) {
    foreach ($documents as $document) {
        foreach ([100, 105, 106] as $state) {
            $adapter->rows[$document] = [array_replace($parent, ['state' => $state]) + [$document . 'id' => $state === 100 ? null : 1001]];
            $adapter->rows[$document . 'pos'] = [$position, $child, array_replace($position, ['id' => 99, 'clientid' => 8]), array_replace($position, ['id' => 100, 'deleted' => 1])];
            $adapter->rows[$document . 'posset'] = [];
            $adapter->rows['pricerulepos'] = [array_replace($rule, ['module' => $module, 'controller' => $document . 'pos'])];
            $before = $adapter->rows;
            $request = new Zend_Controller_Request_Http();
            $request->setModuleName($module)->setControllerName('position')->setActionName('index')
                ->setParams(['parent' => $document, 'parentid' => 1, 'type' => 'pos']);
            $class = 'Read' . ucfirst($module) . 'PositionController';
            $controller = new $class($request, new Zend_Controller_Response_Http());
            foreach ([1, 2] as $load) {
                $controller->indexAction();
                check($adapter->rows === $before && $adapter->writes === [], 'Reading positions changed stored data');
                $form = $controller->view->forms[0][10]['form'];
                if ($form->getElement('total')) check($form->getValue('total') === '180,00 €', 'Read-only display lost price-rule calculation');
                check($form->isReadonly() === ($state !== 100), 'Position form mode changed');
                check(!isset($controller->view->forms[0][99]) && !isset($controller->view->forms[0][100]), 'Foreign or deleted positions were displayed');
            }
        }
    }
}

// Empty positions must not reset historical totals when displayed.
$adapter->rows['quote'] = [$parent + ['quoteid' => 1001]];
$adapter->rows['quotepos'] = [];
$before = $adapter->rows;
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('position')->setActionName('index')->setParams(['parent' => 'quote', 'parentid' => 1]);
$controller = new ReadSalesPositionController($request, new Zend_Controller_Response_Http());
$controller->indexAction();
check($adapter->rows === $before && $adapter->writes === [], 'Empty position display reset document totals');

foreach (['clientid' => 8, 'deleted' => 1] as $field => $value) {
    $adapter->rows['quote'] = [array_replace($parent, [$field => $value]) + ['quoteid' => 1001]];
    $before = $adapter->rows;
    try {
        $controller->indexAction();
        throw new LogicException('Inaccessible document positions were loaded');
    } catch (RuntimeException $expected) {
        check($expected->getMessage() === 'Position parent was not found', 'Unexpected parent access error');
    }
    check($adapter->rows === $before && $adapter->writes === [], 'Denied position read changed data');
}

// The existing calculation helper still persists totals after actual edits.
$adapter->allowWrites = true;
$adapter->rows['quote'] = [array_replace($parent, ['state' => 100]) + ['quoteid' => null]];
$adapter->rows['quotepos'] = [$position, $child];
$adapter->rows['pricerulepos'] = [$rule];
$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct(1, 'sales', 'quote');
check(abs($adapter->rows['quote'][0]['subtotal'] - 234) < 0.00001, 'Write-side subtotal no longer updates');
check(abs($adapter->rows['quote'][0]['total'] - 278.46) < 0.00001, 'Write-side total no longer updates');
check($adapter->rows['quote'][0]['modifiedby'] === 42 && count($adapter->writes) === 3, 'Write-side modification metadata missing');
check(abs($calculation['row']['total'] - 278.46) < 0.00001, 'Calculation response changed');

// New copied/continued targets are calculated after master, children and rules are copied.
foreach ([['sales', 'invoice'], ['purchases', 'purchaseorder']] as [$targetModule, $target]) {
    $sourceRows = [$adapter->rows['quote'], $adapter->rows['quotepos'], $adapter->rows['pricerulepos']];
    $adapter->rows[$target] = [array_replace($parent, ['id' => 2, 'state' => 100]) + [$target . 'id' => null]];
    $adapter->rows[$target . 'pos'] = [];
    $source = (new Sales_Model_DbTable_Quotepos())->getPositions(1);
    Zend_Controller_Action_HelperBroker::getStaticHelper('Position')->copyPositions($source, 2, ['sales', $targetModule], ['quote', $target], date('Y-m-d H:i:s'));
    check(abs($adapter->rows[$target][0]['subtotal'] - 234) < 0.00001 && abs($adapter->rows[$target][0]['total'] - 278.46) < 0.00001, 'Copy did not calculate the complete target');
    check($adapter->rows['quote'] === $sourceRows[0] && $adapter->rows['quotepos'] === $sourceRows[1], 'Target calculation changed source document');
    check(array_slice($adapter->rows['pricerulepos'], 0, count($sourceRows[2])) === $sourceRows[2], 'Target calculation changed source rules');
}

// Finalization calculates only drafts and reloads their saved values before issuing.
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote')->setActionName('save');
$controller = new CalculationDocumentController($request, new Zend_Controller_Response_Http());
$defaults = $controller->defaults([]);
check($defaults['subtotal'] === 0 && $defaults['taxes'] === 0 && $defaults['total'] === 0, 'New empty draft totals missing');
check($controller->defaults(['total' => 123])['total'] === 123, 'Draft defaults replaced supplied values');
$adapter->rows['quote'] = [array_replace($parent, ['contactid' => null, 'state' => 100]) + ['quoteid' => null]];
$before = $adapter->rows;
$writes = $adapter->writes;
try {
    $controller->issue(1);
    throw new LogicException('Direct finalization accepted an invalid document');
} catch (DEEC_Document_ValidationException $expected) {
    check($expected->getValidation()['canFinalize'] === false, 'Direct finalization returned no validation errors');
}
check($adapter->rows === $before && $adapter->writes === $writes, 'Rejected direct finalization changed document data');
$adapter->rows['quote'] = [array_replace($parent, ['state' => 100]) + ['quoteid' => null]];
check($controller->issue(1)['createOriginal'] === true, 'First finalization did not authorize original PDF creation');
check(abs($controller->finalizedRow['total'] - 278.46) < 0.00001, 'Finalization received stale totals');
$adapter->rows['quote'] = [$parent + ['quoteid' => 1001]];
$before = $adapter->rows;
$adapter->allowWrites = false;
check($controller->issue(1)['createOriginal'] === false, 'Repeated finalization authorized PDF reconstruction');
check($adapter->rows === $before && $controller->finalizedRow['total'] === $parent['total'], 'Repeated finalization recalculated issued document');

echo 'PASS: ' . $checks . " position read and write checks; views preserve data, copies and finalization update totals\n";
