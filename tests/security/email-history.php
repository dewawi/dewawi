<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/email-history.php (in-memory fixtures, no database or SMTP).
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
require_once $root . '/application/modules/contacts/controllers/EmailController.php';
require_once $root . '/application/controllers/helpers/Params.php';
require_once $root . '/application/controllers/helpers/Query.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

// Evaluate the generated SQL predicates rather than replacing the access policy.
function historySplit(string $sql, string $operator): array
{
    $parts = [];
    $depth = 0;
    $quoted = false;
    $start = 0;
    for ($i = 0; $i < strlen($sql); $i++) {
        if ($sql[$i] === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) $quoted = !$quoted;
        if ($quoted) continue;
        if ($sql[$i] === '(') $depth++;
        if ($sql[$i] === ')') $depth--;
        if ($depth === 0 && substr($sql, $i, strlen($operator)) === $operator) {
            $parts[] = substr($sql, $start, $i - $start);
            $i += strlen($operator) - 1;
            $start = $i + 1;
        }
    }
    $parts[] = substr($sql, $start);
    return $parts;
}

function historyValue(string $sql, array $row)
{
    $sql = trim($sql);
    if (preg_match('/^COALESCE\((.*), (.*)\)$/', $sql, $match)) return historyValue($match[1], $row) ?? historyValue($match[2], $row);
    if ($sql === 'NULL') return null;
    if (is_numeric($sql)) return (int)$sql;
    if ($sql[0] === "'") return stripcslashes(substr($sql, 1, -1));
    if (array_key_exists($sql, $row)) return $row[$sql];
    throw new RuntimeException('Unsupported fixture value: ' . $sql);
}

function historyMatches(string $sql, array $row, array $tables): bool
{
    $sql = trim($sql);
    foreach ([' OR ', ' AND '] as $operator) {
        $parts = historySplit($sql, $operator);
        if (count($parts) < 2) continue;
        $matches = array_map(static fn($part) => historyMatches($part, $row, $tables), $parts);
        return $operator === ' OR ' ? in_array(true, $matches, true) : !in_array(false, $matches, true);
    }
    if ($sql[0] === '(' && substr($sql, -1) === ')') return historyMatches(substr($sql, 1, -1), $row, $tables);
    if (preg_match('/^EXISTS \(SELECT .*? FROM (\w+) AS history_document WHERE (.*)\)$/', $sql, $match)) {
        foreach ($tables[$match[1]] ?? [] as $document) {
            $context = $row;
            foreach ($document as $field => $value) $context['history_document.' . $field] = $value;
            if (historyMatches($match[2], $context, $tables)) return true;
        }
        return false;
    }
    if (!preg_match('/^(.*?) (=|>|LIKE) (.*)$/', $sql, $match)) throw new RuntimeException('Unsupported fixture predicate: ' . $sql);
    $left = historyValue($match[1], $row);
    $right = historyValue($match[3], $row);
    if ($left === null || $right === null) return false;
    return match ($match[2]) {
        '=' => (string)$left === (string)$right,
        '>' => $left > $right,
        'LIKE' => preg_match('/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($right, '/')) . '$/iu', $left) === 1,
    };
}

class EmailHistoryTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $queries = [];
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $fields = ['id', 'clientid', 'deleted'];
        foreach ($this->rows[$tableName] ?? [] as $row) $fields = array_merge($fields, array_keys($row));
        $metadata = [];
        foreach (array_unique($fields) as $field) {
            $metadata[$field] = ['COLUMN_NAME' => $field, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $field === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $field === 'id'];
        }
        return $metadata;
    }
    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $this->queries[] = $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $alias = array_key_first($from);
        $table = $from[$alias]['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], function ($row) use ($sql, $alias): bool {
            $context = $row;
            foreach ($row as $field => $value) $context[$alias . '.' . $field] = $value;
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                $where = trim(preg_replace('/\s+/', ' ', str_replace('`', '', preg_replace('/^AND /', '', $where))));
                if (!historyMatches($where, $context, $this->rows)) return false;
            }
            return true;
        }));
        if ($table === 'emailmessage') usort($rows, static fn($a, $b) => $b['id'] <=> $a['id']);
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function insert($table, array $bind) { throw new RuntimeException('History must not write'); }
    public function update($table, array $bind, $where = '') { throw new RuntimeException('History must not write'); }
    public function delete($table, $where = '') { throw new RuntimeException('History must not write'); }
}

class EmailHistoryTestOptions extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Options'; }
    public function getOptions($form) { return []; }
}
class EmailHistoryTestDirectory extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Directory'; }
    public function getUrl($id) { return 'history-test'; }
}
class EmailHistoryTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function getMessages() { return []; }
}
class EmailHistoryTestLayout extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Layout'; }
    public function disableLayout() {}
}
class EmailHistoryTestController extends Contacts_EmailController
{
    public function init()
    {
        $this->view = new Zend_View();
        $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
    }
}

$adapter = new EmailHistoryTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Controller_Front::getInstance()->setControllerDirectory(['contacts' => $root . '/application/modules/contacts/controllers']);
Zend_Controller_Action_HelperBroker::addHelper(new EmailHistoryTestOptions());
Zend_Controller_Action_HelperBroker::addHelper(new EmailHistoryTestDirectory());
Zend_Controller_Action_HelperBroker::addHelper(new EmailHistoryTestFlashMessenger());
Zend_Controller_Action_HelperBroker::addHelper(new EmailHistoryTestLayout());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Params());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Query());

function setupEmailHistory(bool $shared): void
{
    global $adapter;
    $contactClient = $shared ? 70 : 7;
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
    Zend_Registry::set('Client', ['id' => $contactClient, 'parentid' => $shared ? 70 : 0, 'modules' => ['contacts' => $contactClient, 'sales' => 7, 'purchases' => 7, 'processes' => 7]]);
    $document = ['id' => 10, 'quoteid' => 1001, 'contactid' => 500, 'clientid' => 7, 'deleted' => 0];
    $message = ['id' => 100, 'documentid' => 10, 'campaignid' => null, 'contactid' => 1000, 'module' => 'sales', 'controller' => 'quote', 'clientid' => $contactClient, 'deleted' => 0, 'subject' => 'Quote', 'body' => 'History body'];
    $adapter->rows = [
        'permission' => [['id' => 1, 'userid' => 42, 'clientid' => 7, 'deleted' => 0, 'contacts' => '{"contact":["view"]}', 'sales' => '{"quote":["view"]}', 'purchases' => '{"purchaseorder":["view"]}', 'processes' => '{"process":["view"]}']],
        'quote' => [$document, array_replace($document, ['id' => 11, 'clientid' => 8]), array_replace($document, ['id' => 12, 'deleted' => 1])],
        'purchaseorder' => [$document, array_replace($document, ['id' => 11, 'clientid' => 8])],
        'invoice' => [$document],
        'process' => [$document, array_replace($document, ['id' => 11, 'clientid' => 8])],
        'task' => [$document, array_replace($document, ['id' => 11, 'clientid' => 8])],
        'campaign' => [$document, array_replace($document, ['id' => 11, 'clientid' => 8])],
        'contact' => [
            ['id' => 1000, 'contactid' => 500, 'clientid' => $contactClient, 'deleted' => 0],
            ['id' => 1001, 'contactid' => 501, 'clientid' => 8, 'deleted' => 0],
            ['id' => 1002, 'contactid' => 502, 'clientid' => $contactClient, 'deleted' => 1],
        ],
        'user' => [],
        'emailmessage' => [
            $message,
            array_replace($message, ['id' => 101, 'documentid' => 11]),
            array_replace($message, ['id' => 102, 'documentid' => 12]),
            array_replace($message, ['id' => 103, 'documentid' => 999]),
            array_replace($message, ['id' => 104, 'documentid' => 0]),
            array_replace($message, ['id' => 105, 'documentid' => null]),
            array_replace($message, ['id' => 106, 'deleted' => 1]),
            array_replace($message, ['id' => 107, 'clientid' => 8]),
            array_replace($message, ['id' => 108, 'module' => 'unknown']),
            array_replace($message, ['id' => 109, 'controller' => 'unknown']),
            array_replace($message, ['id' => 110, 'module' => null, 'controller' => null]),
            array_replace($message, ['id' => 111, 'documentid' => 0, 'module' => null, 'controller' => null]),
            array_replace($message, ['id' => 112, 'documentid' => 0, 'module' => 'contacts', 'controller' => 'contact']),
            array_replace($message, ['id' => 113, 'documentid' => 10, 'module' => 'contacts', 'controller' => 'contact']),
            array_replace($message, ['id' => 114, 'module' => 'purchases', 'controller' => 'purchaseorder']),
            array_replace($message, ['id' => 115, 'module' => 'purchases', 'controller' => 'purchaseorder', 'documentid' => 11]),
            array_replace($message, ['id' => 116, 'controller' => 'invoice']),
            array_replace($message, ['id' => 117, 'documentid' => 0, 'module' => 'unknown', 'controller' => 'unknown']),
            array_replace($message, ['id' => 118, 'module' => 'processes', 'controller' => 'process']),
            array_replace($message, ['id' => 119, 'module' => 'processes', 'controller' => 'process', 'documentid' => 11]),
            array_replace($message, ['id' => 120, 'module' => 'tasks', 'controller' => 'task']),
            array_replace($message, ['id' => 121, 'module' => 'tasks', 'controller' => 'task', 'documentid' => 11]),
            array_replace($message, ['id' => 122, 'module' => 'campaigns', 'controller' => 'campaign', 'documentid' => 0, 'campaignid' => 10]),
            array_replace($message, ['id' => 123, 'module' => 'campaigns', 'controller' => 'campaign', 'documentid' => 0, 'campaignid' => 11]),
            array_replace($message, ['id' => 124, 'module' => 'sales', 'controller' => 'quote', 'documentid' => 1001]),
        ],
    ];
    $adapter->queries = [];
}

function emailHistory(array $params = []): array
{
    return array_column((new Contacts_Model_Get())->emailmessages(array_replace(['documentid' => 0, 'contactid' => 1000, 'module' => '', 'controller' => '', 'keyword' => '', 'limit' => 100], $params), []), 'id');
}

function controllerHistory(string $action, string $method, array $context = [], $contactId = 500): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = [];
    $_GET = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('contacts')->setControllerName('email')->setActionName($action)->setParam('contactid', $contactId);
    if ($method === 'POST') $request->setPost($context);
    else $request->setQuery($context);
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $action === 'search' ? 'XMLHttpRequest' : '';
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $controller = new EmailHistoryTestController($request, $response);
    $controller->{$action . 'Action'}();
    return array_column($controller->view->emailmessages, 'id');
}

foreach ([false, true] as $shared) {
    setupEmailHistory($shared);
    $client = Zend_Registry::get('Client');
    $quoteContext = ['module' => 'sales', 'controller' => 'quote', 'documentid' => 10];
    check(emailHistory($quoteContext) === [100], 'Own quote unavailable');
    check(emailHistory() === [122, 120, 118, 114, 112, 100], 'General history exposed an unauthorized or invalid document');
    check(emailHistory(['limit' => 1]) === [122], 'Unauthorized messages consumed the history limit');
    check(emailHistory(['keyword' => 'Quote', 'limit' => 1]) === [122], 'Keyword filtering bypassed access');
    check(emailHistory(['contactid' => 0]) === [122, 120, 118, 114, 112, 100], 'Unfiltered history bypassed access');
    check(emailHistory(['module' => 'contacts', 'controller' => 'contact']) === [112], 'Contact mail without document unavailable');
    foreach ([11, 12, 999, 1001, -1, [], '10 OR 1=1', '10x'] as $id) {
        check(emailHistory(array_replace($quoteContext, ['documentid' => $id])) === [], 'Invalid or foreign document accepted');
    }
    foreach ([['module' => ''], ['controller' => ''], ['module' => []], ['controller' => []], ['module' => 'unknown'], ['controller' => 'invoice'], ['module' => ' sales '], ['controller' => 'quóte'], ['module' => 'sales OR 1=1']] as $override) {
        check(emailHistory(array_replace($quoteContext, $override)) === [], 'Manipulated document context accepted');
    }
    foreach ([1001, 1002, 9999, -1, [], '1000x'] as $contactId) {
        check(emailHistory(['contactid' => $contactId]) === [], 'Foreign, deleted or malformed contact accepted');
    }
    check(emailHistory(array_replace($quoteContext, ['contactid' => 1001])) === [100], 'Contact override changed the authorized document');
    check(Zend_Registry::get('Client') === $client, 'History changed the global client');

    foreach (['index', 'search'] as $action) {
        foreach (['GET', 'POST'] as $method) {
            check(controllerHistory($action, $method, $quoteContext) === [100], 'Controller lost allowed document history');
            check(controllerHistory($action, $method, array_replace($quoteContext, ['documentid' => 11])) === [], 'Controller exposed sibling quote');
            check(controllerHistory($action, $method, ['documentid' => 10]) === [], 'Missing context widened controller query');
            check(controllerHistory($action, $method, ['module' => [], 'controller' => 'quote', 'documentid' => 10]) === [], 'Controller accepted malformed context');
            check(controllerHistory($action, $method, [], 1000) === [122, 120, 118, 114, 112, 100], 'Contact controller history bypassed access');
            check(controllerHistory($action, $method, [], []) === [], 'Controller converted malformed contact into a broad query');
        }
    }

    foreach ([null, '', '{invalid', '{}', '{"quote":["add"]}'] as $rights) {
        $adapter->rows['permission'][0]['sales'] = $rights;
        check(emailHistory($quoteContext) === [], 'Missing quote read permission accepted');
        check(!in_array(100, emailHistory(), true), 'General contact query bypassed quote permission');
        foreach (['index', 'search'] as $action) {
            foreach (['GET', 'POST'] as $method) check(controllerHistory($action, $method, $quoteContext) === [], 'Controller bypassed quote permission');
        }
        check(emailHistory(['module' => 'contacts', 'controller' => 'contact']) === [112], 'Quote permission removed ordinary contact mail');
    }
    setupEmailHistory($shared);
    $adapter->rows['permission'][0]['purchases'] = '{}';
    $adapter->rows['permission'][0]['processes'] = '{}';
    check(emailHistory() === [122, 120, 112, 100], 'Other document modules bypassed read permissions');
    setupEmailHistory($shared);
    $adapter->rows['permission'][0]['contacts'] = '{}';
    check(emailHistory() === [] && emailHistory($quoteContext) === [], 'Missing contact read permission accepted');
    foreach (['index', 'search'] as $action) {
        foreach (['GET', 'POST'] as $method) check(controllerHistory($action, $method, [], 1000) === [], 'Controller bypassed contact permission');
    }

    // All existing document models must use their internal IDs and selected tenant.
    foreach (['sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'], 'purchases' => ['quoterequest', 'purchaseorder'], 'processes' => ['process']] as $module => $controllers) {
        foreach ($controllers as $controller) {
            setupEmailHistory($shared);
            $adapter->rows['permission'][0][$module] = json_encode([$controller => ['view']]);
            $adapter->rows[$controller] = $adapter->rows['quote'];
            $message = $adapter->rows['emailmessage'][0];
            $adapter->rows['emailmessage'] = [
                array_replace($message, ['module' => $module, 'controller' => $controller]),
                array_replace($message, ['id' => 101, 'module' => $module, 'controller' => $controller, 'documentid' => 11]),
            ];
            check(emailHistory() === [100], 'Document model did not enforce tenant: ' . $controller);
            check(emailHistory(['module' => $module, 'controller' => $controller, 'documentid' => 10]) === [100], 'Document history unavailable: ' . $controller);
        }
    }
}

setupEmailHistory(true);
$user = Zend_Registry::get('User');
$user['clientid'] = 8;
Zend_Registry::set('User', $user);
check(emailHistory(['module' => 'sales', 'controller' => 'quote', 'documentid' => 11]) === [101], 'Selected sibling tenant cannot read its own quote');
check(emailHistory(['module' => 'sales', 'controller' => 'quote', 'documentid' => 10]) === [], 'Module mapping overrode the selected sales tenant');
setupEmailHistory(true);
$adapter->rows['permission'] = [];
check(emailHistory() === [], 'History allowed missing permissions');
setupEmailHistory(true);
$user = Zend_Registry::get('User');
$user['clientid'] = 0;
Zend_Registry::set('User', $user);
check(emailHistory() === [], 'History allowed a missing selected tenant');
Zend_Registry::getInstance()->offsetUnset('User');
check(emailHistory() === [], 'History allowed a missing user');

echo 'PASS: ' . $checks . " email history permission, tenant-sharing, document mapping and controller checks\n";
