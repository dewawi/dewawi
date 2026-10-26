<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/contact-data.php (in-memory database).
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

function contactDataWhereMatches(string $where, array $row): bool
{
    $where = trim(preg_replace('/^AND /', '', $where));
    if ($where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);

    if (preg_match('/^([a-z0-9_]+) IN \(([^)]*)\)$/i', $where, $match)) {
        $values = array_map(static fn($value) => trim($value, " '\""), explode(',', $match[2]));
        return array_key_exists($match[1], $row) && in_array((string)$row[$match[1]], $values, true);
    }

    if (!preg_match('/^([a-z0-9_]+) = (.*)$/i', $where, $match)) {
        throw new RuntimeException('Unsupported fixture predicate: ' . $where);
    }

    return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '\"");
}

class ContactDataTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $writes = [];

    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }

    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'clientid', 'deleted'];
        foreach ($this->rows[$tableName] ?? [] as $row) $columns = array_merge($columns, array_keys($row));

        $metadata = [];
        foreach (array_unique($columns) as $column) {
            $metadata[$column] = [
                'COLUMN_NAME' => $column,
                'DATA_TYPE' => 'varchar',
                'PRIMARY' => $column === 'id',
                'PRIMARY_POSITION' => 1,
                'IDENTITY' => $column === 'id',
            ];
        }

        return $metadata;
    }

    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');

        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function (array $row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (!contactDataWhereMatches($where, $row)) return false;
            }
            return true;
        }));

        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);

        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }

    public function update($tableName, array $bind, $where = '')
    {
        $where = is_array($where) ? $where : [$where];
        $updated = 0;

        foreach ($this->rows[$tableName] ?? [] as $index => $row) {
            foreach ($where as $condition) {
                if (!contactDataWhereMatches($condition, $row)) continue 2;
            }

            $this->rows[$tableName][$index] = array_replace($row, $bind);
            $this->writes[] = [$tableName, $this->rows[$tableName][$index]];
            $updated++;
        }

        return $updated;
    }
}

class Application_Model_DbTable_Currency
{
    public function getPrimaryCurrency(): array { return ['code' => 'EUR']; }
}

class Application_Model_DbTable_Language
{
    public function getPrimaryLanguage(): array { return ['code' => 'de_DE']; }
}

class Application_Model_DbTable_Template
{
    public function getPrimaryTemplate(): array { return ['id' => 5]; }
}

class ContactDataTestView
{
    public function translate(string $key): string { return $key; }
}

require_once $root . '/application/modules/tasks/controllers/TaskController.php';

class ContactDataTestTaskController extends Tasks_TaskController
{
    private array $params;

    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = [])
    {
        $this->params = $invokeArgs['params'] ?? [];
        $this->view = new ContactDataTestView();
    }

    protected function _getParam($paramName, $default = null)
    {
        return $this->params[$paramName] ?? $default;
    }

    public function createData(): array
    {
        return $this->getCreateData();
    }
}

$adapter = new ContactDataTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);

function setContactClient(int $clientId, int $contactClientId = 0): void
{
    $contactClientId = $contactClientId ?: $clientId;

    Zend_Registry::set('Client', [
        'id' => $clientId,
        'parentid' => $contactClientId !== $clientId ? $contactClientId : 0,
        'modules' => ['contacts' => $contactClientId, 'sales' => $clientId, 'purchases' => $clientId, 'processes' => $clientId, 'tasks' => $clientId],
    ]);
}

function contactDataFixtures(): void
{
    global $adapter;
    $adapter->writes = [];
    $adapter->rows = [
        'contact' => [
            ['id' => 10, 'contactid' => 1000, 'clientid' => 7, 'deleted' => 0, 'name1' => 'Own Company', 'name2' => 'GmbH', 'department' => 'Sales', 'vatin' => 'OWN-VAT', 'currency' => 'USD', 'taxfree' => 1],
            ['id' => 11, 'contactid' => 1100, 'clientid' => 8, 'deleted' => 0, 'name1' => 'Foreign Company', 'name2' => '', 'department' => '', 'vatin' => 'FOREIGN-VAT', 'currency' => 'GBP', 'taxfree' => 0],
            ['id' => 12, 'contactid' => 1200, 'clientid' => 7, 'deleted' => 1, 'name1' => 'Deleted Company', 'name2' => '', 'department' => '', 'vatin' => 'DELETED-VAT', 'currency' => 'EUR', 'taxfree' => 0],
            ['id' => 13, 'contactid' => 1300, 'clientid' => 70, 'deleted' => 0, 'name1' => 'Parent Company', 'name2' => 'AG', 'department' => 'Parent Sales', 'vatin' => 'PARENT-VAT', 'currency' => 'CHF', 'taxfree' => 0],
        ],
        'address' => [
            ['id' => 100, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'name1' => '', 'name2' => '', 'department' => '', 'street' => 'Own Street 1', 'postcode' => '10000', 'city' => 'Own City', 'country' => 'DE', 'phone' => ''],
            ['id' => 101, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'shipping', 'clientid' => 7, 'deleted' => 0, 'ordering' => 2, 'name1' => 'Own Warehouse', 'name2' => '', 'department' => 'Logistics', 'street' => 'Warehouse 2', 'postcode' => '10001', 'city' => 'Own City', 'country' => 'DE', 'phone' => '123'],
            ['id' => 102, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'clientid' => 8, 'deleted' => 0, 'ordering' => 0, 'name1' => 'Foreign Address', 'name2' => '', 'department' => '', 'street' => 'Foreign Street', 'postcode' => '80000', 'city' => 'Foreign City', 'country' => 'DE', 'phone' => ''],
            ['id' => 103, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'clientid' => 7, 'deleted' => 1, 'ordering' => 0, 'name1' => 'Deleted Address', 'name2' => '', 'department' => '', 'street' => 'Deleted Street', 'postcode' => '10002', 'city' => 'Own City', 'country' => 'DE', 'phone' => ''],
            ['id' => 130, 'parentid' => 13, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'clientid' => 70, 'deleted' => 0, 'ordering' => 1, 'name1' => '', 'name2' => '', 'department' => '', 'street' => 'Parent Street 1', 'postcode' => '70000', 'city' => 'Parent City', 'country' => 'DE', 'phone' => ''],
        ],
    ];
}

function moduleCreateData(string $module, int $contactId): array
{
    if ($module === 'sales') return (new Sales_Service_CreateDataFactory())->build('quote', $contactId);
    if ($module === 'purchases') return (new Purchases_Service_CreateDataFactory())->build('purchaseorder', $contactId);
    if ($module === 'processes') return (new Processes_Service_CreateDataFactory())->build('process', $contactId);
    if ($module === 'tasks') {
        return (new ContactDataTestTaskController(
            new Zend_Controller_Request_Http(),
            new Zend_Controller_Response_Http(),
            ['params' => ['contactid' => $contactId]]
        ))->createData();
    }
    throw new InvalidArgumentException('Unknown module');
}

contactDataFixtures();
setContactClient(7);

$contactDb = new Contacts_Model_DbTable_Contact();
check($contactDb->getById(10)['name1'] === 'Own Company', 'Own contact was not returned');
check($contactDb->getContact(10)['name1'] === 'Own Company', 'Legacy contact lookup rejected own contact');
check($contactDb->getById(11) === null && $contactDb->getContact(11) === false, 'Foreign contact was returned');
check($contactDb->getById(12) === null && $contactDb->getContact(12) === false, 'Deleted contact was returned');
check($contactDb->getById(999) === null && $contactDb->getContact(999) === false, 'Missing contact was returned');
check($contactDb->getContactWithID(1000)['id'] === 10, 'Valid contact number was not found');
check($contactDb->getContactWithID('Own Company') === false, 'Company name was interpreted as a contact number');
check($contactDb->getContactWithID('1000invalid') === false, 'Partially numeric contact number was accepted');
check($contactDb->getContactWithID(1100) === false && $contactDb->getContactWithID(1200) === false, 'Foreign or deleted contact number was returned');

$factory = new Contacts_Service_ContactDataFactory();
$ownData = $factory->getContactData(10);
check($ownData['contactid'] === 1000 && $ownData['billingname1'] === 'Own Company', 'Own contact data was not populated');
check($ownData['billingstreet'] === 'Own Street 1' && $ownData['shippingstreet'] === 'Warehouse 2', 'Scoped contact addresses were not populated');
check($factory->getContactData(11) === [], 'Foreign contact data was populated');
check($factory->getContactData(12) === [], 'Deleted contact data was populated');
check($factory->getContactData(999) === [], 'Missing contact data was populated');
check($factory->getContactData(13) === [], 'Parent contact was available without shared contact context');

foreach (['sales', 'purchases', 'processes', 'tasks'] as $module) {
    $data = moduleCreateData($module, 10);
    check(($data['billingname1'] ?? null) === 'Own Company', ucfirst($module) . ' did not prefill the own contact');
    $data = moduleCreateData($module, 11);
    check(!array_key_exists('billingname1', $data), ucfirst($module) . ' prefills a foreign contact');
    $data = moduleCreateData($module, 12);
    check(!array_key_exists('billingname1', $data), ucfirst($module) . ' prefills a deleted contact');
}

setContactClient(7, 70);
$sharedData = (new Contacts_Service_ContactDataFactory())->getContactData(13);
check($sharedData['billingname1'] === 'Parent Company' && $sharedData['billingstreet'] === 'Parent Street 1', 'Shared parent contact was not populated');
foreach (['sales', 'purchases', 'processes', 'tasks'] as $module) {
    check((moduleCreateData($module, 13)['billingname1'] ?? null) === 'Parent Company', ucfirst($module) . ' rejected the shared parent contact');
}

contactDataFixtures();
setContactClient(7);
$contactDb = new Contacts_Model_DbTable_Contact();
$contactDb->updateContact(10, ['name1' => 'Updated Company']);
check($adapter->rows['contact'][0]['name1'] === 'Updated Company', 'Own contact was not updated');
check(count($adapter->writes) === 1, 'Own contact update changed an unexpected row');

$adapter->writes = [];
$contactDb->updateContact(11, ['name1' => 'Changed Foreign Company']);
$contactDb->updateContact(12, ['name1' => 'Changed Deleted Company']);
check($adapter->rows['contact'][1]['name1'] === 'Foreign Company', 'Foreign contact was updated');
check($adapter->rows['contact'][2]['name1'] === 'Deleted Company', 'Deleted contact was updated');
check($adapter->writes === [], 'Denied contact update caused a write');

$contactDb->deleteContact(10);
check($adapter->rows['contact'][0]['deleted'] === 1, 'Own contact was not soft-deleted');
$adapter->writes = [];
$contactDb->deleteContact(11);
check($adapter->rows['contact'][1]['deleted'] === 0 && $adapter->writes === [], 'Foreign contact was deleted');

echo 'PASS: ' . $checks . " tenant-safe contact data cases\n";
