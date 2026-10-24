<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/item-tags.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    $relative = str_replace('_', '/', $class) . '.php';
    if ($path = stream_resolve_include_path($relative)) {
        require_once $path;
        return;
    }
    $parts = explode('_', $class);
    if (count($parts) >= 3) {
        $module = array_shift($parts);
        $type = strtolower(array_shift($parts)) . 's';
        $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
        $path = $root . $base . $type . '/' . implode('/', $parts) . '.php';
        if (is_file($path)) require_once $path;
    }
});

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

class TagTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public static int $lastId = 100;
    protected function _connect() {}
    public function query($sql, $bind = []) { throw new RuntimeException('Database access is forbidden in this test'); }
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function lastInsertId($tableName = null, $primaryKey = null) { return self::$lastId; }
}

class TagTestSelect extends Zend_Db_Select
{
    public function setIntegrityCheck($value) { return $this; }
}

class TagTestRow extends ArrayObject
{
    public function __construct(array $data) { parent::__construct($data, ArrayObject::ARRAY_AS_PROPS); }
    public function toArray() { return $this->getArrayCopy(); }
}

class TagTestRows extends ArrayObject
{
    public function toArray() { return array_map(static fn($row) => $row->toArray(), $this->getArrayCopy()); }
}

// Keep the real application models, SQL builder and quoting API; replace database I/O.
class Zend_Db_Table_Abstract
{
    public static $adapter;
    public static array $rows = [];
    public static array $selects = [];
    public static array $writes = [];

    public function __construct() { if (method_exists($this, 'init')) $this->init(); }
    public function getAdapter() { return self::$adapter; }
    public function select() { return new TagTestSelect($this->getAdapter()); }
    public function fetchRow($select) { return $this->fetchAll($select)[0] ?? null; }
    public function fetchAll($select)
    {
        if (is_array($select)) {
            $where = $select;
            $select = $this->select();
            foreach ($where as $condition) $select->where($condition);
        }
        if (!$select->getPart(Zend_Db_Select::FROM)) $select->from($this->_name);
        self::$selects[] = $select;
        $rows = [];
        foreach (self::$rows[$this->_name] ?? [] as $row) {
            foreach ($select->getPart(Zend_Db_Select::WHERE) as $condition) {
                $condition = preg_replace('/^AND /', '', $condition);
                $matched = false;
                foreach ($row as $field => $value) {
                    foreach ([$field, 't.' . $field] as $column) {
                        if ($condition === '(' . $this->getAdapter()->quoteInto($column . ' = ?', $value) . ')') $matched = true;
                    }
                }
                if (!$matched) continue 2;
            }
            if ($this->_name === 'tagentity') {
                $tag = self::$rows['tag'][$row['tagid']] ?? null;
                $row['tag'] = $tag && $tag['clientid'] === $row['clientid'] && !$tag['deleted'] ? $tag['title'] : null;
            }
            $rows[] = new TagTestRow($row);
        }
        return new TagTestRows($rows);
    }
    public function insert(array $data)
    {
        $id = ++TagTestAdapter::$lastId;
        $data += ['id' => $id, 'deleted' => 0];
        self::$rows[$this->_name][$id] = $data;
        self::$writes[] = [$this->_name, $data];
        return $id;
    }
    public function update(array $data, $where)
    {
        $rows = $this->fetchAll((array)$where);
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            self::$rows[$this->_name][$id] = array_replace(self::$rows[$this->_name][$id], $data);
            self::$writes[] = [$this->_name, ['id' => $id] + $data];
        }
        return count($rows);
    }
}

class Application_Model_DbTable_Permission
{
    public static array $permissions = ['add'];
    public function getPermissions() { return ['items' => json_encode(['item' => self::$permissions])]; }
}

require_once $root . '/application/plugins/Acl.php';
require_once $root . '/application/modules/items/controllers/TagController.php';

class TagTestView extends Zend_View
{
    public function translate($message) { return $message; }
    public function MultiForm($module, $controller, $row) { return '<div data-id="' . $row['id'] . '"></div>'; }
}

class TagTestController extends Items_TagController
{
    public function init() { $this->view = new TagTestView(); }
}

class TagTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}

Zend_Db_Table_Abstract::$adapter = new TagTestAdapter(['host'=>'unused', 'username'=>'unused', 'password'=>'unused', 'dbname'=>'unused']);
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>7]);
Zend_Registry::set('Client', ['id'=>7, 'parentid'=>0]);
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write(['id'=>42]);
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/items/controllers', 'items');
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::addHelper(new TagTestFlashMessenger());
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;

function fixtures(): void
{
    Zend_Db_Table_Abstract::$writes = [];
    Zend_Db_Table_Abstract::$selects = [];
    Zend_Db_Table_Abstract::$rows = [
        'item' => [
            11 => ['id'=>11, 'clientid'=>7, 'deleted'=>0],
            12 => ['id'=>12, 'clientid'=>8, 'deleted'=>0],
            13 => ['id'=>13, 'clientid'=>7, 'deleted'=>1],
        ],
        'tag' => [
            21 => ['id'=>21, 'clientid'=>7, 'module'=>'items', 'controller'=>'item', 'deleted'=>0, 'title'=>'Own tag'],
            22 => ['id'=>22, 'clientid'=>8, 'module'=>'items', 'controller'=>'item', 'deleted'=>0, 'title'=>'Foreign tag'],
            23 => ['id'=>23, 'clientid'=>7, 'module'=>'contacts', 'controller'=>'contact', 'deleted'=>0, 'title'=>'Other context'],
            24 => ['id'=>24, 'clientid'=>7, 'module'=>'items', 'controller'=>'item', 'deleted'=>1, 'title'=>'Deleted tag'],
        ],
        'tagentity' => [
            31 => ['id'=>31, 'clientid'=>7, 'entityid'=>11, 'module'=>'items', 'controller'=>'item', 'deleted'=>0, 'tagid'=>0, 'ordering'=>4],
            32 => ['id'=>32, 'clientid'=>8, 'entityid'=>11, 'module'=>'items', 'controller'=>'item', 'deleted'=>0, 'tagid'=>22, 'ordering'=>99],
        ],
    ];
}

function addTag(array $post, string $method = 'POST'): array
{
    return requestTag('add', $post, 0, $method);
}

function requestTag(string $action, array $post, $id = 0, string $method = 'POST'): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $post;
    $_GET = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('items')->setControllerName('tag')->setActionName($action)->setParam('id', $id);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $acl = new Application_Plugin_Acl();
    $acl->setResponse($response);
    $acl->routeShutdown($request);
    ob_start();
    try {
        if ($request->getModuleName() === 'items') {
            $controller = new TagTestController($request, $response);
            $controller->{$action . 'Action'}();
        }
    } finally {
        $output = ob_get_clean();
    }
    return [$response->getHttpResponseCode(), $response->getBody() . $output];
}

$checks = 0;
foreach ([null, '', '0', '-1', '11 OR 1=1', '0") OR (1=1) OR (t.entityid = "0', ['11'], '1.1', '999999999999999999999999'] as $itemId) {
    fixtures();
    [$status] = addTag(['itemid'=>$itemId, 'tagid'=>'21']);
    check($status === 400 && !Zend_Db_Table_Abstract::$writes, 'Invalid item ID accepted');
    $checks++;
}
foreach (['21 OR 1=1', ['21'], '-1', '1.1'] as $tagId) {
    fixtures();
    [$status] = addTag(['itemid'=>'11', 'tagid'=>$tagId]);
    check($status === 400 && !Zend_Db_Table_Abstract::$writes, 'Invalid tag ID accepted');
    $checks++;
}
foreach ([12, 13, 999] as $itemId) {
    fixtures();
    [$status] = addTag(['itemid'=>(string)$itemId, 'tagid'=>'21']);
    check($status === 404 && !Zend_Db_Table_Abstract::$writes, 'Foreign, deleted or missing item accepted');
    $checks++;
}
foreach ([22, 23, 24, 999] as $tagId) {
    fixtures();
    [$status] = addTag(['itemid'=>'11', 'tagid'=>(string)$tagId]);
    check($status === 404 && !Zend_Db_Table_Abstract::$writes, 'Foreign, deleted, missing or wrong-context tag accepted');
    $checks++;
}

fixtures();
[$status, $body] = addTag(['itemid'=>'11', 'tagid'=>'21', 'clientid'=>8, 'module'=>'shops']);
$row = json_decode($body, true);
check($status === 200 && $row['tagid'] === 21 && $row['clientid'] === 7 && $row['module'] === 'items' && $row['ordering'] === 5, 'Valid assignment or server-controlled fields changed');
check(count(Zend_Db_Table_Abstract::$writes) === 1, 'Valid assignment did not write exactly once');
$checks++;
[$status, $body] = addTag(['itemid'=>'11', 'tagid'=>'21']);
check(json_decode($body, true)['message'] === 'TAG_ALREADY_EXISTS' && count(Zend_Db_Table_Abstract::$writes) === 1, 'Duplicate tag inserted');
$checks++;

foreach ([[], ['tagid'=>'0'], ['tagid'=>'']] as $blankTag) {
    fixtures();
    [$status, $body] = addTag(['itemid'=>'11'] + $blankTag);
    $written = Zend_Db_Table_Abstract::$writes[0][1];
    check($status === 200 && str_contains($body, '<div') && $written['tagid'] === 0 && $written['ordering'] === 5, 'Blank tag HTML or ordering changed');
    $checks++;
}

fixtures();
addTag(['itemid'=>'11', 'tagid'=>'21'], 'GET');
check(!Zend_Db_Table_Abstract::$writes, 'GET request wrote a tag assignment');
$checks++;

fixtures();
Application_Model_DbTable_Permission::$permissions = [];
[$status] = addTag(['itemid'=>'11', 'tagid'=>'21']);
check($status === 403 && !Zend_Db_Table_Abstract::$writes, 'Missing add permission did not block assignment');
Application_Model_DbTable_Permission::$permissions = ['add'];
$checks++;

fixtures();
// Client plugin has resolved the shared items module to the authorized parent.
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>70]);
Zend_Registry::set('Client', ['id'=>7, 'parentid'=>7, 'modules'=>['items'=>7]]);
[$status, $body] = addTag(['itemid'=>'11', 'tagid'=>'21']);
check($status === 200 && json_decode($body, true)['clientid'] === 7, 'Authorized parent sharing lost');
$checks++;

fixtures();
$model = new Application_Model_DbTable_Tagentity();
foreach (['0") OR (1=1) OR (t.entityid = "0', "x' OR 1=1 -- "] as $payload) {
    $model->getTagEntities($payload, $payload, $payload);
    $select = end(Zend_Db_Table_Abstract::$selects);
    $where = $select->getPart(Zend_Db_Select::WHERE);
    check(count($where) === 5, 'Tag query does not separate its conditions');
    foreach (['t.entityid', 't.module', 't.controller'] as $i => $column) {
        $expected = ($i ? 'AND ' : '') . '(' . $model->getAdapter()->quoteInto($column . ' = ?', $payload) . ')';
        check($where[$i] === $expected, 'Tag query interpolated unquoted input');
    }
    $join = $select->getPart(Zend_Db_Select::FROM)['tag'];
    check($join['joinType'] === Zend_Db_Select::LEFT_JOIN && str_contains($join['joinCondition'], 'tag.clientid = t.clientid') && str_contains($join['joinCondition'], 'tag.deleted = 0'), 'Tag join lost isolation or blank assignments');
    $checks++;
}
$rows = $model->getTagEntities('items', 'item', 11);
check(count($rows) === 1 && $rows[0]['id'] === 31, 'Tag query included foreign assignments or lost blank tags');
$checks++;

// Existing assignments require ownership of both the assignment and the item.
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>7]);
Zend_Registry::set('Client', ['id'=>7, 'parentid'=>0]);
Application_Model_DbTable_Permission::$permissions = ['add', 'edit', 'delete'];
foreach (['edit', 'delete'] as $action) {
    foreach ([0, -1, '31 OR 1=1', ['31'], '1.1', '999999999999999999999999'] as $id) {
        fixtures();
        [$status] = requestTag($action, ['tag' => 'New tag'], $id);
        check($status === 400 && !Zend_Db_Table_Abstract::$writes, 'Invalid assignment ID accepted: ' . $action);
        $checks++;
    }
    foreach ([32, 33, 34, 35, 36, 37, 999] as $id) {
        fixtures();
        foreach ([
            33 => ['deleted' => 1],
            34 => ['module' => 'contacts', 'controller' => 'contact'],
            35 => ['entityid' => 12],
            36 => ['entityid' => 13],
            37 => ['entityid' => 999],
        ] as $rowId => $changes) {
            Zend_Db_Table_Abstract::$rows['tagentity'][$rowId] = array_replace(Zend_Db_Table_Abstract::$rows['tagentity'][31], ['id' => $rowId], $changes);
        }
        [$status] = requestTag($action, ['tag' => 'New tag'], $id);
        check($status === 404 && !Zend_Db_Table_Abstract::$writes, 'Inaccessible assignment or item accepted: ' . $action);
        $checks++;
    }

    fixtures();
    Application_Model_DbTable_Permission::$permissions = [];
    [$status] = requestTag($action, ['tag' => 'Own tag'], 31);
    check($status === 403 && !Zend_Db_Table_Abstract::$writes, 'Missing permission allowed tag mutation: ' . $action);
    Application_Model_DbTable_Permission::$permissions = ['add', 'edit', 'delete'];
    $checks++;

    fixtures();
    requestTag($action, ['tag' => 'New tag'], 31, 'GET');
    check(!Zend_Db_Table_Abstract::$writes, 'GET request changed an assignment: ' . $action);
    $checks++;
}

foreach ([[], ['tag' => []], ['tag' => ['nested' => ['unexpected']]]] as $post) {
    fixtures();
    [$status] = requestTag('edit', $post, 31);
    check($status === 400 && !Zend_Db_Table_Abstract::$writes, 'Missing or malformed tag title accepted');
    $checks++;
}

fixtures();
[$status, $body] = requestTag('edit', [
    'tag' => 'Own tag', 'id' => 32, 'clientid' => 8, 'entityid' => 12,
    'module' => 'contacts', 'controller' => 'contact', 'deleted' => 1, 'ordering' => 99,
], 31);
$row = Zend_Db_Table_Abstract::$rows['tagentity'][31];
check($status === 200 && json_decode($body, true)['tagid'] === 21, 'Existing tag could not be selected');
check($row['tagid'] === 21 && $row['clientid'] === 7 && $row['entityid'] === 11 && $row['module'] === 'items' && $row['controller'] === 'item' && $row['deleted'] === 0 && $row['ordering'] === 4, 'Extra POST fields changed assignment ownership or context');
check(count(Zend_Db_Table_Abstract::$writes) === 1 && $row['modifiedby'] === 42, 'Existing tag selection did not produce one audited update');
$checks++;

fixtures();
[$status, $body] = requestTag('edit', ['tag' => ' <b>New tag</b> '], 31);
$tagId = json_decode($body, true)['tagid'];
$tag = Zend_Db_Table_Abstract::$rows['tag'][$tagId];
check($status === 200 && $tag['title'] === 'New tag' && $tag['clientid'] === 7 && $tag['module'] === 'items' && $tag['controller'] === 'item', 'New tag did not use the existing form filters and current context');
check(count(Zend_Db_Table_Abstract::$writes) === 2 && Zend_Db_Table_Abstract::$rows['tagentity'][31]['tagid'] === $tagId, 'New tag was not assigned');
$checks++;

fixtures();
[$status] = requestTag('delete', [], 31);
check($status === 200 && Zend_Db_Table_Abstract::$rows['tagentity'][31]['deleted'] === 1, 'Own assignment could not be deleted');
check(Zend_Db_Table_Abstract::$rows['tagentity'][32]['deleted'] === 0 && count(Zend_Db_Table_Abstract::$writes) === 1, 'Deleting own assignment changed foreign data');
$checks++;

// Model methods must also restrict writes when called without the controller.
foreach (['updateTagEntity', 'deleteTagEntity'] as $method) {
    fixtures();
    $model = new Application_Model_DbTable_Tagentity();
    $model->$method(32, ['tagid' => 21]);
    check(!Zend_Db_Table_Abstract::$writes, 'Model modified a foreign assignment: ' . $method);
    Zend_Db_Table_Abstract::$rows['tagentity'][31]['deleted'] = 1;
    $model->$method(31, ['tagid' => 21]);
    check(!Zend_Db_Table_Abstract::$writes, 'Model modified a deleted assignment: ' . $method);
    $checks++;
}
fixtures();
(new Application_Model_DbTable_Tagentity())->updateTagEntity(31, ['tagid' => 21, 'clientid' => 8, 'entityid' => 12, 'module' => 'contacts', 'controller' => 'contact']);
$row = Zend_Db_Table_Abstract::$rows['tagentity'][31];
check($row['clientid'] === 7 && $row['entityid'] === 11 && $row['module'] === 'items' && $row['controller'] === 'item' && $row['tagid'] === 21, 'Model allowed changing assignment ownership or context');
$checks++;

// Preserve edits and deletions in an authorized parent's shared item context.
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>70]);
Zend_Registry::set('Client', ['id'=>7, 'parentid'=>7, 'modules'=>['items'=>7]]);
foreach (['edit', 'delete'] as $action) {
    fixtures();
    [$status] = requestTag($action, ['tag' => 'Own tag'], 31);
    check($status === 200 && count(Zend_Db_Table_Abstract::$writes) === 1, 'Authorized parent context lost tag mutation: ' . $action);
    $checks++;
}

echo 'PASS: ' . $checks . " item tag security and compatibility cases\n";
