<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/sales/quote-monitoring.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
        return;
    }
    if (str_starts_with($class, 'Sales_')) {
        $parts = explode('_', $class);
        $path = $root . '/application/modules/sales/' . strtolower($parts[1]) . 's/' . implode('/', array_slice($parts, 2)) . '.php';
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

class QuoteMonitoringTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function query($sql, $bind = []) { throw new RuntimeException('Database access is forbidden in this test'); }

    public function fetchAll($sql, $bind = [], $fetchMode = null)
    {
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                $where = preg_replace('/^AND /', '', $where);
                $where = substr($where, 1, -1);
                if (preg_match('/^(\w+) (NOT IN|IN) \((.*)\)$/', $where, $match)) {
                    $values = array_map(static fn($value) => trim($value, " '"), explode(',', $match[3]));
                    $matches = in_array((string)$row[$match[1]], $values, true);
                    if ($matches !== ($match[2] === 'IN')) return false;
                } elseif (preg_match('/^(\w+) (=|<>) (.*)$/', $where, $match)) {
                    $matches = (string)$row[$match[1]] === trim($match[3], " '");
                    if ($matches !== ($match[2] === '=')) return false;
                } else {
                    throw new RuntimeException('Unsupported fixture predicate: ' . $where);
                }
            }
            return true;
        }));
        usort($rows, static function ($left, $right) use ($sql): int {
            foreach ($sql->getPart(Zend_Db_Select::ORDER) as $order) {
                if ($order instanceof Zend_Db_Expr) {
                    if ((string)$order !== 'duedate IS NULL ASC') throw new RuntimeException('Unsupported fixture order');
                    $comparison = ($left['duedate'] === null) <=> ($right['duedate'] === null);
                } else {
                    [$column, $direction] = $order;
                    $comparison = $left[$column] <=> $right[$column];
                    if ($direction === 'DESC') $comparison = -$comparison;
                }
                if ($comparison !== 0) return $comparison;
            }
            return 0;
        });
        return $rows;
    }
}

class QuoteMonitoringTestView
{
    public function url(array $params, $route = null, $reset = false): string
    {
        return '/' . implode('/', $params);
    }
}

$adapter = new QuoteMonitoringTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
$service = new Sales_Service_QuoteMonitoring();
$quote = ['id' => 1, 'quoteid' => 1001, 'clientid' => 7, 'state' => 105, 'completed' => 1, 'cancelled' => 0];
$task = ['id' => 10, 'quoteid' => 1001, 'clientid' => 7, 'title' => '<Task>', 'duedate' => '2000-01-01', 'responsible' => 42, 'state' => 100, 'completed' => 0, 'cancelled' => 0, 'deleted' => 0];
$feedback = ['id' => 10, 'parentid' => 1, 'clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'created' => '2000-01-01', 'responded' => null, 'status' => null, 'reason' => null, 'message' => null, 'deleted' => 0];
$list = new Sales_Model_List_Quotes(['view' => new QuoteMonitoringTestView()]);
$column = array_values(array_filter($list->getColumns(), static fn($column) => $column['name'] === 'monitoring'))[0];
$render = static fn($row) => $list->renderCell($row, $column);

// An issued quote is marked completed by DocumentFinalizeService and still needs follow-up.
$row = $service->enrich([$quote])[0];
check($row['monitoring_status'] === 'not_sent', 'Issued quote was excluded from monitoring');

$adapter->rows['task'] = [
    $task,
    array_replace($task, ['id' => 20, 'duedate' => '2099-01-01']),
    array_replace($task, ['id' => 30, 'duedate' => null]),
];
$row = $service->enrich([$quote])[0];
check($row['monitoring_task_id'] === 10, 'Newer future or undated task hid an overdue task');
check($row['monitoring_status'] === 'scheduled_due' && $row['monitoring_task_due'], 'Overdue task was not marked due');
check(str_contains($render($row), 'QUOTES_MONITORING_SCHEDULED_DUE'), 'Due task badge missing');
check(str_contains($render($row), '&lt;Task&gt;'), 'Task title was not escaped');

$adapter->rows['task'] = [array_replace($task, ['duedate' => date('Y-m-d')])];
check($service->enrich([$quote])[0]['monitoring_task_due'], 'Task due today was not marked due');
$adapter->rows['task'] = [array_replace($task, ['duedate' => '2099-01-01'])];
$row = $service->enrich([$quote])[0];
check($row['monitoring_status'] === 'scheduled' && !$row['monitoring_task_due'], 'Future task was marked due');

$adapter->rows['task'] = [array_replace($task, ['id' => 30, 'duedate' => null]), array_replace($task, ['id' => 40, 'duedate' => null])];
$row = $service->enrich([$quote])[0];
check($row['monitoring_task_id'] === 40 && !$row['monitoring_task_due'], 'Undated task selection changed unexpectedly');

$adapter->rows['task'] = [$task, array_replace($task, ['id' => 20])];
check($service->enrich([$quote])[0]['monitoring_task_id'] === 20, 'Equal due dates did not retain newest-task priority');

// Ignore unrelated, closed and deleted tasks even when their due date is earlier.
$adapter->rows['task'] = [array_replace($task, ['duedate' => '2099-01-01'])];
foreach (['clientid' => 8, 'quoteid' => 9999, 'completed' => 1, 'cancelled' => 1, 'deleted' => 1, 'state' => 105] as $field => $value) {
    $adapter->rows['task'][] = array_replace($task, [$field => $value]);
}
$adapter->rows['task'][] = array_replace($task, ['state' => 106]);
$row = $service->enrich([$quote])[0];
check($row['monitoring_task_duedate'] === '2099-01-01', 'Unrelated or closed task affected monitoring');

$adapter->rows['task'] = [$task, array_replace($task, ['id' => 20, 'clientid' => 8, 'title' => 'Other tenant'])];
$rows = $service->enrich([$quote, array_replace($quote, ['id' => 2, 'clientid' => 8])]);
check($rows[0]['monitoring_task_id'] === 10 && $rows[1]['monitoring_task_id'] === 20, 'Same quote number mixed tasks between tenants');

// Feedback and a task must both remain visible regardless of their primary status.
$adapter->rows['task'] = [$task];
foreach ([null, 'interested', 'question', 'pending', 'declined'] as $status) {
    $adapter->rows['feedback'] = [array_replace($feedback, [
        'status' => $status, 'responded' => $status === null ? null : '2000-01-02',
        'reason' => $status === 'declined' ? 'price' : null, 'message' => '<script>alert(1)</script>',
    ])];
    $row = $service->enrich([$quote])[0];
    $html = $render($row);
    $badge = $status === null ? 'QUOTES_MONITORING_FEEDBACK_OPEN' : 'QUOTES_MONITORING_FEEDBACK_RECEIVED';
    check(str_contains($html, $badge) && str_contains($html, 'QUOTES_MONITORING_SCHEDULED_DUE'), 'Feedback or due task hidden: ' . ($status ?? 'open'));
    check(str_contains($html, '/tasks/task/edit/10'), 'Task link missing alongside feedback');
    check(!str_contains($html, '<script>'), 'Feedback message was not escaped');
    if ($status !== null) check(str_contains($html, '&lt;script&gt;'), 'Response text missing');
    if ($status === 'declined') check(str_contains($html, 'FEEDBACK_QUOTE_REASON_PRICE'), 'Decline reason missing');
}

foreach ([null, 2001] as $number) {
    $adapter->rows['salesorder'] = [['id' => 50, 'clientid' => 7, 'quoteid' => 1001, 'salesorderid' => $number, 'state' => 105, 'deleted' => 0]];
    $row = $service->enrich([$quote])[0];
    $html = $render($row);
    check($row['monitoring_status'] === ($number === null ? 'followup_draft' : 'continued'), 'Follow-up status changed');
    check(str_contains($html, 'QUOTES_MONITORING_FEEDBACK_RECEIVED') && str_contains($html, 'QUOTES_MONITORING_SCHEDULED_DUE'), 'Follow-up document hid feedback or task');
}

foreach ([array_replace($quote, ['state' => 106]), array_replace($quote, ['cancelled' => 1]), array_replace($quote, ['quoteid' => null])] as $closedQuote) {
    $html = $render($service->enrich([$closedQuote])[0]);
    check(!str_contains($html, 'SCHEDULED_DUE') && !str_contains($html, 'FEEDBACK_RECEIVED'), 'Cancelled quote or draft showed follow-up activity');
}

// Compile the real filter SQL: these filters describe independent facts, not mutually exclusive buckets.
foreach (['feedback_open', 'feedback_response', 'scheduled'] as $filter) {
    $select = $adapter->select()->from(['q' => 'quote']);
    Sales_Service_QuoteMonitoring::applyListFilter($select, $filter, [], ['alias' => 'q']);
    $sql = $select->assemble();
    check(str_contains($sql, 'q.state <> 106') && str_contains($sql, 'q.cancelled'), 'Cancelled quote exclusion lost');
    check(!str_contains($sql, 'q.completed') && !str_contains($sql, 'q.state <> 105'), 'Issued quotes excluded from filter');
    check(!str_contains($sql, 'FROM salesorder') && !str_contains($sql, 'FROM invoice') && !str_contains($sql, 'FROM deliveryorder'), 'Follow-up document still suppresses filter');
    if ($filter === 'scheduled') {
        check(str_contains($sql, 't.clientid = q.clientid') && str_contains($sql, 't.state NOT IN (105, 106)'), 'Task scope or closed-task exclusion lost');
        check(!str_contains($sql, 'FROM feedback'), 'Feedback still suppresses scheduled filter');
    } else {
        check(str_contains($sql, 'f2.clientid = q.clientid'), 'Feedback tenant correlation lost');
        check(!str_contains($sql, 'FROM task'), 'Task still suppresses feedback filter');
        check(str_contains($sql, $filter === 'feedback_open' ? 'f.responded IS NULL' : 'f.responded IS NOT NULL'), 'Feedback response filter changed');
    }
}

echo 'PASS: ' . $checks . " quote monitoring, task priority, feedback visibility and filter cases\n";
