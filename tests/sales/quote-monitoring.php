<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/sales/quote-monitoring.php (in-memory fixtures; Python 3 for isolated SQLite, no database server).
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
    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        return new class($this->fetchAll($sql)) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'clientid', 'deleted'];
        foreach ($this->rows as $rows) foreach ($rows as $row) $columns = array_merge($columns, array_keys($row));
        $metadata = [];
        foreach (array_unique($columns) as $column) {
            $metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
        }
        return $metadata;
    }
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }

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
$feedback = ['id' => 10, 'parentid' => 1, 'clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'type' => 'quote', 'created' => '2000-01-01', 'responded' => null, 'status' => null, 'reason' => null, 'message' => null, 'deleted' => 0];
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
        check(str_contains($sql, 'f.clientid = q.clientid') && str_contains($sql, 'f.type = \'quote\''), 'Feedback tenant correlation lost');
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
    public function pageSelect(array $params, $adapter)
    {
        $params = $this->normalizeParams($params);
        $select = $this->filteredSelect($params, $adapter)->order('q.id ASC');
        $this->applyLimit($select, $params);
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

// Calendar-day follow-up uses only the latest successful send, including shared-contact logs.
$sentAgo = static fn(int $days) => (new DateTimeImmutable('today'))->modify('-' . $days . ' days')->format('Y-m-d') . ' 23:59:59';
foreach ([3 => 'future', 7 => 'today', 10 => 'overdue'] as $days => $state) {
    $adapter->rows = ['emailmessage' => [array_replace($email, ['messagesent' => $sentAgo($days)])]];
    $row = $service->enrich([$quote])[0];
    check($row['monitoring_sent_days'] === $days && $row['monitoring_followup_state'] === $state, 'Wrong calendar follow-up age/state: ' . $days);
    check($row['monitoring_followup_date'] === (new DateTimeImmutable($sentAgo($days)))->modify('+7 days')->format('Y-m-d'), 'Wrong follow-up date');
    check($row['monitoring_followup_due'] === ($days >= 7), 'Wrong automatic due flag');
    check($row['monitoring_action'] === ($days >= 7 ? 'follow_up' : ''), 'Premature or missing follow-up action');
    foreach (['de_DE', 'en_US'] as $locale) {
        $translator = new DEEC_Translate($locale);
        $translator->load('quote', $root . '/languages/' . $locale . '/sales/quote.php');
        $list->setTranslator($translator);
        $html = $render($row);
        check(!str_contains($html, 'QUOTES_MONITORING_') && str_contains($html, '/sales/quote/view/1/1'), 'Localized due state or manual email entry missing');
        check(str_contains($html, '/tasks/task/add/1'), 'Task entry must use internal source quote ID without untrusted contact join');
    }
}
$oldMail = array_replace($email, ['messagesent' => $sentAgo(10)]);
$newMail = array_replace($email, ['messagesent' => $sentAgo(3)]);
foreach (['sent' => 'future', 'failed' => 'overdue', 'pending' => 'overdue'] as $response => $state) {
    $adapter->rows = ['emailmessage' => [$oldMail, array_replace($newMail, ['response' => $response])]];
    check($service->enrich([$quote])[0]['monitoring_followup_state'] === $state, 'Failed send reset deadline or resend did not reset it');
}
$adapter->rows = ['emailmessage' => [array_replace($newMail, ['response' => 'failed'])]];
check($service->enrich([$quote])[0]['monitoring_followup_date'] === null, 'Failed-only send produced follow-up');
$adapter->rows = ['emailmessage' => [$oldMail], 'feedback' => [$feedback]];
$row = $service->enrich([$quote])[0];
check($row['monitoring_followup_due'] && $row['monitoring_status'] === 'feedback_open' && $row['monitoring_action'] === 'follow_up', 'Unanswered request blocked due follow-up');
foreach (['interested', 'question', 'pending', 'declined'] as $status) {
    $adapter->rows['feedback'] = [array_replace($feedback, ['status' => $status, 'responded' => date('Y-m-d H:i:s')])];
    check($service->enrich([$quote])[0]['monitoring_followup_date'] === null, 'Answered request triggered automatic follow-up');
}
$adapter->rows['feedback'][] = array_replace($feedback, ['id' => 11]);
check($service->enrich([$quote])[0]['monitoring_followup_date'] === null, 'Renewed request ignored an earlier response');
foreach ([null, date('Y-m-d'), '2000-01-01', '2099-01-01'] as $dueDate) {
    $adapter->rows = ['emailmessage' => [$oldMail], 'task' => [array_replace($task, ['duedate' => $dueDate])]];
    check($service->enrich([$quote])[0]['monitoring_followup_date'] === null, 'Open task did not suppress automatic follow-up');
}
foreach (['salesorder' => 'salesorderid', 'invoice' => 'invoiceid', 'deliveryorder' => 'deliveryorderid'] as $table => $number) {
    $adapter->rows = ['emailmessage' => [$oldMail], $table => [['id' => 20, 'quoteid' => 1001, 'clientid' => 7, $number => 3001, 'state' => 105, 'deleted' => 0]]];
    check($service->enrich([$quote])[0]['monitoring_followup_date'] === null, 'Final document allowed follow-up');
    $adapter->rows[$table][0][$number] = null;
    check($service->enrich([$quote])[0]['monitoring_status'] === 'followup_draft', 'Draft follow-up lost its own state');
}
$adapter->rows = ['emailmessage' => [$oldMail]];
foreach ([['cancelled' => 1], ['state' => 106], ['quoteid' => null]] as $override) {
    check($service->enrich([array_replace($quote, $override)])[0]['monitoring_followup_date'] === null, 'Cancelled/draft quote allowed follow-up');
}
$adapter->rows = ['emailmessage' => [array_replace($oldMail, ['documentid' => 2])], 'feedback' => [array_replace($feedback, ['clientid' => 8, 'responded' => '2026-01-01'])], 'task' => [array_replace($task, ['clientid' => 8])]];
$twoQuotes = $service->enrich([$quote, array_replace($quote, ['id' => 2, 'clientid' => 8])]);
check($twoQuotes[0]['monitoring_sentcount'] === 0 && $twoQuotes[1]['monitoring_sentcount'] === 1, 'Same visible quote number mixed mail between tenants');

// Execute generated SQL on isolated SQLite fixtures via Python's standard library; never connect to production.
$fixtures = ['quote' => [], 'emailmessage' => [], 'feedback' => [], 'task' => [], 'salesorder' => [], 'invoice' => [], 'deliveryorder' => []];
$expected = [];
for ($id = 1; $id <= 17; $id++) {
    $q = array_replace($quote, ['id' => $id, 'quoteid' => 1000 + $id]);
    $mail = array_replace($oldMail, ['documentid' => $id]);
    if ($id === 1) $mail['messagesent'] = $sentAgo(3);
    if ($id === 2) $mail['messagesent'] = $sentAgo(7);
    if ($id === 4) $fixtures['feedback'][] = array_replace($feedback, ['parentid' => $id]);
    if ($id === 5 || $id === 6) $fixtures['feedback'][] = array_replace($feedback, ['parentid' => $id, 'responded' => date('Y-m-d'), 'status' => $id === 6 ? 'declined' : 'question']);
    if ($id === 7) $fixtures['task'][] = array_replace($task, ['quoteid' => $q['quoteid']]);
    if ($id === 8) $fixtures['salesorder'][] = ['id' => 50, 'quoteid' => $q['quoteid'], 'clientid' => 7, 'salesorderid' => 3001, 'state' => 105, 'deleted' => 0];
    if ($id === 9) $q['cancelled'] = 1;
    if ($id === 10) $mail['response'] = 'failed';
    if ($id === 11) $fixtures['emailmessage'][] = array_replace($mail, ['messagesent' => $sentAgo(3)]);
    if ($id === 12) $fixtures['emailmessage'][] = array_replace($mail, ['messagesent' => $sentAgo(3), 'response' => 'failed']);
    if ($id === 13) $q['clientid'] = 8;
    if ($id === 14) {
        $fixtures['task'][] = array_replace($task, ['quoteid' => $q['quoteid'], 'clientid' => 8]);
        $fixtures['salesorder'][] = ['id' => 51, 'quoteid' => $q['quoteid'], 'clientid' => 8, 'salesorderid' => 3001, 'state' => 105, 'deleted' => 0];
        $fixtures['feedback'][] = array_replace($feedback, ['parentid' => $id, 'clientid' => 8, 'responded' => date('Y-m-d')]);
    }
    if ($id === 15) {
        $fixtures['feedback'][] = array_replace($feedback, ['parentid' => $id, 'responded' => date('Y-m-d')]);
        $fixtures['feedback'][] = array_replace($feedback, ['id' => 11, 'parentid' => $id]);
    }
    if ($id === 16) $fixtures['salesorder'][] = ['id' => 52, 'quoteid' => $q['quoteid'], 'clientid' => 7, 'salesorderid' => null, 'state' => 100, 'deleted' => 0];
    if ($id === 17) $q['state'] = 106;
    $fixtures['quote'][] = $q;
    $fixtures['emailmessage'][] = $mail;
    $adapter->rows = $fixtures;
    $r = $service->enrich([$q])[0];
    if ($q['clientid'] === 7 && $r['monitoring_followup_due']) $expected[] = $id;
}
check($expected === [2, 3, 4, 12, 14, 16], 'Unexpected automatic follow-up fixtures');
$filterParams = ['monitoring' => 'followup_due', 'states' => ['100', '101', '102', '103', '104', '105']];
$sql = [$query->filteredSelect($filterParams, $adapter)->order('q.id ASC')->assemble()];
for ($page = 1; $page <= 3; $page++) $sql[] = $query->pageSelect($filterParams + ['page' => $page, 'limit' => 2], $adapter)->assemble();
foreach (['feedback_open', 'feedback_response'] as $filter) $sql[] = $query->filteredSelect(array_replace($filterParams, ['monitoring' => $filter]), $adapter)->order('q.id ASC')->assemble();
$python = <<<'PY'
import sys, json, sqlite3
payload = json.load(sys.stdin)
db = sqlite3.connect(':memory:')
schema = {
 'quote': ['id','quoteid','clientid','deleted','state','cancelled'],
 'emailmessage': ['documentid','module','controller','deleted','response','messagesent'],
 'feedback': ['id','parentid','clientid','module','controller','type','deleted','responded','status'],
 'task': ['quoteid','clientid','deleted','completed','cancelled','state'],
 'salesorder': ['quoteid','clientid','deleted','state','salesorderid'],
 'invoice': ['quoteid','clientid','deleted','state','invoiceid'],
 'deliveryorder': ['quoteid','clientid','deleted','state','deliveryorderid']
}
for table, columns in schema.items():
 db.execute('CREATE TABLE '+table+' ('+','.join(c+' '+('TEXT' if c in ['module','controller','type','response','messagesent','responded','status'] else 'INTEGER') for c in columns)+')')
 for row in payload['fixtures'][table]:
  db.execute('INSERT INTO '+table+' VALUES ('+','.join('?' for _ in columns)+')', [row.get(c) for c in columns])
print(json.dumps([[r[0] for r in db.execute(sql)] for sql in payload['sql']]))
PY;
$process = proc_open(['python3', '-c', $python], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start isolated SQL fixture runner');
fwrite($pipes[0], json_encode(['fixtures' => $fixtures, 'sql' => $sql], JSON_THROW_ON_ERROR));
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
check(proc_close($process) === 0, 'Generated SQL failed: ' . $error);
$results = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
check($results[0] === $expected, 'SQL filter diverged from displayed due states');
check(array_merge(...array_slice($results, 1, 3)) === $expected && count($results[1]) === 2, 'Filter applied after pagination or returned wrong pages');
check($results[4] === [4, 15] && $results[5] === [5, 6, 15], 'Open and answered SQL filters must both include an earlier response with a new open request');
check(isset((new Sales_Form_QuoteToolbar())->getElement('monitoring')['options']['followup_due']), 'Due filter missing from toolbar');

// Exercise the existing task controller with real forms/models and isolated database I/O.
require_once $root . '/application/modules/tasks/controllers/TaskController.php';
require_once $root . '/application/modules/sales/controllers/QuoteController.php';
class QuoteFollowupTestTaskDb extends Tasks_Model_DbTable_Task
{
    public static array $created = [];
    public function create(array $data): int { self::$created[] = $data; return 999; }
}
class QuoteFollowupTestView extends Zend_View
{
    public function translate($key) { return $key === 'FEEDBACK_QUOTE_TASK_TITLE' ? 'Follow up quote %s' : $key; }
}
class QuoteFollowupTestTaskController extends Tasks_TaskController
{
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->view = new QuoteFollowupTestView();
    }
    protected function getEditForm(): array
    {
        $form = new Tasks_Form_Task();
        $form->addOptions('responsible', ['42' => 'Alice']);
        $form->addOptions('currency', ['EUR' => 'EUR']);
        $form->setLocale('en_US');
        return ['form' => $form, 'options' => []];
    }
    protected function getDb(): DEEC_Model_DbTable_Entity { return new QuoteFollowupTestTaskDb(); }
}
class QuoteFollowupTestQuoteController extends Sales_QuoteController
{
    public function init() { $this->_user = Zend_Registry::get('User'); }
    public function contact(array $quote): array { return $this->getViewContact($quote); }
}
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'name' => 'Alice']);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('en_US'));
$permissionRow = ['id' => 1, 'userid' => 42, 'deleted' => 0, 'sales' => json_encode(['quote' => ['view']])];
$adapter->rows = [
    'quote' => [array_replace($quote, ['contactid' => 300, 'currency' => 'EUR', 'billingname1' => 'Shared customer'])],
    'emailmessage' => [$oldMail],
    'contact' => [
        ['id' => 600, 'contactid' => 300, 'clientid' => 70, 'deleted' => 0, 'name1' => 'Shared customer'],
        ['id' => 601, 'contactid' => 300, 'clientid' => 8, 'deleted' => 0, 'name1' => 'Foreign customer'],
    ],
    'permission' => [$permissionRow],
];
$taskRequest = static function ($source = 1, bool $post = false) {
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('tasks')->setControllerName('task')->setActionName('add');
    $request->setParam('sourcequote', $source)->setParam('contactid', 601)->setParam('quoteid', 9999);
    return $request;
};
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/tasks/controllers', 'tasks');
$controller = new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http());
$controller->addAction();
$form = $controller->view->form;
check(QuoteFollowupTestTaskDb::$created === [], 'Opening follow-up form automatically saved a task');
check($form->getFilteredValues()['contactid'] === 300 && $form->getFilteredValues()['quoteid'] === 1001, 'Task defaults trusted URL IDs instead of shared quote/contact');
check($form->getValue('title') === 'Follow up quote 1001' && (int)$form->getValue('responsible') === 42, 'Task title or owner missing');
check($form->getFilteredValues()['duedate'] === date('Y-m-d'), 'Overdue follow-up did not default task date to today');
$adapter->rows['emailmessage'] = [$newMail];
$futureController = new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http());
$futureController->addAction();
check($futureController->view->form->getFilteredValues()['duedate'] === (new DateTimeImmutable('today'))->modify('+4 days')->format('Y-m-d'), 'Future follow-up task date was not preserved');
check(str_contains($futureController->view->form->renderElement('duedate'), 'data-format="yyyy-mm-dd"'), 'Task date picker does not match server-side date parsing');
$adapter->rows['emailmessage'] = [$oldMail];
Zend_Registry::set('User', []);
try {
    (new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http()))->addAction();
    check(false, 'Anonymous task follow-up allowed');
} catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 403, 'Anonymous follow-up not rejected'); }
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'name' => 'Alice']);
foreach ([0, -1, '1 OR 1=1', 999] as $invalidId) {
    try {
        (new QuoteFollowupTestTaskController($taskRequest($invalidId), new Zend_Controller_Response_Http()))->addAction();
        check(false, 'Invalid internal source ID allowed');
    } catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 404, 'Unexpected invalid source error'); }
}
$adapter->rows['quote'][0]['clientid'] = 8;
try {
    (new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http()))->addAction();
    check(false, 'Foreign quote allowed as source');
} catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 404, 'Foreign quote not rejected'); }
$adapter->rows['quote'][0]['clientid'] = 7;
foreach ([['sales' => json_encode(['quote' => []])], ['tasks' => json_encode(['task' => []])]] as $deny) {
    $adapter->rows['permission'] = [array_replace($permissionRow, $deny)];
    try {
        (new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http()))->addAction();
        check(false, 'Source quote or task permission ignored');
    } catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 403, 'Permission denial not enforced'); }
}
$adapter->rows['permission'] = [$permissionRow];
$adapter->rows['task'] = [$task];
try {
    (new QuoteFollowupTestTaskController($taskRequest(), new Zend_Controller_Response_Http()))->addAction();
    check(false, 'Open task did not prevent another follow-up draft');
} catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 409, 'Stale follow-up entry not rejected'); }
unset($adapter->rows['task']);
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/sales/controllers', 'sales');
$quoteRequest = new Zend_Controller_Request_Http();
$quoteRequest->setModuleName('sales')->setControllerName('quote')->setActionName('view');
$quoteController = new QuoteFollowupTestQuoteController($quoteRequest, new Zend_Controller_Response_Http());
check($quoteController->contact(['contactid' => 300])['id'] === 600, 'Manual email used foreign contact with same visible number');
if (!defined('BASE_PATH')) define('BASE_PATH', $root);
foreach (['de_DE', 'en_US'] as $locale) {
    $emailDraft = (new DEEC_Feedback())->buildQuoteFollowupEmail(1001, $locale);
    check(str_contains($emailDraft['subject'], '1001') && str_contains($emailDraft['body'], '1001'), 'Existing follow-up email draft lost quote number');
}
check(QuoteFollowupTestTaskDb::$created === [], 'Read-only follow-up actions saved tasks');
$postRequest = $taskRequest(1, true);
$postRequest->setPost(array_filter(array_replace($form->getValues(), ['create_followup' => '1', 'contactid' => 999, 'quoteid' => 999, 'id' => 999, 'state' => 105]), static fn($value) => $value !== null));
$postController = new QuoteFollowupTestTaskController($postRequest, new Zend_Controller_Response_Http());
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();
$postController->addAction();
check(count(QuoteFollowupTestTaskDb::$created) === 1, 'Explicit manual submission did not create exactly one task');
$saved = QuoteFollowupTestTaskDb::$created[0];
check($saved['contactid'] === 300 && $saved['quoteid'] === 1001 && $saved['state'] === 100 && !isset($saved['id']), 'Manual task save accepted manipulated source association');

// Feedback history keeps every request and summarizes the latest received answer independently of open requests.
$pendingAnswer = array_replace($feedback, ['id' => 20, 'created' => '2026-10-01 10:00:00', 'responded' => '2026-10-03 11:00:00', 'status' => 'pending', 'message' => 'First decision']);
$interestedAnswer = array_replace($feedback, ['id' => 21, 'created' => '2026-10-10 10:00:00', 'responded' => '2026-10-12 11:00:00', 'status' => 'interested', 'message' => "<script>alert('x')</script> & Grüße\n" . str_repeat('Full customer message ', 30)]);
$openRequest = array_replace($feedback, ['id' => 22, 'created' => '2026-10-13 10:00:00', 'token' => str_repeat('a', 64)]);
$feedbackList = new Sales_Model_List_Quotes(['view' => new QuoteMonitoringTestView()]);
$feedbackColumn = array_values(array_filter($feedbackList->getColumns(), static fn($column) => $column['name'] === 'monitoring'))[0];
$renderFeedback = static fn($row) => $feedbackList->renderCell($row, $feedbackColumn);
foreach ([[], [$openRequest], [$pendingAnswer], [$pendingAnswer, $interestedAnswer], [$pendingAnswer, $interestedAnswer, $openRequest]] as $requests) {
    $adapter->rows = ['feedback' => $requests];
    $row = $service->enrich([$quote])[0];
    $answered = array_values(array_filter($requests, static fn($request) => !empty($request['responded'])));
    $expectedId = $answered ? end($answered)['id'] : ($requests[0]['id'] ?? 0);
    check($row['monitoring_feedback_id'] === $expectedId, 'New open request displaced latest received answer');
    check($row['monitoring_feedback_has_open'] === in_array($openRequest, $requests, true), 'Additional open request flag lost');
    if ($answered) {
        $html = $renderFeedback($row);
        check(str_contains($html, 'QUOTES_MONITORING_VIEW_RESPONSE') && str_contains($html, '#quote-feedback-history'), 'Response history link missing');
        check(str_contains($html, '/sales/quote/view/1/1#quote-feedback-history'), 'History link did not use internal quote ID and separate history parameter');
        check(str_contains($html, 'QUOTES_MONITORING_FEEDBACK_RECEIVED') && !str_contains($html, '<script>'), 'Latest answer badge missing or unsafe');
        check(!str_contains($html, str_repeat('Full customer message ', 30)), 'Monitoring summary did not truncate long customer messages');
        if (count($requests) === 3) check(str_contains($html, 'QUOTES_MONITORING_FEEDBACK_OPEN') && $row['monitoring_action'] === 'next_step', 'Open request hid customer action or open badge');
    }
}
// Order by response time, even when an older request was answered later.
$adapter->rows = ['feedback' => [$interestedAnswer, array_replace($pendingAnswer, ['responded' => '2026-10-15 12:00:00'])]];
check($service->enrich([$quote])[0]['monitoring_feedback_status'] === 'pending', 'Request creation order displaced latest response time');
$invalidFeedback = [];
foreach (['clientid' => 8, 'module' => 'purchases', 'controller' => 'invoice', 'type' => 'other', 'parentid' => 2, 'deleted' => 1] as $field => $value) {
    $invalidFeedback[] = array_replace($interestedAnswer, [$field => $value, 'id' => 999]);
}
$adapter->rows = ['feedback' => $invalidFeedback];
check($service->enrich([$quote])[0]['monitoring_feedback_id'] === 0, 'Foreign or unrelated feedback leaked into summary');
$adapter->rows = ['feedback' => [array_replace($interestedAnswer, ['clientid' => 8])]];
$pair = $service->enrich([$quote, array_replace($quote, ['id' => 2, 'clientid' => 8])]);
check($pair[0]['monitoring_feedback_id'] === 0 && $pair[1]['monitoring_feedback_id'] === 0, 'Bulk feedback tenant IDs were not paired with their parent IDs');

class QuoteFeedbackHistoryTestView extends Zend_View
{
    public DEEC_Translate $translator;
    public function translate($key) { return $this->translator->t($key); }
}
class QuoteFeedbackHistoryTestOptions extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Options'; }
    public function applyFormOptions($form): array { return []; }
}
Zend_Controller_Action_HelperBroker::addHelper(new QuoteFeedbackHistoryTestOptions());
class QuoteFeedbackHistoryTestController extends QuoteFollowupTestQuoteController
{
    public function init()
    {
        parent::init();
        $this->view = new QuoteFeedbackHistoryTestView();
        $this->_flashMessenger = new class { public function getMessages(): array { return []; } };
    }
    protected function getAttachmentService() { return null; }
    protected function getEmailFormFactory()
    {
        return new class {
            public function build(...$args): DEEC_Form
            {
                $form = new DEEC_Form();
                $form->addElement(['name' => 'subject', 'type' => 'text', 'value' => 'Original subject']);
                $form->addElement(['name' => 'body', 'type' => 'textarea', 'value' => 'Original body']);
                return $form;
            }
        };
    }
    protected function getEditToolbar() { return null; }
    public function visibleQuote(int $id): ?array { return $this->loadRow($id); }
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$declinedAnswer = array_replace($interestedAnswer, ['id' => 23, 'status' => 'declined', 'reason' => 'price']);
$historyRequest = static function (array $params = []) {
    $request = new Zend_Controller_Request_Http();
    return $request->setModuleName('sales')->setControllerName('quote')->setActionName('view')->setParams($params + ['id' => 1]);
};
$adapter->rows = ['quote' => [$quote, array_replace($quote, ['id' => 2, 'clientid' => 8])], 'feedback' => array_merge([$pendingAnswer, $interestedAnswer, $openRequest, $declinedAnswer], $invalidFeedback)];
$historyController = new QuoteFeedbackHistoryTestController($historyRequest(['history' => 1]), new Zend_Controller_Response_Http());
$historyController->viewAction();
$history = $historyController->view->feedbackHistory;
check(array_column($history, 'id') === [23, 22, 21, 20], 'Detail history missing answers, order or tenant/type scope');
check(!isset($history[1]['token']) && !str_contains(json_encode($history), str_repeat('a', 64)), 'Feedback token exposed to detail view');
check($historyController->view->activeTab === 'messages', 'History link did not activate messages tab');
check($historyController->view->emailForm->getValue('subject') === 'Original subject' && $historyController->view->emailForm->getValue('body') === 'Original body' && !$historyController->view->emailForm->getElement('feedback'), 'Read-only history link prepared a follow-up email');
check($historyController->visibleQuote(2) === null, 'Foreign quote accessible through history view');
$feedbackBefore = $adapter->rows['feedback'];
foreach (['de_DE', 'en_US'] as $locale) {
    $view = $historyController->view;
    $view->translator = new DEEC_Translate($locale);
    $view->translator->loadDir('default', $root . '/languages/' . $locale . '/default');
    $view->setScriptPath($root . '/application/views/scripts');
    $view->feedbackHistory = $history;
    $html = $view->render('partials/quote-feedback.phtml');
    check(!str_contains($html, 'FEEDBACK_') && !str_contains($html, str_repeat('a', 64)), 'History has untranslated labels or tokens');
    check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>') && str_contains($html, '&amp; Grüße'), 'Customer HTML or special characters unsafe');
    check(str_contains($html, str_repeat('Full customer message ', 30)) && str_contains($html, '<br'), 'Full message or line breaks lost');
    check(str_contains($html, $view->translator->t('FEEDBACK_QUOTE_REASON_PRICE')), 'Decline reason missing from detail');
    check(strpos($html, '2026-10-13') < strpos($html, '2026-10-01'), 'History not newest request first');
    $view->feedbackHistory = [];
    check(str_contains($view->render('partials/quote-feedback.phtml'), $view->translator->t('FEEDBACK_HISTORY_EMPTY')), 'Empty feedback history not explained');
}
$adapter->rows['quote'][0]['cancelled'] = 1;
$adapter->rows['quote'][0]['state'] = 106;
$cancelledHistory = new QuoteFeedbackHistoryTestController($historyRequest(['history' => 1]), new Zend_Controller_Response_Http());
$cancelledHistory->viewAction();
check(count($cancelledHistory->view->feedbackHistory) === 4 && $cancelledHistory->view->form->getMode() === 'readonly', 'Cancelled quote history unavailable or editable');
check($adapter->rows['feedback'] === $feedbackBefore, 'Reading cancelled quote mutated feedback');
foreach ([[], [$openRequest], [$pendingAnswer], [$pendingAnswer, $interestedAnswer], [$pendingAnswer, $openRequest]] as $entries) {
    $adapter->rows['feedback'] = $entries;
    $detail = new QuoteFeedbackHistoryTestController($historyRequest(['history' => 1]), new Zend_Controller_Response_Http());
    $detail->viewAction();
    check(count($detail->view->feedbackHistory) === count($entries), 'Entity history lost empty/open/answered/multiple request cases');
}
$adapter->rows['feedback'] = $feedbackBefore;
$adapter->rows['quote'][0] = $quote;
Zend_Registry::set('DEEC_Translate', $historyController->view->translator);
$composer = new QuoteFeedbackHistoryTestController($historyRequest(['feedback' => 1]), new Zend_Controller_Response_Http());
$composer->viewAction();
check((int)$composer->view->emailForm->getValue('feedback') === 1 && $composer->view->emailForm->getValue('subject') !== 'Original subject' && $composer->view->activeTab === 'messages', 'Existing feedback=1 email preparation broken');

echo 'PASS: ' . $checks . " quote monitoring, defaults, customer actions, due dates and tenant cases\n";
