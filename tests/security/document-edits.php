<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/document-edits.php (in-memory database, real guards and forms).
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
require_once $root . '/application/plugins/State.php';
require_once $root . '/application/controllers/helpers/Access.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function documentEditMatches($where, array $row): bool
{
    foreach ((array)$where as $condition) {
        $condition = preg_replace('/^AND /', '', $condition);
        if ($condition[0] === '(' && substr($condition, -1) === ')') $condition = substr($condition, 1, -1);
        if (!preg_match('/^([a-z]+) = (.*)$/', $condition, $match)) throw new RuntimeException('Unsupported fixture predicate');
        if ((string)($row[$match[1]] ?? '') !== trim($match[2], " '")) return false;
    }
    return true;
}

class DocumentEditTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $writes = [];
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'parentid', 'clientid', 'deleted'];
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
        $rows = array_values(array_filter($this->rows[$table] ?? [], static fn($row) => documentEditMatches($sql->getPart(Zend_Db_Select::WHERE), $row)));
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function update($table, array $bind, $where = '')
    {
        $affected = 0;
        foreach ($this->rows[$table] as &$row) {
            if (!documentEditMatches($where, $row)) continue;
            $row = array_replace($row, $bind);
            $affected++;
        }
        if ($affected) $this->writes[] = [$table, $bind];
        return $affected;
    }
}

class DocumentNotesTestController extends DEEC_Controller_DocumentAction
{
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->view = new Zend_View();
    }
    public function readonlyForm(array $row): DEEC_Form { return $this->buildViewForm($row); }
}

class DocumentEditTestOptions extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Options'; }
    public function applyFormOptions($form): array { return []; }
}

$adapter = new DocumentEditTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default' => $root . '/application/controllers',
    'sales' => $root . '/application/modules/sales/controllers',
    'purchases' => $root . '/application/modules/purchases/controllers',
    'items' => $root . '/application/modules/items/controllers',
]);
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Access());
Zend_Controller_Action_HelperBroker::addHelper(new DocumentEditTestOptions());
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;

function documentEditRoute(string $module, string $controller, string $action, array $params = [], array $post = [], string $method = 'POST'): array
{
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($module)->setControllerName($controller)->setActionName($action)->setParams($params)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $plugin = new Application_Plugin_State();
    $plugin->setResponse($response);
    $plugin->preDispatch($request);
    return [$request, $response];
}

function checkDocumentRoute(bool $allowed, string $module, string $controller, string $action, array $params, array $post = [], string $method = 'POST'): void
{
    global $adapter;
    $before = $adapter->rows;
    $writes = $adapter->writes;
    [$request, $response] = documentEditRoute($module, $controller, $action, $params, $post, $method);
    check(($response->getHttpResponseCode() === 200) === $allowed, 'Unexpected state guard result: ' . $module . '/' . $controller . '/' . $action . ' ' . json_encode($params));
    if (!$allowed) check($request->getModuleName() === 'default' && $request->getControllerName() === 'error' && $request->getActionName() === 'forbidden', 'Denied action can still dispatch');
    check($adapter->rows === $before && $adapter->writes === $writes, 'State guard changed data');
}

$modules = ['sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'], 'purchases' => ['quoterequest', 'purchaseorder']];
$permissions = ['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 0, 'items' => '{"item":["edit"]}'];
foreach ($modules as $module => $documents) $permissions[$module] = json_encode(array_fill_keys($documents, ['view', 'edit', 'add', 'delete']));
$adapter->rows['permission'] = [$permissions];
$base = [
    'id' => 1, 'clientid' => 7, 'deleted' => 0, 'state' => 105, 'completed' => 1, 'cancelled' => 0,
    'completeddate' => '2026-09-09 12:00:00', 'completedby' => 17, 'filename' => 'original.pdf',
    'notes' => 'Old note', 'title' => 'Issued document', 'subtotal' => 100, 'taxes' => 19, 'total' => 119,
    'currency' => 'EUR', 'locked' => 0, 'lockedtime' => null, 'modified' => '2026-09-09 12:00:00', 'modifiedby' => 17,
];

foreach ($modules as $module => $documents) {
    foreach ($documents as $document) {
        $issued = $base + [$document . 'id' => 1001, $document . 'date' => '2026-09-09'];
        $draft = array_replace($issued, ['id' => 2, $document . 'id' => null, 'state' => 100, 'completed' => 0]);
        $adapter->rows[$document] = [
            $issued, $draft,
            array_replace($issued, ['id' => 3, 'state' => 100, 'completed' => 0]),
            array_replace($draft, ['id' => 4, 'completed' => 1]),
            array_replace($draft, ['id' => 5, 'cancelled' => 1]),
            array_replace($issued, ['id' => 6, 'state' => 106, 'cancelled' => 1]),
            array_replace($draft, ['id' => 7, 'clientid' => 8]),
            array_replace($draft, ['id' => 8, 'deleted' => 1]),
            array_replace($draft, ['id' => 9]),
        ];
        $children = [];
        foreach ([10 => 1, 11 => 2, 12 => 3, 13 => 7, 14 => 2, 15 => 9, 16 => 6] as $id => $parentId) {
            $children[] = ['id' => $id, 'clientid' => $id === 13 ? 8 : 7, 'deleted' => $id === 14 ? 1 : 0, 'parentid' => $parentId];
        }
        $adapter->rows[$document . 'pos'] = $children;
        $adapter->rows[$document . 'posset'] = $children;
        $adapter->rows['pricerulepos'] = [];
        foreach ([20 => 10, 21 => 11, 22 => 12, 23 => 13, 24 => 14] as $id => $parentId) {
            $adapter->rows['pricerulepos'][] = ['id' => $id, 'clientid' => $id === 23 ? 8 : 7, 'deleted' => 0, 'module' => $module, 'controller' => $document . 'pos', 'parentid' => $parentId];
        }

        foreach ([1, 3, 4, 5, 6] as $id) {
            checkDocumentRoute(true, $module, $document, 'edit', ['id' => $id], ['notes' => 'New note']);
            foreach (['total', 'title', 'state', 'completeddate', 'filename', 'clientid', 'unknown'] as $field) {
                checkDocumentRoute(false, $module, $document, 'edit', ['id' => $id], ['notes' => 'New note', $field => 'changed']);
                checkDocumentRoute(false, $module, $document, 'edit', ['id' => $id], [$field => 'changed']);
            }
        }
        checkDocumentRoute(false, $module, $document, 'edit', ['id' => 1], ['notes' => ['array']]);
        checkDocumentRoute(true, $module, $document, 'edit', ['id' => 2], ['title' => 'Draft title']);
        checkDocumentRoute(false, $module, $document, 'edit', ['id' => 7], ['notes' => 'Foreign']);
        checkDocumentRoute(false, $module, $document, 'edit', ['id' => 8], ['notes' => 'Deleted']);
        checkDocumentRoute(false, $module, $document, 'delete', ['ids' => [2, 1]]);
        checkDocumentRoute(true, $module, $document, 'delete', ['ids' => [2, 9]]);

        foreach (['position', 'positionset'] as $childController) {
            foreach (['add', 'edit', 'copy', 'sort', 'delete'] as $action) {
                foreach ([1 => 10, 3 => 12, 6 => 16, 7 => 13, 8 => 11] as $parentId => $id) {
                    checkDocumentRoute(false, $module, $childController, $action, ['parent' => $document, 'parentid' => $parentId, 'id' => $id], ['id' => $id]);
                }
                $post = $action === 'edit' ? ['title' => 'Draft position'] : ['id' => 11];
                checkDocumentRoute(true, $module, $childController, $action, ['parent' => $document, 'parentid' => 2, 'id' => 11], $post);
                checkDocumentRoute(false, $module, $childController, $action, ['parent' => $document, 'parentid' => 2, 'id' => 11], $post, 'GET');
            }
            checkDocumentRoute(false, $module, $childController, 'edit', ['parent' => $document, 'parentid' => 2, 'id' => 10], ['title' => 'Forged draft context']);
            checkDocumentRoute(false, $module, $childController, 'edit', ['parent' => $document, 'parentid' => 2, 'id' => 11], ['title' => 'Draft', 'parentid' => 9]);
            checkDocumentRoute(false, $module, $childController, 'edit', ['parent' => $document, 'parentid' => 2, 'id' => 11], ['title' => 'Draft', 'clientid' => 8]);
        }
        checkDocumentRoute(false, $module, 'position', 'sort', ['parent' => $document, 'parentid' => 2, 'id' => 10], ['id' => 11, 'ordering' => 2]);
        checkDocumentRoute(false, $module, 'position', 'delete', ['parent' => $document, 'parentid' => 2], ['id' => [11, 10]]);
        checkDocumentRoute(false, $module, 'position', 'delete', ['parent' => $document, 'parentid' => 2], ['id' => [11, 15]]);
        checkDocumentRoute(false, $module, 'positionset', 'delete', ['parent' => $document, 'parentid' => 2], ['id' => [11]]);
        foreach (['masterid', 'setid'] as $field) {
            checkDocumentRoute(false, $module, 'position', 'add', ['parent' => $document, 'parentid' => 2, $field => 10]);
            checkDocumentRoute(false, $module, 'position', 'add', ['parent' => $document, 'parentid' => 2, $field => 13]);
            checkDocumentRoute(true, $module, 'position', 'add', ['parent' => $document, 'parentid' => 2, $field => 11]);
        }

        foreach (['edit', 'delete'] as $action) {
            foreach ([20, 22, 23, 24] as $id) checkDocumentRoute(false, 'items', 'pricerulepos', $action, ['id' => $id], ['amount' => '1']);
            checkDocumentRoute(true, 'items', 'pricerulepos', $action, ['id' => 21], ['amount' => '1']);
        }
        foreach ([10 => false, 11 => true, 12 => false, 13 => false, 14 => false] as $parentId => $allowed) {
            checkDocumentRoute($allowed, 'items', 'pricerulepos', 'add', ['parentid' => $parentId, 'parent_module' => $module, 'parent_controller' => $document . 'pos']);
        }

        // Save through the real document edit action; only note/modification fields may change.
        foreach ([1, 3, 4, 5, 6] as $id) {
            $before = $adapter->rows[$document][$id - 1];
            [$request, $response] = documentEditRoute($module, $document, 'edit', ['id' => $id], ['notes' => 'New note']);
            $controller = new DocumentNotesTestController($request, $response);
            $controller->editAction();
            $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            check($body['ok'] === true && $body['values']['notes'] === 'New note', 'Final note save failed');
            $after = $adapter->rows[$document][$id - 1];
            check(array_diff_key($before, array_flip(['notes', 'modified', 'modifiedby'])) === array_diff_key($after, array_flip(['notes', 'modified', 'modifiedby'])), 'Note save changed protected fields');
            check($after['modifiedby'] === 42 && $after['modified'] !== $before['modified'], 'Note save did not record modification');
            $writes = count($adapter->writes);
            $controller->editAction();
            check(count($adapter->writes) === $writes, 'Identical note changed modification metadata');
        }

        // Render an editable note inside the otherwise readonly document form.
        $form = $controller->readonlyForm($issued);
        check(!preg_match('/<textarea[^>]*\breadonly\b/', $form->renderElement('notes')) && str_contains($form->renderElement('notes'), 'data-document-note="1"'), 'Final note field is not editable');
        check(str_contains($form->renderElement('title'), 'readonly'), 'Printed document field became editable');

        // The controller also rejects mixed writes when called without the plugin.
        [$request, $response] = documentEditRoute($module, $document, 'edit', ['id' => 1], ['notes' => 'Allowed']);
        $_POST = [];
        $request->setPost(['notes' => 'Rejected', 'total' => '1']);
        $before = $adapter->rows;
        $writes = count($adapter->writes);
        (new DocumentNotesTestController($request, $response))->editAction();
        check($response->getHttpResponseCode() === 403 && $adapter->rows === $before && count($adapter->writes) === $writes, 'Controller accepted mixed final-document writes');

        // Lock ownership and edit rights are checked even without the route plugin.
        $adapter->rows[$document][0] = array_replace($issued, ['locked' => 99, 'lockedtime' => date('Y-m-d H:i:s')]);
        [$request, $response] = documentEditRoute($module, $document, 'edit', ['id' => 1], ['notes' => 'Blocked']);
        $writes = count($adapter->writes);
        (new DocumentNotesTestController($request, $response))->editAction();
        check(json_decode($response->getBody(), true)['message'] === 'locked' && count($adapter->writes) === $writes, 'Locked document note was overwritten');
        $adapter->rows['permission'][0][$module] = json_encode(array_fill_keys($documents, ['view']));
        $adapter->rows[$document][0] = $issued;
        [$request, $response] = documentEditRoute($module, $document, 'edit', ['id' => 1], ['notes' => 'Forbidden']);
        $controller = new DocumentNotesTestController($request, $response);
        $controller->editAction();
        check($response->getHttpResponseCode() === 403 && count($adapter->writes) === $writes, 'Read permission allowed note writes');
        check(str_contains($controller->readonlyForm($issued)->renderElement('notes'), 'readonly'), 'Reader received editable note UI');
        checkDocumentRoute(false, $module, 'position', 'edit', ['parent' => $document, 'parentid' => 2, 'id' => 11], ['title' => 'Forbidden']);
        checkDocumentRoute(false, 'items', 'pricerulepos', 'edit', ['id' => 21], ['amount' => '1']);
        $adapter->rows['permission'][0] = $permissions;
    }
}

// Other modules and standalone item price rules retain their existing behavior.
checkDocumentRoute(true, 'processes', 'position', 'edit', ['parent' => 'process', 'parentid' => 1, 'id' => 10]);
checkDocumentRoute(true, 'items', 'pricerulepos', 'add', ['parent_module' => 'items', 'parent_controller' => 'itempos', 'parentid' => 1]);
check(!DEEC_Permission::isDocument('processes', 'process'), 'Operational process became a frozen document');
check((new DEEC_List(['module' => 'sales', 'controller' => 'invoice']))->isReadonly((object)['invoiceid' => 1001, 'state' => 100]), 'List did not protect a numbered legacy document');

echo 'PASS: ' . $checks . " document edit checks; final notes, field protection, child routes, locks and tenant permissions\n";
