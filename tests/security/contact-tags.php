<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/contact-tags.php (in-memory database).
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

function contactTagWhereMatches(string $where, array $row): bool
{
    $where = trim(preg_replace('/^AND /', '', $where));
    if ($where !== '' && $where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);

    if (preg_match('/^(?:[a-z0-9_]+\.)?([a-z0-9_]+) IN \(([^)]*)\)$/i', $where, $match)) {
        $values = array_map(static fn($value) => trim($value, " '\""), explode(',', $match[2]));
        return array_key_exists($match[1], $row) && in_array((string)$row[$match[1]], $values, true);
    }

    if (preg_match('/^(?:[a-z0-9_]+\.)?([a-z0-9_]+) != (.*)$/i', $where, $match)) {
        return array_key_exists($match[1], $row) && (string)$row[$match[1]] !== trim($match[2], " '\"");
    }

    if (!preg_match('/^(?:[a-z0-9_]+\.)?([a-z0-9_]+) = (.*)$/i', $where, $match)) {
        throw new RuntimeException('Unsupported fixture predicate: ' . $where);
    }

    return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '\"");
}

class ContactTagTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public static int $lastId = 100;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }
    public function query($sql, $bind = []) { throw new RuntimeException('Unexpected database access'); }
    public function lastInsertId($tableName = null, $primaryKey = null) { return self::$lastId; }
}

class ContactTagTestSelect extends Zend_Db_Select
{
    public function setIntegrityCheck($value) { return $this; }
}

class ContactTagTestRow extends ArrayObject
{
    public function __construct(array $data) { parent::__construct($data, ArrayObject::ARRAY_AS_PROPS); }
    public function toArray() { return $this->getArrayCopy(); }
}

class ContactTagTestRows extends ArrayObject
{
    public function toArray() { return array_map(static fn($row) => $row->toArray(), $this->getArrayCopy()); }
}

class Zend_Db_Table_Abstract
{
    public const NAME = 'name';
    public const COLS = 'cols';
    public static $adapter;
    public static array $rows = [];
    public static array $selects = [];
    public static array $writes = [];

    public function __construct() { if (method_exists($this, 'init')) $this->init(); }
    public function getAdapter() { return self::$adapter; }
    public function select() { return new ContactTagTestSelect($this->getAdapter()); }
    public function info($key = null)
    {
        if ($key === self::NAME || $key === 'name') return $this->_name;
        if ($key === self::COLS || $key === 'cols') {
            $columns = [];
            foreach (self::$rows[$this->_name] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
            return array_values(array_unique($columns));
        }
        return [];
    }
    public function fetchRow($select) { return $this->fetchAll($select)[0] ?? null; }
    public function fetchAll($select)
    {
        if (is_array($select)) {
            $conditions = $select;
            $select = $this->select();
            foreach ($conditions as $condition) $select->where($condition);
        }
        if (!$select->getPart(Zend_Db_Select::FROM)) $select->from($this->_name);
        self::$selects[] = $select;
        $from = $select->getPart(Zend_Db_Select::FROM);
        $rows = [];

        foreach (self::$rows[$this->_name] ?? [] as $row) {
            foreach ($select->getPart(Zend_Db_Select::WHERE) as $condition) {
                if (!contactTagWhereMatches($condition, $row)) continue 2;
            }

            if ($this->_name === 'tagentity' && isset($from['tag'])) {
                $tag = self::$rows['tag'][$row['tagid']] ?? null;
                $validTag = $tag
                    && (int)$tag['clientid'] === (int)$row['clientid']
                    && $tag['module'] === $row['module']
                    && $tag['controller'] === $row['controller']
                    && (int)$tag['deleted'] === 0;

                if (!$validTag && $from['tag']['joinType'] === Zend_Db_Select::INNER_JOIN) continue;
                $row['tag'] = $validTag ? $tag['title'] : null;
            }

            $rows[] = new ContactTagTestRow($row);
        }

        $limit = $select->getPart(Zend_Db_Select::LIMIT_COUNT);
        if ($limit) $rows = array_slice($rows, 0, $limit);

        return new ContactTagTestRows($rows);
    }
    public function insert(array $data)
    {
        $id = ++ContactTagTestAdapter::$lastId;
        $data += ['id' => $id, 'deleted' => 0];
        self::$rows[$this->_name][$id] = $data;
        self::$writes[] = [$this->_name, $data];
        return 1;
    }
    public function update(array $data, $where)
    {
        $conditions = is_array($where) ? $where : [$where];
        $updated = 0;

        foreach (self::$rows[$this->_name] ?? [] as $id => $row) {
            foreach ($conditions as $condition) {
                if (!contactTagWhereMatches($condition, $row)) continue 2;
            }

            self::$rows[$this->_name][$id] = array_replace($row, $data);
            self::$writes[] = [$this->_name, ['id' => $id] + $data];
            $updated++;
        }

        return $updated;
    }
}

require_once $root . '/application/modules/contacts/controllers/TagController.php';
require_once $root . '/application/modules/contacts/controllers/ContactController.php';
require_once $root . '/application/controllers/helpers/Options.php';

class ContactTagTestView extends Zend_View
{
    public function translate($message) { return $message; }
}

class ContactTagTestController extends Contacts_TagController
{
    public function init() { $this->view = new ContactTagTestView(); }

    public function validateAssignment(int $id, array $values, array $row): ?string
    {
        return $this->validateMultiUpdate($id, $values, $row, []);
    }
}

class ContactBulkTagTestController extends Contacts_ContactController
{
	public function init()
	{
		$this->_user = Zend_Registry::get('User');
		$this->view = new ContactTagTestView();
	}
}

class ContactTagTestListQuery extends DEEC_List_Query
{
    public function build(array $params, array $options, array $config): Zend_Db_Select
    {
        $select = new ContactTagTestSelect(Zend_Db_Table_Abstract::$adapter);
        $select->from([$config['alias'] => 'contact']);
        $this->applyFilters($select, $params, $options, $config);
        $this->applyGrouping($select, $config);
        return $select;
    }
}

Zend_Db_Table_Abstract::$adapter = new ContactTagTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/contacts/controllers', 'contacts');
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Options());
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;

function contactTagFixtures(): void
{
    ContactTagTestAdapter::$lastId = 100;
    Zend_Db_Table_Abstract::$writes = [];
    Zend_Db_Table_Abstract::$selects = [];
    Zend_Db_Table_Abstract::$rows = [
        'permission' => [1 => ['id' => 1, 'userid' => 42, 'clientid' => 7, 'deleted' => 0, 'contacts' => '{"contact":["add","edit","view","delete"]}']],
        'contact' => [
            11 => ['id' => 11, 'clientid' => 7, 'deleted' => 0, 'catid' => 3, 'name1' => 'Own Contact'],
            12 => ['id' => 12, 'clientid' => 7, 'deleted' => 0, 'catid' => 4, 'name1' => 'Second Contact'],
            13 => ['id' => 13, 'clientid' => 8, 'deleted' => 0, 'catid' => 3, 'name1' => 'Foreign Contact'],
            14 => ['id' => 14, 'clientid' => 7, 'deleted' => 1, 'catid' => 3, 'name1' => 'Deleted Contact'],
            70 => ['id' => 70, 'clientid' => 70, 'deleted' => 0, 'catid' => 5, 'name1' => 'Shared Parent Contact'],
        ],
        'tag' => [
            21 => ['id' => 21, 'shopid' => 0, 'clientid' => 7, 'module' => 'contacts', 'controller' => 'contact', 'deleted' => 0, 'title' => 'Gilventa'],
            22 => ['id' => 22, 'shopid' => 0, 'clientid' => 8, 'module' => 'contacts', 'controller' => 'contact', 'deleted' => 0, 'title' => 'Foreign Tag'],
            23 => ['id' => 23, 'shopid' => 0, 'clientid' => 7, 'module' => 'items', 'controller' => 'item', 'deleted' => 0, 'title' => 'Item Tag'],
            24 => ['id' => 24, 'shopid' => 0, 'clientid' => 7, 'module' => 'contacts', 'controller' => 'contact', 'deleted' => 1, 'title' => 'Dormant'],
            25 => ['id' => 25, 'shopid' => 0, 'clientid' => 7, 'module' => 'contacts', 'controller' => 'contact', 'deleted' => 0, 'title' => '<b>VIP</b>'],
            71 => ['id' => 71, 'shopid' => 0, 'clientid' => 70, 'module' => 'contacts', 'controller' => 'contact', 'deleted' => 0, 'title' => 'Parent Tag'],
        ],
        'tagentity' => [
            31 => ['id' => 31, 'tagid' => 21, 'entityid' => 11, 'module' => 'contacts', 'controller' => 'contact', 'ordering' => 1, 'clientid' => 7, 'deleted' => 0],
            32 => ['id' => 32, 'tagid' => 22, 'entityid' => 11, 'module' => 'contacts', 'controller' => 'contact', 'ordering' => 2, 'clientid' => 8, 'deleted' => 0],
            33 => ['id' => 33, 'tagid' => 25, 'entityid' => 11, 'module' => 'contacts', 'controller' => 'contact', 'ordering' => 2, 'clientid' => 7, 'deleted' => 0],
            34 => ['id' => 34, 'tagid' => 24, 'entityid' => 12, 'module' => 'contacts', 'controller' => 'contact', 'ordering' => 1, 'clientid' => 7, 'deleted' => 1],
            72 => ['id' => 72, 'tagid' => 71, 'entityid' => 70, 'module' => 'contacts', 'controller' => 'contact', 'ordering' => 1, 'clientid' => 70, 'deleted' => 1],
        ],
    ];
}

function requestContactTag(string $action, array $post, int $id = 0, int $parentId = 0): array
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $post;
    $_GET = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('contacts')->setControllerName('tag')->setActionName($action)->setParam('id', $id)->setParam('parentid', $parentId)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);

    ob_start();
    try {
        $controller = new ContactTagTestController($request, $response);
        $controller->{$action . 'Action'}();
    } finally {
        $output = ob_get_clean();
    }

    return [$response->getHttpResponseCode(), $response->getBody() . $output];
}

function requestBulkContactTags(array $ids, int $tagId, string $operation): array
{
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = ['ids' => $ids, 'tagid' => $tagId, 'operation' => $operation];
	$_GET = [];
	$request = new Zend_Controller_Request_Http();
	$request->setModuleName('contacts')->setControllerName('contact')->setActionName('tags')->setPost($_POST);
	$response = new Zend_Controller_Response_Http();
	Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);

	ob_start();
	try {
		$controller = new ContactBulkTagTestController($request, $response);
		$controller->tagsAction();
	} finally {
		$output = ob_get_clean();
	}

	return [$response->getHttpResponseCode(), json_decode($response->getBody() . $output, true)];
}

function addBlankContactTag(int $contactId): int
{
    requestContactTag('add', ['parent_module' => 'contacts', 'parent_controller' => 'contact'], 0, $contactId);
    return ContactTagTestAdapter::$lastId;
}

contactTagFixtures();
[$status, $response] = requestBulkContactTags([11, 12], 21, 'add');
check($status === 200 && $response['ok'] === true, 'Bulk contact tag assignment failed');
check($response['changed'] === 1 && $response['unchanged'] === 1, 'Bulk assignment counts are incorrect');
check($response['operation'] === 'add' && $response['message'] !== '', 'Bulk assignment response lost its operation/message contract');
$gilventaAssignments = array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static function(array $row): bool {
	return (int)$row['tagid'] === 21 && (int)$row['deleted'] === 0;
});
check(count($gilventaAssignments) === 2, 'One tag was not assigned to multiple contacts');
check(Zend_Db_Table_Abstract::$rows['contact'][11]['catid'] === 3 && Zend_Db_Table_Abstract::$rows['contact'][12]['catid'] === 4, 'Bulk assignment changed contact categories');
check((int)Zend_Db_Table_Abstract::$rows['tagentity'][33]['deleted'] === 0, 'Bulk assignment changed another tag');

$assignmentCount = count(Zend_Db_Table_Abstract::$rows['tagentity']);
[, $response] = requestBulkContactTags([11, 12], 21, 'add');
check($response['ok'] === true && $response['changed'] === 0 && $response['unchanged'] === 2, 'Repeated bulk assignment is not idempotent');
check(count(Zend_Db_Table_Abstract::$rows['tagentity']) === $assignmentCount, 'Repeated bulk assignment created duplicates');

[, $response] = requestBulkContactTags([11, 12], 21, 'remove');
check($response['ok'] === true && $response['changed'] === 2 && $response['unchanged'] === 0, 'Bulk tag removal failed');
check($response['operation'] === 'remove' && is_int($response['changed']) && is_int($response['unchanged']), 'Bulk removal response lost operation or integer counters');
check(!array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static function(array $row): bool {
	return (int)$row['tagid'] === 21 && (int)$row['deleted'] === 0;
}), 'Bulk tag removal left active assignments');
check((int)Zend_Db_Table_Abstract::$rows['tagentity'][33]['deleted'] === 0, 'Bulk removal changed another tag');
check(Zend_Db_Table_Abstract::$rows['contact'][11]['catid'] === 3 && Zend_Db_Table_Abstract::$rows['contact'][12]['catid'] === 4, 'Bulk removal changed contact categories');

[, $response] = requestBulkContactTags([11, 12], 21, 'remove');
check($response['ok'] === true && $response['changed'] === 0 && $response['unchanged'] === 2, 'Repeated bulk removal is not idempotent');

foreach ([22, 23, 24, 999] as $invalidTagId) {
	contactTagFixtures();
	$writes = count(Zend_Db_Table_Abstract::$writes);
	[, $response] = requestBulkContactTags([11, 12], $invalidTagId, 'add');
	check($response['ok'] === false && $response['message'] === 'MESSAGES_TAG_INVALID', 'Invalid or foreign bulk tag was accepted: ' . $invalidTagId);
	check(count(Zend_Db_Table_Abstract::$writes) === $writes, 'Invalid bulk tag request wrote data: ' . $invalidTagId);
}

foreach ([13, 14] as $invalidContactId) {
	contactTagFixtures();
	$writes = count(Zend_Db_Table_Abstract::$writes);
	[, $response] = requestBulkContactTags([12, $invalidContactId], 21, 'add');
	check($response['ok'] === false && $response['message'] === 'MESSAGES_CONTACT_NOT_FOUND', 'Invalid bulk contact was accepted: ' . $invalidContactId);
	check(count(Zend_Db_Table_Abstract::$writes) === $writes, 'Mixed bulk selection caused a partial write: ' . $invalidContactId);
	check(!array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static function(array $row): bool {
		return (int)$row['entityid'] === 12 && (int)$row['tagid'] === 21 && (int)$row['deleted'] === 0;
	}), 'Mixed bulk selection assigned the tag to an allowed contact');
}

contactTagFixtures();
Zend_Registry::set('Client', ['id' => 70, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
[, $response] = requestBulkContactTags([70], 71, 'add');
check($response['ok'] === true && $response['changed'] === 1, 'Shared parent contact rejected a bulk tag');
$parentAssignments = array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static function(array $row): bool {
	return (int)$row['entityid'] === 70 && (int)$row['tagid'] === 71 && (int)$row['deleted'] === 0 && (int)$row['clientid'] === 70;
});
check(count($parentAssignments) === 1, 'Shared parent bulk tag used the wrong tenant');
[, $response] = requestBulkContactTags([70], 71, 'remove');
check($response['ok'] === true && $response['changed'] === 1, 'Shared parent bulk tag could not be removed');

contactTagFixtures();
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
[, $response] = requestBulkContactTags(array_fill(0, 101, 11), 21, 'add');
check($response['ok'] === false && $response['message'] === 'MESSAGES_TAG_SELECTION_INVALID', 'Oversized contact selection was accepted');
$writes = count(Zend_Db_Table_Abstract::$writes);
[, $response] = requestBulkContactTags([11, 'invalid'], 21, 'add');
check($response['ok'] === false && $response['message'] === 'MESSAGES_TAG_SELECTION_INVALID', 'Malformed contact selection was accepted');
check(count(Zend_Db_Table_Abstract::$writes) === $writes, 'Malformed contact selection wrote data');
check((new DEEC_Permission([]))->getPermissionForAction('tags') === 'edit', 'Bulk tag route is not protected by edit permission');
$toolbar = new Contacts_Form_Toolbar();
check($toolbar->getElement('tags') !== null && $toolbar->getElement('bulk-tagid') !== null, 'Bulk tag toolbar controls are missing');
$contactsJs = file_get_contents($root . '/js/contacts.js');
check(str_contains($contactsJs, "actionConfig.tags = { selection: 'multiple' }") && str_contains($contactsJs, "ids: ids"), 'Bulk tag toolbar action does not use the selected contacts');

contactTagFixtures();
$rowId = addBlankContactTag(12);
[$status, $body] = requestContactTag('edit', ['tag' => 'New Tag'], $rowId);
$response = json_decode($body, true);
check($status === 200 && ($response['ok'] ?? false) === true, 'New contact tag was not saved: ' . $body);
$newTagId = (int)$response['values']['tagid'];
check(Zend_Db_Table_Abstract::$rows['tag'][$newTagId]['title'] === 'New Tag', 'New tag definition was not created');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === $newTagId, 'New tag was not assigned to the contact');
check(Zend_Db_Table_Abstract::$rows['contact'][12]['catid'] === 4, 'Tag assignment changed the contact category');

contactTagFixtures();
$tagCount = count(Zend_Db_Table_Abstract::$rows['tag']);
$rowId = addBlankContactTag(12);
requestContactTag('edit', ['tag' => 'Gilventa'], $rowId);
check(count(Zend_Db_Table_Abstract::$rows['tag']) === $tagCount, 'Existing tag was created again');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === 21, 'Existing tag was not reused');
check(count((new Contacts_Model_DbTable_Tag())->getByParentId(12, 'contacts', 'contact')) === 1, 'Saved contact tag could not be loaded again');

$secondTagRowId = addBlankContactTag(12);
requestContactTag('edit', ['tag' => 'Second Tag'], $secondTagRowId);
check(count((new Contacts_Model_DbTable_Tag())->getByParentId(12, 'contacts', 'contact')) === 2, 'Multiple tags on one contact were not retained');

$firstContactTagCount = count((new Contacts_Model_DbTable_Tag())->getByParentId(11, 'contacts', 'contact'));
check($firstContactTagCount === 2, 'Fixture tags for the first contact were not loaded');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === 21, 'One tag could not be assigned to multiple contacts');

contactTagFixtures();
$duplicateRowId = addBlankContactTag(11);
[$status, $body] = requestContactTag('edit', ['tag' => 'Gilventa'], $duplicateRowId);
$response = json_decode($body, true);
check($status === 200 && $response['ok'] === false && $response['message'] === 'MESSAGES_TAG_ALREADY_ASSIGNED', 'Duplicate contact tag assignment was accepted');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$duplicateRowId]['tagid'] === 0, 'Rejected duplicate changed the blank assignment');

contactTagFixtures();
foreach ([[13, 'foreign'], [14, 'deleted']] as [$contactId, $label]) {
    $before = count(Zend_Db_Table_Abstract::$rows['tagentity']);
    [$status, $body] = requestContactTag('add', ['parent_module' => 'contacts', 'parent_controller' => 'contact'], 0, $contactId);
    $response = json_decode($body, true);
    check($status === 200 && $response['ok'] === false && $response['message'] === 'MESSAGES_CONTACT_NOT_FOUND', ucfirst($label) . ' contact accepted a tag row');
    check(count(Zend_Db_Table_Abstract::$rows['tagentity']) === $before, ucfirst($label) . ' contact tag request wrote data');
}
[$status, $body] = requestContactTag('add', ['parent_module' => 'items', 'parent_controller' => 'item'], 0, 11);
check(json_decode($body, true)['message'] === 'MESSAGES_TAG_CONTEXT_INVALID', 'Foreign parent context was accepted');

$validationRequest = new Zend_Controller_Request_Http();
$validationRequest->setModuleName('contacts')->setControllerName('tag')->setActionName('edit');
$controller = new ContactTagTestController($validationRequest, new Zend_Controller_Response_Http());
$ownRow = Zend_Db_Table_Abstract::$rows['tagentity'][31];
check($controller->validateAssignment(31, ['tagid' => 22], $ownRow) === 'MESSAGES_TAG_INVALID', 'Foreign tag ID was accepted');
check($controller->validateAssignment(31, ['tagid' => 23], $ownRow) === 'MESSAGES_TAG_INVALID', 'Tag from another context was accepted');

contactTagFixtures();
$rowId = addBlankContactTag(12);
requestContactTag('edit', ['tag' => 'Dormant'], $rowId);
check(Zend_Db_Table_Abstract::$rows['tag'][24]['deleted'] === 0, 'Soft-deleted tag definition was not restored');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === 24, 'Restored tag was not assigned');
check(Zend_Db_Table_Abstract::$rows['tagentity'][34]['deleted'] === 1, 'Historical deleted assignment was modified');

contactTagFixtures();
$rowId = addBlankContactTag(12);
$tagCount = count(Zend_Db_Table_Abstract::$rows['tag']);
requestContactTag('edit', ['tag' => ' <b> </b> '], $rowId);
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === 0, 'Empty tag input was not cleared');
check(count(Zend_Db_Table_Abstract::$rows['tag']) === $tagCount, 'Empty tag input created a definition');

contactTagFixtures();
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 70, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
$rowId = addBlankContactTag(70);
requestContactTag('edit', ['tag' => 'Parent Tag'], $rowId);
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['clientid'] === 70, 'Shared parent tag used the child tenant');
check(Zend_Db_Table_Abstract::$rows['tagentity'][$rowId]['tagid'] === 71, 'Shared parent tag was not reused');

contactTagFixtures();
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$beforeSelects = count(Zend_Db_Table_Abstract::$selects);
$tagEntities = (new Application_Model_DbTable_Tagentity())->getTagEntitiesForParents('contacts', 'contact', [11, 12]);
check(count(Zend_Db_Table_Abstract::$selects) === $beforeSelects + 1, 'Contact list tags were not loaded in one query');
check(count($tagEntities[11]) === 2 && !isset($tagEntities[12]), 'Batch tag loading mixed contacts or tenants');
$list = new Contacts_Model_List_Contacts(['options' => ['tagEntities' => $tagEntities]]);
$html = $list->renderTags((object)['id' => 11]);
check(str_contains($html, 'Gilventa') && str_contains($html, '&lt;b&gt;VIP&lt;/b&gt;'), 'Contact list tag badges are missing or unescaped');
check(!str_contains($html, '<b>VIP</b>'), 'Contact list rendered tag HTML without escaping');

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('index');
Zend_Controller_Front::getInstance()->setRequest($request);
$tagFilterForm = new DEEC_Form();
$tagFilterForm->addElement(['name' => 'tagid', 'type' => 'select', 'source' => 'tag']);
$toolbarOptions = (new Application_Controller_Action_Helper_Options())->getOptions($tagFilterForm);
check(($toolbarOptions['tagid'][21] ?? null) === 'Gilventa' && !isset($toolbarOptions['tagid'][22], $toolbarOptions['tagid'][23], $toolbarOptions['tagid'][24]), 'Contact toolbar tag options lost tenant or context isolation');
$params = ['keyword' => 'Own', 'catid' => '3', 'tagid' => 21, 'country' => '0'];
$options = ['catid' => [3 => ['childs' => [4]], 4 => ['childs' => []]]];
$select = (new ContactTagTestListQuery())->build($params, $options, Contacts_Model_Entity_Contact::listConfig());
$from = $select->getPart(Zend_Db_Select::FROM);
$where = implode(' ', $select->getPart(Zend_Db_Select::WHERE));
check(isset($from['tag_filter'], $from['tag_filter_definition']), 'Tag filter did not join assignment and definition');
check(str_contains($from['tag_filter']['joinCondition'], "tag_filter.module = 'contacts'") && str_contains($from['tag_filter']['joinCondition'], "tag_filter.controller = 'contact'"), 'Tag filter lost contact context');
check(str_contains($from['tag_filter_definition']['joinCondition'], 'tag_filter_definition.clientid = tag_filter.clientid') && str_contains($from['tag_filter_definition']['joinCondition'], 'tag_filter_definition.deleted = 0'), 'Tag filter accepts foreign or deleted definitions');
check(str_contains($where, 'c.catid IN') && str_contains($where, 'LIKE') && str_contains($where, 'c.deleted'), 'Category, search or deletion filter was lost');
check(str_contains($where, '3') && str_contains($where, '4'), 'Category filter lost configured subcategories');
check($select->getPart(Zend_Db_Select::GROUP) === ['c.id'], 'Tag filter can return duplicate contact rows');

echo 'PASS: ' . $checks . " contact tag security, display and filter cases\n";
