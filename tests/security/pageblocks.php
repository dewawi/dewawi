<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/pageblocks.php (in-memory fixtures, no database required).
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
require_once $root . '/application/controllers/TrashController.php';
require_once $root . '/application/modules/admin/controllers/PageblockController.php';
require_once $root . '/application/plugins/Acl.php';
require_once $root . '/application/controllers/helpers/Access.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function pageblockMatches($where, array $row): bool
{
    foreach ((array)$where as $condition) {
        $condition = trim(preg_replace('/^AND /', '', $condition));
        if ($condition[0] === '(' && substr($condition, -1) === ')') $condition = substr($condition, 1, -1);
        if (preg_match('/^id IN \(([0-9,]+)\)$/', $condition, $match)) {
            if (!in_array((int)$row['id'], array_map('intval', explode(',', $match[1])), true)) return false;
            continue;
        }
        if (!preg_match('/^([a-z]+) (=|>) (.*)$/', $condition, $match)) throw new RuntimeException('Unsupported fixture predicate: ' . $condition);
        $value = trim($match[3], " '");
        if ($match[2] === '=' && (string)($row[$match[1]] ?? '') !== $value) return false;
        if ($match[2] === '>' && $row[$match[1]] <= $value) return false;
    }
    return true;
}

class PageblockTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    private ?array $snapshot = null;
    private int $lastId = 100;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function beginTransaction() { $this->snapshot = $this->rows; return $this; }
    public function commit() { $this->snapshot = null; return $this; }
    public function rollBack() { $this->rows = $this->snapshot; $this->snapshot = null; return $this; }
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
        $rows = array_values(array_filter($this->rows[$table], static fn($row) => pageblockMatches($sql->getPart(Zend_Db_Select::WHERE), $row)));
        $orders = $sql->getPart(Zend_Db_Select::ORDER);
        usort($rows, static function ($a, $b) use ($orders) {
            foreach ($orders as [$field, $direction]) {
                $result = $a[$field] <=> $b[$field];
                if ($result) return $direction === 'DESC' ? -$result : $result;
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
        $this->rows[$table][] = $bind + ['id' => ++$this->lastId, 'deleted' => 0];
        return 1;
    }
    public function update($table, array $bind, $where = '')
    {
        $affected = 0;
        foreach ($this->rows[$table] as &$row) {
            if (!pageblockMatches($where, $row)) continue;
            foreach ($bind as $field => $value) {
                if ($value instanceof Zend_Db_Expr) {
                    if ((string)$value !== 'ordering + 1') throw new RuntimeException('Unexpected fixture expression');
                    $value = $row['ordering'] + 1;
                }
                $row[$field] = $value;
            }
            $affected++;
        }
        return $affected;
    }
    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class PageblockTestController extends Admin_PageblockController
{
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
        $this->view = new Zend_View();
    }
}
class PageblockTestOptions extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Options'; }
    public function applyFormOptions($form): array { return []; }
}
class PageblockTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}
class PageblockTestRequest extends Zend_Controller_Request_Http
{
    public string $body = '';
    public function getRawBody() { return $this->body; }
}

$adapter = new PageblockTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
// A child user may manage content in the authorized parent tenant context.
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 7]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 1]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
$translator = new DEEC_Translate('de_DE');
$translator->loadDir('admin', $root . '/languages/de_DE/admin');
$translator->loadDir('default', $root . '/languages/de_DE/default');
Zend_Registry::set('DEEC_Translate', $translator);
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default' => $root . '/application/controllers',
    'admin' => $root . '/application/modules/admin/controllers',
]);
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Access());
Zend_Controller_Action_HelperBroker::addHelper(new PageblockTestOptions());
Zend_Controller_Action_HelperBroker::addHelper(new PageblockTestFlashMessenger());
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);

$block = ['id' => 1, 'pageid' => 10, 'parentid' => 0, 'clientid' => 7, 'type' => 'cards', 'data' => '{"title":"Cards"}', 'ordering' => 1, 'activated' => 1, 'deleted' => 0, 'locked' => 0, 'lockedtime' => null, 'created' => null, 'createdby' => 0, 'modified' => null, 'modifiedby' => 0];
$adapter->rows = [
    'permission' => [['id' => 1, 'userid' => 42, 'deleted' => 0]],
    'page' => [['id' => 10, 'clientid' => 7, 'deleted' => 0], ['id' => 20, 'clientid' => 7, 'deleted' => 0], ['id' => 30, 'clientid' => 8, 'deleted' => 0]],
    'pageblock' => [
        $block,
        array_replace($block, ['id' => 2, 'type' => 'text', 'data' => '{"title":"<script>alert(1)</script>"}', 'ordering' => 2, 'activated' => 0]),
        array_replace($block, ['id' => 3, 'parentid' => 1, 'type' => 'card', 'data' => '{"title":"Child"}', 'locked' => 99]),
        array_replace($block, ['id' => 4, 'pageid' => 20]),
        array_replace($block, ['id' => 5, 'clientid' => 8]),
        array_replace($block, ['id' => 6, 'deleted' => 1]),
        array_replace($block, ['id' => 7, 'parentid' => 1, 'type' => 'card', 'deleted' => 1]),
        array_replace($block, ['id' => 8, 'parentid' => 1, 'type' => 'card', 'clientid' => 8]),
    ],
];

function pageblockRoute(string $action, array $params = [], array $post = [], string $method = 'POST', bool $ajax = true): array
{
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
    $request = new PageblockTestRequest();
    $request->setModuleName('admin')->setControllerName('pageblock')->setActionName($action)->setParams($params)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    return [$request, $response];
}
function runPageblock(string $action, array $params = [], array $post = [], string $method = 'POST', bool $ajax = true): array
{
    [$request, $response] = pageblockRoute($action, $params, $post, $method, $ajax);
    (new PageblockTestController($request, $response))->{$action . 'Action'}();
    return (array)json_decode($response->getBody(), true);
}
function trashPageblock(int $id): array
{
    [$request, $response] = pageblockRoute('add');
    $request->setModuleName('default')->setControllerName('trash');
    $request->body = json_encode(['module' => 'admin', 'controller' => 'pageblock', 'id' => [$id]]);
    (new TrashController($request, $response))->addAction();
    return [(array)json_decode($response->getBody(), true), $response->getHttpResponseCode()];
}

$db = new Application_Model_DbTable_Pageblock();
check(array_column($db->getBlocksByPageId(10, false, 0), 'id') === [1, 2], 'Root list leaked another page, tenant, child or deleted block');
check(array_column($db->getBlocksByPageId(10, true, 0), 'id') === [1], 'Active-only filtering changed');
check($db->hasChildren(1, $block) && !$db->canDelete($block), 'A container with a different child type is deletable');
check(!$db->hasChildren(2, $db->getById(2)), 'Leaf has children');

// Mixed block types share a page ordering, while other pages and tenants stay intact.
$outside = array_slice($adapter->rows['pageblock'], 2);
check(runPageblock('sort', ['id' => 2], ['direction' => 'up'])['ok'] === true, 'Toolbar sort request failed');
check(array_column($db->getBlocksByPageId(10, false, 0), 'id') === [2, 1], 'Mixed block types did not move');
check(array_slice($adapter->rows['pageblock'], 2) === $outside, 'Sorting changed children, another page or tenant');
check(runPageblock('sort', ['id' => 2], ['ordering' => 'down'])['ok'] === true, 'Legacy sort parameter failed');
check(array_column($db->getBlocksByPageId(10, false, 0), 'id') === [1, 2], 'Legacy sort did not move');
$before = $adapter->rows;
check(runPageblock('sort', ['id' => 5], ['direction' => 'up'])['ok'] === false, 'Foreign tenant block sorted');
check(runPageblock('sort', ['id' => 2], ['direction' => 'up'], 'GET')['ok'] === false, 'GET sorted blocks');
check(runPageblock('sort', ['id' => 2], ['direction' => 'up'], 'POST', false)['ok'] === false, 'Non-AJAX request sorted blocks');
check($adapter->rows === $before, 'Rejected sort changed data');

// The normal copy action copies live descendants and resets locks.
$copy = runPageblock('copy', ['id' => 1]);
check($copy['ok'] === true, 'Container copy failed');
$children = $db->getBlocksByPageId(10, false, $copy['id']);
check(count($children) === 1 && $children[0]['data'] === '{"title":"Child"}', 'Copy lost children or copied deleted/foreign children');
check((int)$children[0]['locked'] === 0 && $children[0]['lockedtime'] === null && (int)$children[0]['createdby'] === 42, 'Copied child retained a lock or invalid audit fields');
check($db->getBlocksByPageId(10, false, 1)[0]['id'] === 3, 'Copy changed the original hierarchy');
check(array_column($db->getBlocksByPageId(10, false, 0), 'ordering') === [1, 2, 3], 'Copy did not preserve sibling ordering');
$before = $adapter->rows;
check(runPageblock('copy', ['id' => 5])['ok'] === false && $adapter->rows === $before, 'Foreign tenant block copied');

// Creation validates the page and the existing cards/card hierarchy.
foreach ([['pageid' => 30, 'type' => 'text'], ['pageid' => 10, 'parentid' => 4, 'type' => 'card'], ['pageid' => 10, 'parentid' => 5, 'type' => 'card'], ['pageid' => 10, 'type' => 'card'], ['pageid' => 10, 'parentid' => 1, 'type' => 'text'], ['pageid' => 10, 'parentid' => -1, 'type' => 'text'], ['pageid' => 10, 'type' => 'unknown']] as $params) {
    $before = $adapter->rows;
    $rejected = false;
    try {
        runPageblock('add', $params);
    } catch (RuntimeException | InvalidArgumentException $expected) {
        $rejected = true;
    }
    check($rejected, 'Invalid page/block hierarchy accepted');
    check($adapter->rows === $before, 'Rejected creation changed data');
}
runPageblock('add', ['pageid' => 10, 'type' => 'hero']);
$newRootId = $adapter->lastInsertId();
$newRoot = $db->getById($newRootId);
check($newRoot['type'] === 'hero' && (int)$newRoot['parentid'] === 0 && (int)$newRoot['ordering'] === 4 && (int)$newRoot['activated'] === 0, 'Root creation lost type, ordering or activation');
[$result] = trashPageblock($newRootId);
check($result['deletedIds'] === [$newRootId], 'New root deletion failed');
runPageblock('add', ['pageid' => 10, 'parentid' => 1, 'type' => 'card']);
$newChildId = $adapter->lastInsertId();
check(count($db->getBlocksByPageId(10, false, 1)) === 2 && (int)$db->getById($newChildId)['activated'] === 0, 'Child creation failed');
$rootRows = $db->getBlocksByPageId(10, false, 0);
check(runPageblock('sort', ['id' => $newChildId], ['direction' => 'up'])['ok'] === true, 'Child sort failed');
check(array_column($db->getBlocksByPageId(10, false, 1), 'id') === [$newChildId, 3] && $db->getBlocksByPageId(10, false, 0) === $rootRows, 'Child sort changed the wrong hierarchy level');

// Exercise the actual DEEC_Form AJAX save, including JSON data and activation.
$save = runPageblock('edit', ['id' => $newChildId], ['title' => 'Saved child', 'activated' => '1', 'type' => 'text', 'pageid' => 20]);
check($save['ok'] === true, 'Auto-save failed');
$saved = $db->getById($newChildId);
check(json_decode($saved['data'], true)['title'] === 'Saved child' && (int)$saved['activated'] === 1, 'Auto-save lost title or activation');
check($saved['type'] === 'card' && (int)$saved['pageid'] === 10 && (int)$saved['parentid'] === 1, 'Auto-save changed block context');
$before = $adapter->rows;
check(runPageblock('edit', ['id' => 5], ['title' => 'Forbidden'])['ok'] === false && $adapter->rows === $before, 'Foreign tenant block edited');

// The existing trash route uses the real model and enforces admin and tenant access.
[$result] = trashPageblock(1);
check($result['ok'] === false && $db->getById(1) !== null, 'Trash orphaned a container child');
[$result] = trashPageblock(5);
check($result['deletedIds'] === [] && $adapter->rows['pageblock'][4]['deleted'] === 0, 'Trash deleted a foreign tenant block');
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 0]);
$before = $adapter->rows;
[$result, $status] = trashPageblock(2);
check($status === 403 && $result['ok'] === false && $adapter->rows === $before, 'Non-admin deleted a block through trash');
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 1]);
[$result] = trashPageblock($copy['id']);
check($result['ok'] === false, 'Copied container lost deletion protection');
[$result] = trashPageblock((int)$children[0]['id']);
check($result['deletedIds'] === [(int)$children[0]['id']], 'Leaf deletion failed');
[$result] = trashPageblock($copy['id']);
check($result['deletedIds'] === [$copy['id']] && $db->getById($copy['id']) === null, 'Empty container deletion failed');
check(array_column($db->getBlocksByPageId(10, false, 0), 'ordering') === [1, 2], 'Deletion did not normalize sibling ordering');

// All block actions remain protected by the existing admin ACL.
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write(['id' => 42]);
foreach ([0, 1] as $admin) {
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => $admin]);
    foreach (['add', 'edit', 'copy', 'delete', 'sort'] as $action) {
        [$request, $response] = pageblockRoute($action, ['id' => 1]);
        $acl = new Application_Plugin_Acl();
        $acl->setResponse($response);
        $acl->routeShutdown($request);
        check($response->getHttpResponseCode() === ($admin ? 200 : 403), 'Incorrect admin ACL for ' . $action);
    }
}

// Render the actual list/helper with standard forms, toolbar and list partial.
$view = new Zend_View();
$view->addScriptPath($root . '/application/views/scripts');
$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
$view->addHelperPath($root . '/application/modules/admin/views/helpers', 'Zend_View_Helper');
$view->user = Zend_Registry::get('User');
$html = $view->Pageblocks(['pageid' => 10]);
check(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>'), 'Block title is not escaped');
check(str_contains($html, '>Aktiv</span>') && str_contains($html, '>Inaktiv</span>') && !str_contains($html, 'class="editable '), 'Activation is not a readable status');
check(!str_contains($html, 'ADMIN_TYPE'), 'Block type header is not translated');
foreach (['edit', 'copy', 'delete', 'sortup', 'sortdown'] as $action) {
    check(str_contains($html, 'data-action="' . $action . '"'), 'Missing standard action: ' . $action);
}
check(!str_contains($html, '/type/card"'), 'Root toolbar offered a child-only block type');
$html = $view->Pageblocks(['pageid' => 10, 'parentid' => 1, 'parenttype' => 'cards']);
check(str_contains($html, 'data-list-id="pageblocks-1"') && str_contains($html, '/parentid/1'), 'Child toolbar lost hierarchy context');
check(!str_contains($html, '/type/text'), 'Child toolbar offered an invalid block type');
check(str_contains($view->Pageblocks(['pageid' => 0]), 'Seite zuerst speichern.'), 'Unsaved page guard changed');
$adapter->rows['pageblock'][1]['data'] = '{}';
check(str_contains($view->Pageblocks(['pageid' => 10]), '>Text</a>'), 'Untitled block does not use the translated type');

echo 'PASS: ' . $checks . " pageblock list, CRUD, hierarchy, ordering, ACL and tenant cases\n";
