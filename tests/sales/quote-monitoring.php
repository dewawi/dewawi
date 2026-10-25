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
                while (str_starts_with($where, '(') && str_ends_with($where, ')')) $where = substr($where, 1, -1);
                if (preg_match('/^(?:\w+\.)?(\w+) (NOT IN|IN) \((.*)\)$/', $where, $match)) {
                    $values = array_map(static fn($value) => trim($value, " '"), explode(',', $match[3]));
                    $matches = in_array((string)$row[$match[1]], $values, true);
                    if ($matches !== ($match[2] === 'IN')) return false;
                } elseif (preg_match('/^(?:\w+\.)?(\w+) (=|<>) (.*)$/', $where, $match)) {
                    $matches = (string)$row[$match[1]] === trim($match[3], " '");
                    if ($matches !== ($match[2] === '=')) return false;
                } else {
                    throw new RuntimeException('Unsupported fixture predicate: ' . $where);
                }
            }
            return true;
        }));
        if ($table === 'emailmessage') {
            $groups = [];
            foreach ($rows as $row) {
                $id = $row['documentid'];
                $groups[$id] ??= ['documentid' => $id, 'lastsent' => null, 'sentcount' => 0];
                $groups[$id]['lastsent'] = max($groups[$id]['lastsent'], $row['messagesent']);
                $groups[$id]['sentcount']++;
            }
            return array_values($groups);
        }
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
$quote = ['id' => 1, 'quoteid' => 1001, 'clientid' => 7, 'state' => 105, 'completed' => 1, 'cancelled' => 0, 'deleted' => 0];
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
check(str_contains($render($row), 'QUOTES_MONITORING_SCHEDULED_OVERDUE'), 'Due task badge missing');
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
    check(str_contains($html, $badge) && str_contains($html, 'QUOTES_MONITORING_SCHEDULED_OVERDUE'), 'Feedback or due task hidden: ' . ($status ?? 'open'));
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
    check(str_contains($html, 'QUOTES_MONITORING_FEEDBACK_RECEIVED') && str_contains($html, $number === null ? 'QUOTES_MONITORING_SCHEDULED_OVERDUE' : 'QUOTES_MONITORING_OPEN_TASK_OVERDUE'), 'Follow-up document hid feedback or task');
}

foreach ([array_replace($quote, ['state' => 106]), array_replace($quote, ['cancelled' => 1]), array_replace($quote, ['quoteid' => null])] as $closedQuote) {
    $html = $render($service->enrich([$closedQuote])[0]);
    check(!str_contains($html, 'SCHEDULED_') && !str_contains($html, 'FEEDBACK_RECEIVED'), 'Cancelled quote or draft showed follow-up activity');
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

// Resolve the actual toolbar defaults and preserve explicit request/cookie selections.
require_once $root . '/application/controllers/helpers/Params.php';
class QuoteMonitoringTestQuery extends DEEC_List_Query
{
    public function filteredSelect(array $params, $adapter)
    {
        $select = $adapter->select()->from(['q' => 'quote']);
        $this->applyFilters($select, $params, [], Sales_Model_Entity_Quote::listConfig());
        return $select;
    }
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = $_POST = $_COOKIE = [];
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote')->setActionName('index');
Zend_Controller_Front::getInstance()->setRequest($request);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$toolbar = new Sales_Form_QuoteToolbar();
$paramsHelper = new Application_Controller_Action_Helper_Params();
$params = $paramsHelper->getParams($toolbar);
check(in_array('105', $params['states'], true) && !in_array('106', $params['states'], true), 'Issued quote missing from default filter');
check((new Sales_Form_Toolbar())->getDefault('states') === ['100', '101', '102', '103', '104'], 'Other sales toolbar defaults changed');
$adapter->rows['quote'] = [$quote, array_replace($quote, ['id' => 2, 'clientid' => 8]), array_replace($quote, ['id' => 3, 'state' => 106])];
$query = new QuoteMonitoringTestQuery();
check(array_column($adapter->fetchAll($query->filteredSelect($params, $adapter)), 'id') === [1], 'Default SQL filter hid issued quote or lost tenant scope');
$request->setParam('states', ['100', '104']);
check($paramsHelper->getParams(new Sales_Form_QuoteToolbar())['states'] === ['100', '104'], 'Explicit GET selection changed');
$request->clearParams()->setModuleName('sales')->setControllerName('quote')->setActionName('index');
$_COOKIE['states'] = '["101","106"]';
check($paramsHelper->getParams(new Sales_Form_QuoteToolbar())['states'] === ['101', '106'], 'Saved state selection changed');
$_SERVER['REQUEST_METHOD'] = 'POST';
$request->setPost(['states' => ['102']]);
check($paramsHelper->getParams(new Sales_Form_QuoteToolbar())['states'] === ['102'], 'Explicit POST selection changed');
$_COOKIE = [];

$adapter->rows = [];
$row = $service->enrich([$quote])[0];
check($row['monitoring_status'] === 'not_sent' && $row['monitoring_action'] === 'check_send', 'Unsent quote has incorrect next action');
check(str_contains($render($row), 'QUOTES_MONITORING_NOT_SENT'), 'Unsent indicator missing');
$email = ['documentid' => 1, 'module' => 'sales', 'controller' => 'quote', 'messagesent' => '2026-01-01 12:00:00', 'response' => 'sent', 'clientid' => 70, 'deleted' => 0];
$adapter->rows['emailmessage'] = [$email, array_replace($email, ['response' => 'pending']), array_replace($email, ['response' => 'failed']), array_replace($email, ['documentid' => 2])];
$row = $service->enrich([$quote])[0];
check($row['monitoring_status'] === 'sent' && $row['monitoring_action'] === 'follow_up' && $row['monitoring_sentcount'] === 1, 'Successful shared-contact mail not recognized');

foreach (['today' => date('Y-m-d'), 'overdue' => date('Y-m-d', strtotime('-1 day')), 'future' => date('Y-m-d', strtotime('+1 day')), 'undated' => null] as $dueState => $dueDate) {
    $adapter->rows = ['task' => [array_replace($task, ['duedate' => $dueDate])]];
    $row = $service->enrich([$quote])[0];
    $label = ['today' => 'SCHEDULED_DUE', 'overdue' => 'SCHEDULED_OVERDUE', 'future' => 'SCHEDULED_PLANNED', 'undated' => 'SCHEDULED_UNDATED'][$dueState];
    $html = $render($row);
    check($row['monitoring_task_due_state'] === $dueState, 'Incorrect due classification: ' . $dueState);
    check(str_contains($html, 'QUOTES_MONITORING_NOT_SENT'), 'Task hid the unsent indicator');
    check($row['monitoring_task_due'] === in_array($dueState, ['today', 'overdue'], true), 'Due compatibility flag changed');
    check(str_contains($html, 'QUOTES_MONITORING_' . $label) && str_contains($html, '/tasks/task/edit/10') && str_contains($html, '&lt;Task&gt;'), 'Task details missing: ' . $dueState);
    if ($dueDate !== null) check(str_contains($html, 'QUOTES_MONITORING_TASK_DUE'), 'Task date missing');

    foreach (['interested' => 'next_step', 'question' => 'contact', 'pending' => in_array($dueState, ['today', 'overdue'], true) ? 'review_task' : 'waiting', 'declined' => 'review_decline'] as $status => $action) {
        $adapter->rows['feedback'] = [array_replace($feedback, ['status' => $status, 'responded' => '2026-01-02'])];
        $row = $service->enrich([$quote])[0];
        $html = $render($row);
        check($row['monitoring_status'] === 'feedback_response' && $row['monitoring_action'] === $action, 'Task displaced customer action: ' . $status . '/' . $dueState);
        check(str_contains($html, 'QUOTES_MONITORING_FEEDBACK_RECEIVED') && str_contains($html, '/tasks/task/edit/10'), 'Feedback or task link disappeared');
        if ($status === 'declined') check(!str_contains($html, 'QUOTES_MONITORING_FOLLOW_UP'), 'Rejected quote suggested general follow-up');
    }
}

foreach (['interested' => 'next_step', 'question' => 'contact', 'pending' => 'waiting', 'declined' => 'review_decline'] as $status => $action) {
    $adapter->rows = ['feedback' => [array_replace($feedback, ['status' => $status, 'responded' => '2026-01-02'])]];
    check($service->enrich([$quote])[0]['monitoring_action'] === $action, 'Incorrect customer action without task: ' . $status);
}

// Every final follow-up document leaves tasks open without suggesting quote follow-up.
foreach (['salesorder' => 'salesorderid', 'invoice' => 'invoiceid', 'deliveryorder' => 'deliveryorderid'] as $table => $numberField) {
    foreach ([null, 2001] as $number) {
        $adapter->rows = ['task' => [$task], $table => [['id' => 50, 'clientid' => 7, 'quoteid' => 1001, $numberField => $number, 'state' => 105, 'deleted' => 0]]];
        $before = $adapter->rows['task'];
        $row = $service->enrich([$quote])[0];
        $html = $render($row);
        check($row['monitoring_status'] === ($number === null ? 'followup_draft' : 'continued'), 'Follow-up document state changed: ' . $table);
        check($row['monitoring_action'] === ($number === null ? 'review' : ''), 'Final document suggested a sales action');
        check(str_contains($html, '/tasks/task/edit/10') && $adapter->rows['task'] === $before, 'Open task hidden or modified');
        if ($number !== null) check(str_contains($html, 'QUOTES_MONITORING_OPEN_TASK_OVERDUE') && !str_contains($html, 'QUOTES_MONITORING_SCHEDULED_') && !str_contains($html, 'QUOTES_MONITORING_FOLLOW_UP'), 'Final document labeled task as quote follow-up');
    }
    $adapter->rows[$table][0]['clientid'] = 8;
    check($service->enrich([$quote])[0]['monitoring_status'] !== 'continued', 'Foreign follow-up document affected quote');
    $adapter->rows[$table][0]['clientid'] = 7;
    $adapter->rows[$table][0]['state'] = 106;
    check($service->enrich([$quote])[0]['monitoring_status'] !== 'continued', 'Cancelled follow-up document affected quote');
}

$adapter->rows = ['task' => [$task]];
foreach ([array_replace($quote, ['state' => 106]), array_replace($quote, ['cancelled' => 1]), array_replace($quote, ['quoteid' => null, 'state' => 106])] as $cancelled) {
    $row = $service->enrich([$cancelled])[0];
    check($row['monitoring_status'] === 'cancelled' && $row['monitoring_action'] === '', 'Cancelled quote suggested an action');
}

// Verify actual localized output and names already available in the quote/list context.
foreach (['de_DE', 'en_US'] as $locale) {
    $translator = new DEEC_Translate($locale);
    $translator->load('quote', $root . '/languages/' . $locale . '/sales/quote.php');
    $translator->load('feedback', $root . '/languages/' . $locale . '/default/feedback.php');
    $list->setTranslator($translator);
    $list->setContext(['user' => ['id' => 42, 'name' => '<Alice>']]);
    $row = $service->enrich([$quote])[0];
    $html = $render($row);
    check(str_contains($html, '&lt;Alice&gt;') && !str_contains($html, '<Alice>'), 'Loaded task owner name missing or unescaped');
    check(!str_contains($html, 'QUOTES_MONITORING_'), 'Missing monitoring translation: ' . $locale);
    $row['responsibleid'] = 42;
    $row['responsible'] = '<Sales owner>';
    check(str_contains($render($row), '&lt;Sales owner&gt;'), 'Loaded quote owner name not used');
    foreach (['interested', 'question', 'pending', 'declined'] as $status) {
        $adapter->rows['feedback'] = [array_replace($feedback, ['status' => $status, 'responded' => '2026-01-02'])];
        $html = $render($service->enrich([$quote])[0]);
        check(!str_contains($html, 'QUOTES_MONITORING_') && !str_contains($html, 'FEEDBACK_QUOTE_'), 'Untranslated customer response: ' . $locale . '/' . $status);
    }
}

echo 'PASS: ' . $checks . " quote monitoring, defaults, customer actions, due dates and tenant cases\n";
