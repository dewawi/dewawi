<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/documents/lifecycle.php (in-memory database, no real writes).
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

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function lifecycleWhereMatches($where, array $row): bool
{
    foreach ((array)$where as $condition) {
        $condition = preg_replace('/^AND /', '', $condition);
        if ($condition[0] === '(' && substr($condition, -1) === ')') $condition = substr($condition, 1, -1);
        if ($condition === 'quoteid IS NULL OR quoteid = 0') {
            if (($row['quoteid'] ?? null) !== null && $row['quoteid'] != 0) return false;
            continue;
        }
        if (!preg_match('/^([a-z]+) = (.*)$/', $condition, $match)) throw new RuntimeException('Unsupported fixture predicate');
        if ((string)($row[$match[1]] ?? '') !== trim($match[2], " '")) return false;
    }
    return true;
}

class LifecycleTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $writes = [];
    public ?Closure $beforeUpdate = null;
    private int $lastId = 100;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $metadata = [];
        foreach (array_keys($this->rows[$tableName][0]) as $column) {
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
        $rows = array_values(array_filter($this->rows[$table], static fn($row) => lifecycleWhereMatches($sql->getPart(Zend_Db_Select::WHERE), $row)));
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function insert($table, array $bind)
    {
        $bind += ['id' => ++$this->lastId, 'deleted' => 0, $table . 'id' => null];
        $this->rows[$table][] = $bind;
        $this->writes[] = [$table, $bind];
        return 1;
    }
    public function update($table, array $bind, $where = '')
    {
        if ($callback = $this->beforeUpdate) {
            $this->beforeUpdate = null;
            $callback();
        }
        $affected = 0;
        foreach ($this->rows[$table] as &$row) {
            if (!lifecycleWhereMatches($where, $row)) continue;
            $row = array_replace($row, $bind);
            $affected++;
        }
        if ($affected) $this->writes[] = [$table, $bind];
        return $affected;
    }
    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class LifecycleTestController extends DEEC_Controller_DocumentAction
{
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = []) { $this->_date = date('Y-m-d H:i:s'); }
    public function generateData(array $row, string $source, string $target): ?array
    {
        $config = $this->getDocumentGenerateConfig($source, $target);
        return $config ? $this->prepareGeneratedDocumentData($row, $source, $target, $config) : null;
    }
    public function generateConfig(string $source, string $target): ?array { return $this->getDocumentGenerateConfig($source, $target); }
}

$adapter = new LifecycleTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42]);
$modules = ['Sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'], 'Purchases' => ['quoterequest', 'purchaseorder']];
$fields = [
    'id' => 1, 'clientid' => 7, 'contactid' => 12, 'deleted' => 0, 'title' => 'Document fixture',
    'subject' => 'Keep subject', 'notes' => 'Keep internal notes', 'total' => '119.0000',
    'header' => 'Keep introduction', 'footer' => 'Keep terms', 'currency' => 'EUR', 'language' => 'de_DE',
    'state' => 106, 'completed' => 1, 'completeddate' => '2026-09-09 12:00:00', 'completedby' => 17,
    'cancelled' => 1, 'cancelleddate' => '2026-09-10 12:00:00', 'cancelledby' => 18,
    'filename' => 'old-original.pdf', 'pinned' => 1, 'locked' => 17, 'lockedtime' => '2026-09-10 12:00:00',
    'created' => '2026-09-01 12:00:00', 'createdby' => 17, 'modified' => '2026-09-10 12:00:00', 'modifiedby' => 18,
];
$adapter->rows['increment'] = [['id' => 1, 'clientid' => 7]];
$adapter->rows['filename'] = [['id' => 1, 'clientid' => 7, 'language' => 'de_DE']];

// Copy real document rows, then finalize the resulting drafts using the real services.
foreach ($modules as $module => $controllers) {
    foreach ($controllers as $controller) {
        $source = $fields + [$controller . 'id' => 999, $controller . 'date' => '2026-09-09'];
        $adapter->rows[$controller] = [$source];
        $adapter->rows['increment'][0][$controller . 'id'] = 1000;
        $adapter->rows['filename'][0][$controller] = $controller . '_%NUMBER%.pdf';
        $class = $module . '_Model_DbTable_' . ucfirst($controller);
        $table = new $class();
        $copyId = $table->copyById(1);
        $copy = $table->getById($copyId);
        check(empty($copy[$controller . 'id']) && empty($copy[$controller . 'date']), 'Copy retained its own document identity');
        check($copy['state'] === 100 && $copy['completed'] === 0 && $copy['completeddate'] === null && $copy['completedby'] === 0, 'Copy retained completion metadata');
        check($copy['cancelled'] === 0 && $copy['cancelleddate'] === null && $copy['cancelledby'] === 0, 'Copy retained cancellation metadata');
        check($copy['filename'] === null, 'Copy retained original PDF association');
        foreach (['subject', 'notes', 'header', 'footer', 'total', 'contactid'] as $field) {
            check($copy[$field] === $source[$field], 'Copy lost business data: ' . $field);
        }
        check($table->getById(1) === $source, 'Copy changed source');

        $serviceClass = $module . '_Service_DocumentFinalizeService';
        $service = new $serviceClass();
        $finalized = $service->finalize($copy, $controller);
        check($finalized[$controller . 'id'] === 1000 && $finalized['completed'] === 1, 'Finalization did not issue copied draft');
        check($finalized['completeddate'] >= $copy['created'] && $finalized['completedby'] === 42, 'Finalization retained an old timestamp or actor');
        check($finalized['filename'] === $controller . '_1000.pdf', 'Finalization reused source PDF filename');
        $writes = count($adapter->writes);
        check($service->finalize($finalized, $controller) === $finalized && count($adapter->writes) === $writes, 'Repeated finalization changed metadata');
        check($service->finalize($source, $controller) === $source && count($adapter->writes) === $writes, 'Finalization changed issued source');

        foreach (['clientid' => 8, 'deleted' => 1] as $field => $value) {
            $adapter->rows[$controller][0] = array_replace($source, [$field => $value]);
            try {
                $table->copyById(1);
                throw new LogicException('Inaccessible document was copied');
            } catch (RuntimeException $expected) {
                check($expected->getMessage() === 'Record not found', 'Unexpected copy access error');
            }
            check(count($adapter->writes) === $writes, 'Denied copy changed data');
        }
        $adapter->rows[$controller][0] = $source;
    }
}

// Cover every configured continuation, including source rows that refer to an older target.
$controller = new LifecycleTestController(new Zend_Controller_Request_Simple(), new Zend_Controller_Response_Http());
$sources = array_merge(...array_values($modules));
$targets = array_merge($sources, ['process']);
$relations = [];
foreach ($sources as $name) {
    $relations[$name . 'id'] = 999;
    $relations[$name . 'date'] = '2026-09-09';
}
foreach ($sources as $source) {
    foreach ($targets as $target) {
        $original = $fields + $relations;
        $generated = $controller->generateData($original, $source, $target);
        if ($generated === null) continue;
        check(!array_key_exists('id', $generated) && !array_key_exists('filename', $generated) && empty($generated[$target . 'id']) && empty($generated[$target . 'date']), 'Continuation retained target identity or PDF association');
        check($generated['state'] === 100 && $generated['completed'] === 0 && $generated['completeddate'] === null && $generated['completedby'] === 0, 'Continuation retained completion metadata');
        check($generated['cancelled'] === 0 && $generated['cancelleddate'] === null && $generated['cancelledby'] === 0, 'Continuation retained cancellation metadata');
        check($generated['notes'] === $fields['notes'] && $generated['total'] === $fields['total'], 'Continuation changed unrelated values');
        $config = $controller->generateConfig($source, $target);
        foreach ($relations as $field => $value) {
            if ($field === $target . 'id' || $field === $target . 'date' || in_array($field, $config['unset'] ?? [], true)) continue;
            check($generated[$field] === $value, 'Continuation lost an existing source reference');
        }
        check($original === $fields + $relations, 'Continuation mutated source');
    }
}

// Public automatic generation records no invented user and cannot reissue a saved quote.
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 11, 'clientid' => 7]));
foreach ([[], ['id' => 42]] as $user) {
    Zend_Registry::set('User', $user);
    $draft = array_replace($fields, ['quoteid' => null, 'quotedate' => '2026-08-01', 'state' => 100, 'completed' => 0, 'completeddate' => null, 'completedby' => 0, 'cancelled' => 0, 'cancelleddate' => null, 'cancelledby' => 0, 'filename' => null]);
    $adapter->rows['quote'] = [$draft];
    $table = new Shops_Model_DbTable_Quote();
    $table->saveQuote(1, 1001, 'new-quote.pdf');
    $saved = $table->getQuote(1);
    check($saved['quoteid'] === 1001 && $saved['completed'] === 1 && $saved['state'] === 105, 'Shop quote was not completed');
    check($saved['completeddate'] >= $draft['created'] && $saved['completedby'] === ($user['id'] ?? 0), 'Shop quote completion metadata missing');
    check($saved['quotedate'] === $draft['quotedate'], 'Shop finalization overwrote document date');
    $writes = count($adapter->writes);
    $table->saveQuote(1, 2002, 'different.pdf');
    check($table->getQuote(1) === $saved && count($adapter->writes) === $writes, 'Shop quote was reissued');

    foreach (['clientid' => 8, 'deleted' => 1] as $field => $value) {
        $adapter->rows['quote'] = [array_replace($draft, [$field => $value])];
        try {
            $table->saveQuote(1, 1001, 'new-quote.pdf');
            throw new LogicException('Inaccessible shop quote was issued');
        } catch (RuntimeException $expected) {
            check($expected->getMessage() === 'Could not find quote 1', 'Unexpected shop quote access error');
        }
        check(count($adapter->writes) === $writes, 'Denied shop finalization changed data');
    }

    $adapter->rows['quote'] = [$draft];
    $winner = array_replace($draft, ['quoteid' => 3003, 'filename' => 'winner.pdf', 'completed' => 1, 'completeddate' => '2026-10-01 12:00:00', 'completedby' => 17]);
    $adapter->beforeUpdate = static function () use ($adapter, $winner): void { $adapter->rows['quote'] = [$winner]; };
    $table->saveQuote(1, 1001, 'new-quote.pdf');
    check($table->getQuote(1) === $winner && count($adapter->writes) === $writes, 'Concurrent shop finalization was overwritten');

    $legacy = array_replace($draft, ['quoteid' => 1001]);
    $adapter->rows['quote'] = [$legacy];
    $table->saveQuote(1, 2002, 'different.pdf');
    check($table->getQuote(1) === $legacy && count($adapter->writes) === $writes, 'Legacy completion data was silently backfilled');

    $adapter->rows['quote'] = [array_replace($draft, ['quoteid' => 0, 'quotedate' => null])];
    $table->saveQuote(1, 1001, 'new-quote.pdf');
    check($table->getQuote(1)['quotedate'] === date('Y-m-d'), 'Shop quote without document date did not receive today\'s date');
}

echo 'PASS: ' . $checks . " document lifecycle checks; copy, continuation, finalization and tenant isolation\n";
