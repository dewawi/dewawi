<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/contacts/history-states.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
    }
});
require_once $root . '/application/models/DbTable/State.php';
require_once $root . '/application/modules/contacts/models/Get.php';
require_once $root . '/application/modules/contacts/models/List/History.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

class HistoryTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    protected function _connect() { throw new RuntimeException('Database access is forbidden in this test'); }
    public function quote($value, $type = null) {
        if (!is_int($value)) throw new RuntimeException('Only integer fixture filters are expected');
        return (string)$value;
    }
}

// Replace document reads while keeping the real history loader and status renderer.
class Application_Model_DbTable_Permission
{
    public static array $permissions = ['contacts' => '{"contact":["view"]}', 'sales' => '{"quote":["view"]}'];
    public function getPermissions() { return self::$permissions; }
}
class Contacts_Model_DbTable_Contact
{
    public static bool $visible = true;
    public function select() { return new Zend_Db_Select(Zend_Db_Table::getDefaultAdapter()); }
    public function fetchAll($select) {
        $where = implode(' ', $select->getPart(Zend_Db_Select::WHERE));
        check(str_contains($where, 'clientid = 7') && str_contains($where, 'contactid = 42') && str_contains($where, 'deleted = 0'), 'Contact history lost tenant, object or deletion scope');
        return self::$visible ? [['contactid' => 42, 'clientid' => 7, 'deleted' => 0]] : [];
    }
}
class HistoryTestDocuments
{
    private int $clientId;
    public function setClientId($id) { $this->clientId = $id; return $this; }
    public function getByContactId($contactid) {
        check($this->clientId === 7 && $contactid === 42, 'Document history lost tenant or contact scope');
        return [];
    }
}
class Sales_Model_DbTable_Quote extends HistoryTestDocuments {}
class Sales_Model_DbTable_Salesorder extends HistoryTestDocuments {}
class Sales_Model_DbTable_Invoice extends HistoryTestDocuments {}
class Sales_Model_DbTable_Deliveryorder extends HistoryTestDocuments {}
class Sales_Model_DbTable_Reminder extends HistoryTestDocuments {}
class Sales_Model_DbTable_Creditnote extends HistoryTestDocuments {}
class Purchases_Model_DbTable_Quoterequest extends HistoryTestDocuments {}
class Purchases_Model_DbTable_Purchaseorder extends HistoryTestDocuments {}
class Processes_Model_DbTable_Process extends HistoryTestDocuments {}

Zend_Db_Table::setDefaultAdapter(new HistoryTestAdapter([
    'host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused',
]));
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
Zend_Registry::set('Zend_Currency', null);
$history = (new Contacts_Model_Get())->history(42);
$list = new Contacts_Model_List_History(['options' => ['states' => $history['states']]]);
$columns = array_column($list->getColumns(), null, 'name');

foreach (['de_DE', 'en_US'] as $locale) {
    $file = $root . '/languages/' . $locale . '/default/state.php';
    $labels = require $file;
    $translator = new DEEC_Translate($locale);
    $translator->load('state', $file);
    $list->setTranslator($translator);

    foreach (range(100, 106) as $state) {
        check(isset($history['states'][$state]), 'Missing history status: ' . $state);
        $html = $list->renderCell((object)['state' => $state], $columns['state']);
        check(strip_tags($html) === $labels[$history['states'][$state]], 'Incorrect status label: ' . $locale . '/' . $state);
        check(!str_contains($html, 'class="editable'), 'History status became editable');
    }
}

$selectableStates = (new Application_Model_DbTable_State())->getSelectOptions();
check(!isset($selectableStates[105]) && !isset($selectableStates[106]), 'Final states became selectable');
Contacts_Model_DbTable_Contact::$visible = false;
check((new Contacts_Model_Get())->history(42) === [], 'Foreign, deleted or missing contact exposed history');
Contacts_Model_DbTable_Contact::$visible = true;
Application_Model_DbTable_Permission::$permissions = [];
check((new Contacts_Model_Get())->history(42) === [], 'Missing permission exposed history');
Zend_Registry::getInstance()->offsetUnset('User');
check((new Contacts_Model_Get())->history(42) === [], 'Unauthenticated request exposed history');
echo 'PASS: ' . $checks . " history checks; both locales, selectable states and authorization/tenant guards\n";
