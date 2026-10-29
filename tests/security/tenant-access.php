<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Run with: php tests/security/tenant-access.php (in-memory data, no external actions).
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/fixtures/tenant-access.php';
Zend_Session::$_unitTestEnabled = true;
Zend_Session::start();
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

$adapter = tenantAccessFixture();
$permission = new DEEC_Permission(Zend_Registry::get('User'));
foreach ([['contacts', 'contact', 10], ['items', 'item', 101], ['sales', 'quote', 301], ['sales', 'quotepos', 401]] as [$module, $controller, $id]) {
    check($permission->getObjectContext($id, $module, $controller) !== null, 'Authorized context denied: ' . $module . '/' . $controller);
}
foreach ([['contacts', 'contact', 20], ['contacts', 'contact', 30], ['items', 'item', 102], ['sales', 'quote', 302], ['sales', 'quotepos', 402]] as [$module, $controller, $id]) {
    check($permission->getObjectContext($id, $module, $controller) === null, 'Foreign or deleted context allowed: ' . $module . '/' . $controller);
}
foreach ([0, -1, '10extra', [10]] as $id) check($permission->getObjectContext($id, 'contacts', 'contact') === null, 'Malformed object ID allowed');
check($permission->getObjectContext(11, 'contacts', 'contactperson') !== null, 'Shared contact person denied');
check($permission->getObjectContext(22, 'contacts', 'contactperson') === null, 'Child bypassed foreign parent scope');
tenantAccessFixture('denied');
check((new DEEC_Permission(Zend_Registry::get('User')))->getObjectContext(10, 'contacts', 'contact') === null, 'Revoked permission retained object access');
tenantAccessFixture('full', 8);
check((new DEEC_Permission(Zend_Registry::get('User')))->getObjectContext(301, 'sales', 'quote') === null, 'Selected tenant retained previous financial document');
check((new DEEC_Permission(Zend_Registry::get('User')))->getObjectContext(10, 'contacts', 'contact') !== null, 'Switch lost authorized shared contact');

// Exercise legacy wrappers rather than only the inherited scoped API.
$adapter = tenantAccessFixture();
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$models = [
    'Application' => ['Category', 'Comment', 'Media'],
    'Admin' => ['Category', 'Config', 'Country', 'Currency', 'Deliverytime', 'Exchangerate', 'Footer', 'Language', 'Manufacturer', 'Page', 'Paymentmethod', 'Shippingmethod', 'Shop', 'Tag', 'Tagentity', 'Taxrate', 'Template', 'Textblock', 'Uom', 'User'],
    'Contacts' => ['Downloadset', 'Emailmessage', 'Emailtracking'],
    'Items' => ['Item', 'Inventory', 'Itemlist', 'Pricerule'],
    'Processes' => ['Process'], 'Tasks' => ['Task'],
];
foreach ($models as $module => $names) {
    foreach ($names as $name) {
        $class = $module . '_Model_DbTable_' . $name;
        $table = strtolower($name);
        $adapter->rows[$table] = [
            ['id' => 1, 'clientid' => 7, 'deleted' => 0, 'notes' => 'own'],
            ['id' => 2, 'clientid' => 9, 'deleted' => 0, 'notes' => 'foreign'],
            ['id' => 3, 'clientid' => 7, 'deleted' => 1, 'notes' => 'deleted'],
        ];
        $db = new $class();
        $get = 'get' . $name;
        if (method_exists($db, $get)) {
            check(($db->$get(1)['id'] ?? 0) === 1, 'Own legacy read denied: ' . $class);
            foreach ($name === 'Config' ? [2] : [2, 3] as $id) {
                try { $row = $db->$get($id); } catch (Exception $exception) { $row = null; }
                check(!$row, 'Legacy read leaked foreign/deleted row: ' . $class);
            }
        }
        $update = 'update' . $name;
        if (method_exists($db, $update) && !in_array($name, ['Media', 'Textblock'], true)) {
            $db->$update(2, ['notes' => 'tampered']);
            check($adapter->rows[$table][1]['notes'] === 'foreign', 'Legacy foreign write: ' . $class);
            $db->$update(1, ['notes' => 'saved']);
            check($adapter->rows[$table][0]['notes'] === 'saved', 'Own legacy write denied: ' . $class);
        }
    }
}
foreach (['Items' => ['Itematrset', 'Itemoptset'], 'Sales' => ['Quoteposset', 'Salesorderposset', 'Invoiceposset', 'Deliveryorderposset', 'Creditnoteposset', 'Reminderposset'], 'Purchases' => ['Quoterequestposset', 'Purchaseorderposset'], 'Processes' => ['Processposset'], 'Tasks' => ['Taskposset'], 'Campaigns' => ['Campaignposset']] as $module => $names) {
    foreach ($names as $name) {
        $table = strtolower($name);
        $adapter->rows[$table] = [['id' => 1, 'clientid' => 7, 'deleted' => 0, 'ordering' => 1], ['id' => 2, 'clientid' => 9, 'deleted' => 0, 'ordering' => 1]];
        $class = $module . '_Model_DbTable_' . $name;
        $db = new $class();
        $db->sortPositionSets([1 => 4, 2 => 5]);
        check($adapter->rows[$table][0]['ordering'] === 4 && $adapter->rows[$table][1]['ordering'] === 1, 'Bulk ordering crossed tenant: ' . $class);
        $db->deletePositionSets([1, 2]);
        check($adapter->rows[$table][0]['deleted'] === 1 && $adapter->rows[$table][1]['deleted'] === 0, 'Bulk deletion crossed tenant: ' . $class);
    }
}

$adapter = tenantAccessFixture();
$itemDb = (new Items_Model_DbTable_Item())->setClientId(1);
$itemDb->updateById(101, ['id' => 102, 'clientid' => 9, 'parentid' => 102, 'createdby' => 999, 'sku' => 'saved']);
check($adapter->rows['item'][0]['id'] === 101 && $adapter->rows['item'][0]['clientid'] === 1 && $adapter->rows['item'][0]['parentid'] === 0 && !isset($adapter->rows['item'][0]['createdby']), 'Hidden identifiers changed entity ownership or variant parent');
check($adapter->rows['item'][0]['sku'] === 'saved', 'Valid field was not saved');
$id = $itemDb->create(['id' => 102, 'clientid' => 9, 'sku' => 'new']);
check($itemDb->getById($id)['clientid'] === 1 && $id !== 102, 'Create accepted browser ID or client');

function contactpersonRequest(string $action, int $id, array $post = [], string $mode = 'full'): array
{
    $adapter = tenantAccessFixture($mode);
    $_POST = $_GET = [];
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('contacts')->setControllerName('contactperson')->setActionName($action)->setParam('id', $id)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    ob_start();
    tenantAccessDispatch($request, $response);
    ob_end_clean();
    return [$adapter, $response, json_decode($response->getBody(), true)];
}

foreach (['get', 'edit', 'copy'] as $action) {
    foreach ([21, 22] as $id) {
        [$db, $response, $json] = contactpersonRequest($action, $id, $action === 'edit' ? ['firstname' => 'tampered'] : []);
        check(!$json['ok'] && !str_contains($response->getBody(), 'secret'), 'IDOR response exposed child: ' . $action);
        check(count($db->rows['contactperson']) === 3 && $db->rows['contactperson'][1]['firstname'] === 'Foreign secret' && $db->rows['contactperson'][2]['firstname'] === 'Invalid parent secret', 'IDOR request wrote data: ' . $action);
    }
}
[$db, $response, $json] = contactpersonRequest('get', 11);
check($json['ok'] && $json['item']['firstname'] === 'Allowed', 'Authorized JSON read failed');
[$db, $response, $json] = contactpersonRequest('edit', 11, ['firstname' => 'Saved', 'clientid' => 9, 'parentid' => 20, 'module' => 'sales', 'controller' => 'quote']);
check($json['ok'] && $db->rows['contactperson'][0]['firstname'] === 'Saved', 'Authorized AJAX save failed');
check($db->rows['contactperson'][0]['clientid'] === 1 && $db->rows['contactperson'][0]['parentid'] === 10 && $db->rows['contactperson'][0]['module'] === 'contacts', 'AJAX reassigned child via hidden fields');
[$db, $response, $json] = contactpersonRequest('copy', 11);
check($json['ok'] && count($db->rows['contactperson']) === 4 && $db->rows['contactperson'][3]['clientid'] === 1, 'Authorized shared copy failed');
foreach (['readonly', 'denied', 'guest'] as $mode) {
    [$db, $response, $json] = contactpersonRequest('edit', 11, ['firstname' => 'tampered'], $mode);
    check($response->getHttpResponseCode() === 403 && !$json['ok'] && $db->rows['contactperson'][0]['firstname'] === 'Allowed', 'ACL bypass through AJAX: ' . $mode);
}

// The generic trash URL must check the permission of the requested target.
class TenantTrashRequest extends Zend_Controller_Request_Http
{
    public array $payload = [];
    public function getRawBody() { return json_encode($this->payload); }
}
foreach ([['readonly', 11, false], ['full', 21, false], ['full', 22, false], ['full', 11, true], ['guest', 11, false]] as [$mode, $id, $deleted]) {
    $adapter = tenantAccessFixture($mode);
    $_POST = $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request = new TenantTrashRequest();
    $request->payload = ['id' => [$id], 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 9];
    $request->setModuleName('default')->setControllerName('trash')->setActionName('add');
    $response = new Zend_Controller_Response_Http();
    ob_start();
    tenantAccessDispatch($request, $response);
    ob_end_clean();
    $rows = array_column($adapter->rows['contactperson'], null, 'id');
    check((bool)$rows[$id]['deleted'] === $deleted, 'Trash target access failed: ' . $mode . '/' . $id);
}

$adapter = tenantAccessFixture();
$stockClient = Zend_Registry::get('Client');
$stockClient['id'] = 1;
Zend_Registry::set('Client', $stockClient);
$stock = new Items_Service_Stock();
$ledger = ['itemid' => 101, 'warehouseid' => 201, 'quantity' => 1, 'type' => 'inflow', 'reason' => 'correction'];
check($stock->prepareCreateData($ledger)['itemid'] === 101, 'Shared item/warehouse ledger denied');
foreach ([['itemid' => 102], ['warehouseid' => 202]] as $foreign) {
    try { $stock->prepareCreateData(array_replace($ledger, $foreign)); $denied = false; } catch (Exception $exception) { $denied = true; }
    check($denied, 'Stock accepted foreign item or warehouse');
}

// Contact history must apply each document permission and retain the server context.
$adapter = tenantAccessFixture();
$before = Zend_Registry::get('Client');
$history = (new Contacts_Model_Get())->history(100);
check(count($history['quotes']) === 1 && $history['quotes'][0]['id'] === 301, 'Contact history crossed business tenant');
check(Zend_Registry::get('Client') === $before, 'Contact history changed the global client context');
$adapter->rows['permission'][0]['sales'] = '{}';
check(count((new Contacts_Model_Get())->history(100)['quotes']) === 0, 'Contact history bypassed document read permission');

// Inspect the actual list builders: business rows and shared joins have distinct owners.
$method = new ReflectionMethod(DEEC_List_Query::class, 'buildBaseSelect');
foreach ([Sales_Model_Entity_Quote::class, Purchases_Model_Entity_Purchaseorder::class, Processes_Model_Entity_Process::class, Contacts_Model_Entity_Contact::class] as $entity) {
    $config = $entity::listConfig();
    $select = $method->invoke(new DEEC_List_Query(), new $config['tableClass'](), $config, false);
    foreach ($config['joins'] as $join) {
        if (($join['clientModule'] ?? '') !== 'contacts') continue;
        $condition = $select->getPart(Zend_Db_Select::FROM)[$join['alias']]['joinCondition'];
        check(str_contains($condition, $join['alias'] . '.clientid = 1'), 'List join exposed foreign contact: ' . $entity);
    }
}

// Operational position routes also bind children to the authorized parent.
$adapter = tenantAccessFixture();
$adapter->rows['itematr'] = [['id' => 501, 'parentid' => 101, 'clientid' => 1, 'deleted' => 0]];
foreach (['items' => ['item', 'atr', 101, 501, 1], 'processes' => ['process', 'pos', 601, 701, 7], 'tasks' => ['task', 'pos', 601, 701, 7], 'campaigns' => ['campaign', 'pos', 601, 701, 7]] as $module => [$parent, $type, $parentId, $childId, $owner]) {
    if ($module !== 'items') {
        $adapter->rows[$parent] = [['id' => $parentId, 'clientid' => $owner, 'deleted' => 0, 'state' => 100]];
        $adapter->rows[$parent . $type] = [['id' => $childId, 'parentid' => $parentId, 'clientid' => $owner, 'deleted' => 0]];
        if ($module === 'processes') $adapter->rows['permission'][0]['processes'] = '{"process":["view","edit"]}';
    }
    $context = Zend_Registry::get('Client');
    $context['id'] = $owner;
    Zend_Registry::set('Client', $context);
    foreach ([[$parentId, $childId, true], [$parentId + 1, $childId, false], [$parentId, $childId + 1, false]] as [$requestedParent, $requestedChild, $allowed]) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $_GET = [];
        $request = new Zend_Controller_Request_Http();
        $request->setModuleName($module)->setControllerName('position')->setActionName('copy')
            ->setParams(['parent' => $parent, 'type' => $type, 'parentid' => $requestedParent, 'id' => $requestedChild])->setPost([]);
        $response = new Zend_Controller_Response_Http();
        $guard = new Application_Plugin_State();
        $guard->setResponse($response);
        $guard->preDispatch($request);
        check(($response->getHttpResponseCode() === 200) === $allowed, 'Operational position bypass or regression: ' . $module);
    }
}

// Profile locks belong to the actor even if a different user ID is posted.
$adapter = tenantAccessFixture();
$adapter->rows['user'] = [['id' => 42, 'clientid' => 7, 'activated' => 1, 'deleted' => 0, 'admin' => 0, 'locked' => 0], ['id' => 99, 'clientid' => 9, 'locked' => 0]];
(new Users_Model_DbTable_User())->lock(99);
check($adapter->rows['user'][0]['locked'] === 42 && $adapter->rows['user'][1]['locked'] === 0, 'Profile lock modified another user');
(new Users_Model_DbTable_User())->unlock(99);
check($adapter->rows['user'][0]['locked'] === 0 && $adapter->rows['user'][1]['locked'] === 0, 'Profile unlock modified another user');
foreach ([9, [9], '9 OR 1=1'] as $clientId) {
    try { (new Admin_Model_DbTable_User())->updateById(42, ['clientid' => $clientId]); $denied = false; } catch (InvalidArgumentException $exception) { $denied = true; }
    check($denied && $adapter->rows['user'][0]['clientid'] === 7, 'Non-admin assigned a user to another client');
}
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'admin' => 1]);
$adapter->rows['user'][0]['admin'] = 1;
$adapter->rows['client'] = [['id' => 7, 'company' => 'Home', 'deleted' => 0], ['id' => 8, 'company' => 'Allowed', 'deleted' => 0], ['id' => 9, 'company' => 'Deleted', 'deleted' => 1]];
foreach ([9, [8], '8 OR 1=1'] as $clientId) {
    try { (new Admin_Model_DbTable_User())->updateById(42, ['clientid' => $clientId]); $denied = false; } catch (InvalidArgumentException $exception) { $denied = true; }
    check($denied, 'Admin assignment accepted malformed/deleted target');
}
(new Admin_Model_DbTable_User())->updateById(42, ['clientid' => 8]);
check($adapter->rows['user'][0]['clientid'] === 8, 'Authorized admin client assignment was lost');

foreach (['Ebay' => ['Account', 'Listing', 'Order'], 'Shops' => ['Account', 'Order']] as $module => $models) {
    foreach ($models as $name) {
        $adapter = tenantAccessFixture();
        $table = strtolower($module === 'Shops' ? 'shop' : 'ebay') . strtolower($name);
        $adapter->rows[$table] = [['id' => 1, 'itemid' => 101, 'clientid' => 7, 'deleted' => 0, 'locked' => 0], ['id' => 2, 'itemid' => 102, 'clientid' => 9, 'deleted' => 0, 'locked' => 0]];
        $class = $module . '_Model_DbTable_' . $name;
        $db = new $class();
        $db->lock(2);
        check($adapter->rows[$table][1]['locked'] === 0, 'Integration lock crossed tenant: ' . $class);
        $db->lock(1);
        check($adapter->rows[$table][0]['locked'] === 42, 'Integration lock lost own scope: ' . $class);
        $add = 'add' . ($module === 'Ebay' && $name === 'Order' ? 'Ebayuser' : $name);
        $id = $db->$add(['id' => 2, 'clientid' => 9]);
        check($id !== 2 && $db->getById($id)['clientid'] === 7, 'Integration create accepted browser owner or ID: ' . $class);
    }
}
// Checkout derives its owner from the selected site, including a logged-in ERP visitor.
$adapter = tenantAccessFixture();
$id = (new Shops_Model_DbTable_Order())->setClientId(1)->addOrder(['clientid' => 9, 'shopid' => 3]);
check($adapter->rows['shoporder'][0]['clientid'] === 1, 'Site order lost explicit server-owned tenant');

require_once dirname(__DIR__, 2) . '/application/controllers/helpers/Query.php';
foreach ([new DEEC_Query(), new Application_Controller_Action_Helper_Query()] as $query) {
    foreach ([['getQueryClient', 'clientid'], ['getQueryShopID', 'shopid'], ['getQueryAccountID', 'accountid']] as [$method, $field]) {
        $sql = $query->$method('', '7 OR 1=1');
        check(!str_contains($sql, 'OR') && str_contains($sql, $field . ' = 0'), 'Numeric scope filter accepted SQL: ' . $method);
    }
}

echo 'PASS: ' . $checks . " tenant, object, legacy model, AJAX, trash, stock, history and shared-list cases\n";
