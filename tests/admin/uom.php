<?php

// Run with: python3 tests/install/import-db.py (disposable MariaDB with the real installation).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1], $argv[2])) { echo "SKIP: run python3 tests/install/import-db.py\n"; exit; }
$work = realpath($argv[2]);
if (!$work || !str_starts_with($work, '/tmp/dewawi-install-db-') || $argv[1] !== $work . '/mysql.sock') {
    throw new RuntimeException('Only isolated test sockets are accepted');
}
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class);
    if (count($parts) < 3) return;
    $module = array_shift($parts);
    $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
    if (is_file($path)) require_once $path;
});
require_once $root . '/application/modules/admin/controllers/UomController.php';

class UomTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public bool $failDefault = false;
    public function update($table, array $bind, $where = '')
    {
        $affected = parent::update($table, $bind, $where);
        if ($this->failDefault && $table === 'uom' && $bind === ['default' => 0]) {
            throw new RuntimeException('Simulated failure after clearing the previous default');
        }
        return $affected;
    }
}

class UomTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public array $messages = [];
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { $this->messages[] = $message; return $this; }
}

class UomTestController extends Admin_UomController
{
    public ?Closure $beforeSave = null;
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->_flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $this->view = new Zend_View();
    }
    protected function beforeEdit(array $row)
    {
        if ($this->beforeSave) ($this->beforeSave)();
        return null;
    }
}

class UomTestPositionController extends DEEC_Controller_PositionAction
{
    public function init() {}
    public function newPosition(): array
    {
        return $this->getNewPositionData(['parentid' => 1, 'type' => 'pos', 'setid' => 0, 'masterid' => null], ['currency' => 'EUR']);
    }
}

$adapter = new UomTestAdapter(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'install_success', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 1]);
Zend_Registry::set('User', ['id' => 1, 'admin' => 1]);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers', 'admin' => $root . '/application/modules/admin/controllers']);
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();
$flash = new UomTestFlashMessenger();
Zend_Controller_Action_HelperBroker::addHelper($flash);
foreach (['Access', 'Options'] as $name) {
    require_once $root . '/application/controllers/helpers/' . $name . '.php';
    $class = 'Application_Controller_Action_Helper_' . $name;
    Zend_Controller_Action_HelperBroker::addHelper(new $class());
}
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function uomController(string $action, int $id, array $post = [], bool $ajax = true): UomTestController
{
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('admin')->setControllerName('uom')->setActionName($action)->setParam('id', $id)->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    return new UomTestController($request, $response);
}

function saveUom(int $id, array $post, bool $ajax = true, ?Closure $beforeSave = null): array
{
    $controller = uomController('edit', $id, $post, $ajax);
    $controller->beforeSave = $beforeSave;
    $controller->editAction();
    return $ajax ? json_decode($controller->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR) : [];
}

function verifyDefault(int $clientId, int $expectedId): void
{
    global $adapter;
    $ids = $adapter->fetchCol('SELECT id FROM uom WHERE clientid = ? AND deleted = 0 AND `default` = 1', [$clientId]);
    verify(array_map('intval', $ids) === [$expectedId], 'Expected exactly one active default for tenant ' . $clientId);
    verify((int)(new Application_Model_DbTable_Uom())->setClientId($clientId)->getDefault()['id'] === $expectedId, 'getDefault returned the wrong tenant UOM');
}

// Real installer seed and real new-tenant initialization, without assuming Stück has ID 1.
verifyDefault(1, 1);
$initializer = new Admin_Model_Service_ClientInitializer();
$createUoms = new ReflectionMethod($initializer, 'createDefaultUoms');
$createUoms->invoke($initializer, 7);
$tenantUoms = $adapter->fetchAll('SELECT * FROM uom WHERE clientid = 7 ORDER BY id');
verify(count($tenantUoms) === 5, 'Tenant initializer did not create all UOMs');
verify(array_column($tenantUoms, 'title') === ['Stück', 'Pack.', 'Std.', 'kg', 'm'], 'Tenant initializer changed the UOM titles');
verify(array_map('intval', array_column($tenantUoms, 'default')) === [1, 0, 0, 0, 0], 'Tenant initializer selected the wrong defaults');
$tenantDefault = (int)$tenantUoms[0]['id'];
verifyDefault(7, $tenantDefault);
verify((new Application_Model_DbTable_Uom())->setClientId(99)->getDefault() === null, 'Empty tenant inherited another tenant default');
$adapter->insert('uom', ['title' => 'Deleted default', 'clientid' => 99, 'deleted' => 1, 'default' => 1]);
$deletedDefault = (int)$adapter->lastInsertId();
verify((new Application_Model_DbTable_Uom())->setClientId(99)->getDefault() === null, 'Deleted UOM was returned as default');

// Real editAction, including the actual UOM form, AJAX partial filtering and transaction hooks.
$result = saveUom(4, ['default' => '1']);
verify($result['ok'] && (int)$result['values']['default'] === 1, 'AJAX default selection failed');
verifyDefault(1, 4);
verifyDefault(7, $tenantDefault);
$result = saveUom(4, ['default' => '0']);
verify($result['ok'] && (int)$result['values']['default'] === 1, 'AJAX deselection did not return the retained default');
verifyDefault(1, 4);
saveUom(4, ['title' => 'kg']);
verifyDefault(1, 4);
saveUom(4, ['id' => 999998, 'clientid' => 7, 'default' => 1]);
verify((new Admin_Model_DbTable_Uom())->getById(4) !== null, 'Posted ID moved the default target');
verifyDefault(1, 4);
saveUom(1, ['id' => 1, 'title' => 'Stück', 'default' => 1, 'clientid' => 7], false);
verifyDefault(1, 1);
verifyDefault(7, $tenantDefault);
saveUom(1, ['id' => 1, 'title' => 'Stück'], false);
verifyDefault(1, 1);
verify((int)$adapter->fetchOne('SELECT `default` FROM uom WHERE id = 4') === 0, 'Previous default was not cleared');

// The stored row, rather than a stale form snapshot, decides whether deselection is allowed.
saveUom(4, ['default' => 0], true, static function (): void { (new Admin_Model_DbTable_Uom())->setDefault(4); });
verifyDefault(1, 4);
saveUom(4, ['default' => 0], true, static function (): void { (new Admin_Model_DbTable_Uom())->setDefault(1); });
verifyDefault(1, 1);

$adapter->failDefault = true;
$result = saveUom(4, ['default' => 1, 'title' => 'Must roll back']);
$adapter->failDefault = false;
verify($result['ok'] === false, 'Simulated failure was reported as success');
verifyDefault(1, 1);
verify($adapter->fetchOne('SELECT title FROM uom WHERE id = 4') === 'kg', 'Failed default switch did not roll back other edits');

// Foreign and deleted rows cannot be selected through the controller.
foreach ([$tenantDefault, $deletedDefault, 999999] as $id) {
    $result = saveUom($id, ['default' => 1]);
    verify($result['ok'] === false, 'Inaccessible UOM was editable');
    verifyDefault(1, 1);
}

$controller = uomController('copy', 1);
$controller->copyAction();
$copy = json_decode($controller->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
verify($copy['ok'] && (int)(new Admin_Model_DbTable_Uom())->getById((int)$copy['id'])['default'] === 0, 'Copy inherited the default');
verifyDefault(1, 1);
$controller = uomController('delete', 1);
$controller->deleteAction();
verify((int)$adapter->fetchOne('SELECT deleted FROM uom WHERE id = 1') === 0, 'Default UOM was deleted');
verify(in_array('MESSAGES_UOM_CANNOT_BE_DELETED', $flash->messages, true), 'Default deletion had no translated rejection message');
$controller = uomController('delete', (int)$copy['id']);
$controller->deleteAction();
verify((int)$adapter->fetchOne('SELECT deleted FROM uom WHERE id = ?', [$copy['id']]) === 1, 'Non-default UOM could not be deleted');
verifyDefault(1, 1);

// An empty tenant receives a default when its first UOM is created via the existing add action.
Zend_Registry::set('Client', ['id' => 98]);
$controller = uomController('add', 0, [], false);
$controller->addAction();
verifyDefault(98, (int)$adapter->fetchOne('SELECT id FROM uom WHERE clientid = 98'));
Zend_Registry::set('Client', ['id' => 1]);

// Exercise the existing position method with real tenant-scoped UOM and item models.
$request = new Zend_Controller_Request_Http();
$position = new UomTestPositionController($request, new Zend_Controller_Response_Http());
$data = $position->newPosition();
verify($data['quantity'] === 1 && $data['uom'] === 'Stück', 'Free position did not inherit the default');
saveUom(4, ['default' => 1]);
verify($position->newPosition()['uom'] === 'kg', 'Free position ignored a changed default');
foreach ([2 => 'Pack.', 0 => 'kg', 999999 => 'kg', $tenantDefault => 'kg'] as $uomId => $expected) {
    $adapter->insert('item', ['clientid' => 1, 'catid' => 0, 'shopid' => 0, 'sku' => 'UOM-TEST', 'title' => 'Fixture', 'description' => '', 'price' => 1, 'currency' => 'EUR', 'uomid' => $uomId]);
    $request->setParam('itemid', (int)$adapter->lastInsertId());
    verify($position->newPosition()['uom'] === $expected, 'Article UOM did not override or retain the default correctly');
}
$adapter->insert('uom', ['title' => 'Deleted', 'clientid' => 1, 'deleted' => 1]);
$deletedId = (int)$adapter->lastInsertId();
$adapter->update('item', ['uomid' => $deletedId], ['id = ?' => $request->getParam('itemid')]);
verify($position->newPosition()['uom'] === 'kg', 'Deleted article UOM replaced the default');

$columns = array_column((new Admin_Model_List_Uoms())->getColumns(), null, 'name');
verify($columns['default']['type'] === 'checkbox' && $columns['default']['label'] === 'ADMIN_DEFAULT', 'UOM list has no default indicator');
foreach (['de_DE', 'en_US'] as $locale) {
    $messages = require $root . '/languages/' . $locale . '/admin/admin.php';
    verify(isset($messages['ADMIN_DEFAULT'], $messages['MESSAGES_UOM_CANNOT_BE_DELETED']), 'UOM default translations are incomplete');
}
echo 'PASS: ' . $checks . " UOM checks on isolated MariaDB (initialization, tenants, edit/AJAX, rollback, copy/delete and positions)\n";
