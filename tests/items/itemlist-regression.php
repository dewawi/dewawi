<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/items/itemlist-regression.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());

spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if(count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if(is_file($path)) require_once $path;
});
require_once $root . '/application/controllers/helpers/Options.php';

$checks = 0;
function checkItemlist(bool $condition, string $message): void
{
	global $checks;
	if(!$condition) throw new RuntimeException($message);
	$checks++;
}

class ItemlistRegressionAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $queries = [];

	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }

	public function describeTable($tableName, $schemaName = null)
	{
		$columns = match ($tableName) {
			'item' => ['id', 'sku', 'title', 'catid', 'price', 'currency', 'clientid', 'deleted', 'pinned', 'modified'],
			'category' => ['id', 'parentid', 'type', 'title', 'clientid', 'activated', 'deleted', 'ordering'],
			default => ['id', 'entityid', 'tagid', 'module', 'controller', 'clientid', 'deleted', 'ordering'],
		};
		$metadata = [];
		foreach($columns as $column) {
			$metadata[$column] = [
				'COLUMN_NAME' => $column,
				'DATA_TYPE' => 'varchar',
				'PRIMARY' => $column === 'id',
				'PRIMARY_POSITION' => $column === 'id' ? 1 : null,
				'IDENTITY' => $column === 'id',
			];
		}
		return $metadata;
	}

	public function fetchOne($sql, $bind = [])
	{
		$this->queries[] = (string)$sql;
		return 1;
	}

	public function query($sql, $bind = [])
	{
		if(!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
		$this->queries[] = $sql->assemble();
		$from = $sql->getPart(Zend_Db_Select::FROM);
		$table = reset($from)['tableName'];
		$rows = match ($table) {
			'item' => [['id' => 10, 'sku' => 'SKU-10', 'title' => 'Decimal item', 'catid' => 3, 'price' => '19.9500', 'currency' => 'EUR', 'clientid' => 7, 'deleted' => 0, 'pinned' => 0, 'modified' => '2026-01-01']],
			'category' => [['id' => 3, 'parentid' => 0, 'type' => 'item', 'title' => 'Hardware', 'clientid' => 7, 'activated' => 1, 'deleted' => 0, 'ordering' => 1]],
			default => [['id' => 20, 'entityid' => 10, 'tagid' => 30, 'module' => 'items', 'controller' => 'item', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'tag' => 'Featured']],
		};
		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode) { return $this->rows; }
		};
	}
}

$adapter = new ItemlistRegressionAdapter([
	'host' => 'unused',
	'username' => 'unused',
	'password' => 'unused',
	'dbname' => 'unused',
]);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);

[$items, $records] = (new Items_Model_Get())->items([
	'catid' => 3,
	'keyword' => '',
	'tagid' => 0,
	'order' => 'modified',
	'sort' => 'ASC',
	'limit' => 0,
], ['catid' => [3 => 'Hardware']], true);

checkItemlist($records === 1 && count($items) === 1, 'Legacy item-list download query is unavailable');
checkItemlist((string)$items[0]['price'] === '19.9500', 'Item-list query changed a decimal price');
$itemSql = implode("\n", $adapter->queries);
checkItemlist(str_contains($itemSql, 'i.clientid = 7') && str_contains($itemSql, 'i.deleted = 0'), 'Item-list query lost tenant or soft-delete scope');
checkItemlist(str_contains($itemSql, 'i.catid IN (3)') && !str_contains($adapter->queries[array_key_last($adapter->queries)], 'LIMIT'), 'Complete category export is paginated or unfiltered');

Zend_Registry::set('Client', ['id' => 8, 'parentid' => 7, 'modules' => ['contacts' => 70, 'items' => 7, 'sales' => 8]]);
$adapter->queries = [];
(new DEEC_List_Query())->fetch([
	'catid' => 3,
	'keyword' => '',
	'tagid' => 0,
	'order' => 'modified',
	'sort' => 'ASC',
	'limit' => 25,
], ['catid' => [3 => 'Shared contact category']], Sales_Model_Entity_Quote::listConfig());
$sharedSql = implode("\n", $adapter->queries);
checkItemlist(str_contains($sharedSql, 'category') && str_contains($sharedSql, 'clientid = 70'), 'Document filter loaded contact categories from the document tenant');

$adapter->queries = [];
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote')->setActionName('index');
Zend_Controller_Front::getInstance()->setRequest($request);
$form = new DEEC_Form();
$form->addElement(['name' => 'catid', 'type' => 'select', 'source' => 'category:contact']);
(new Application_Controller_Action_Helper_Options())->getOptions($form);
checkItemlist(str_contains(implode("\n", $adapter->queries), 'clientid = 70'), 'Document toolbar loaded contact categories from the document tenant');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);

foreach([
	Items_Model_Entity_Item::class => 'item',
	Items_Model_Entity_Inventory::class => 'item',
	Items_Model_Entity_Pricerule::class => 'item',
	Sales_Model_Entity_Quote::class => 'contact',
	Sales_Model_Entity_Salesorder::class => 'contact',
	Sales_Model_Entity_Invoice::class => 'contact',
	Sales_Model_Entity_Deliveryorder::class => 'contact',
	Sales_Model_Entity_Creditnote::class => 'contact',
	Sales_Model_Entity_Reminder::class => 'contact',
	Purchases_Model_Entity_Quoterequest::class => 'contact',
	Purchases_Model_Entity_Purchaseorder::class => 'contact',
	Processes_Model_Entity_Process::class => 'contact',
	Campaigns_Model_Entity_Campaign::class => 'contact',
] as $class => $type) {
	checkItemlist(($class::listConfig()['filters']['catid']['categoryType'] ?? '') === $type, $class . ' cannot resolve category filter rows');
}

$adapter->queries = [];
$tags = (new Items_Model_Get())->tags('items', 'item', 10);
$tagSql = implode("\n", $adapter->queries);
checkItemlist(count($tags) === 1 && $tags[0]['tag'] === 'Featured', 'Item tags are unavailable');
checkItemlist(str_contains($tagSql, 'tag.clientid = t.clientid') && str_contains($tagSql, 'tag.deleted = 0')
	&& str_contains($tagSql, 't.clientid = 7'), 'Item tags bypass the central tenant-scoped definition join');

$controller = file_get_contents($root . '/application/modules/items/controllers/ItemlistController.php');
$download = substr($controller, strpos($controller, 'public function downloadAction'), strpos($controller, 'public function deleteAction') - strpos($controller, 'public function downloadAction'));
$delete = substr($controller, strpos($controller, 'public function deleteAction'));
checkItemlist(str_contains($download, "viewRenderer->setRender('pdf')") && !str_contains($download, 'viewRenderer->setNoRender()'), 'Item-list PDF rendering is disabled');
checkItemlist(!str_contains($download, "floor(\$item['price'])"), 'Item-list PDF still truncates decimal prices');
checkItemlist(str_contains($delete, 'new Items_Model_DbTable_Itemlist()') && str_contains($delete, 'deleteItemlist($id)')
	&& !str_contains($delete, 'deleteItem($id)'), 'Item-list deletion still targets an item');

echo 'PASS: ' . $checks . " item-list download, pricing, tag and deletion regression cases\n";
