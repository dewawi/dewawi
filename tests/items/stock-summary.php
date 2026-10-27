<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/items/stock-summary.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());

spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);

	if(count($parts) < 3) {
		return;
	}

	$relative = 'application/modules/' . strtolower(array_shift($parts)) . '/'
		. strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	$path = $root . '/' . $relative;

	if(is_file($path)) {
		require_once $path;
	}
});

function check(bool $condition, string $message): void
{
	if(!$condition) {
		throw new RuntimeException($message);
	}
}

class StockSummaryAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $sql = [];
	public float $result = 0.0;

	protected function _connect()
	{
	}

	protected function _quote($value)
	{
		return Zend_Db_Adapter_Abstract::_quote($value);
	}

	public function fetchOne($sql, $bind = [])
	{
		$this->sql[] = (string)$sql;
		return $this->result;
	}
}

class StockSummaryTable extends Items_Model_DbTable_Itemstock
{
	protected $_primary = 'id';
	protected $_cols = [
		'id',
		'itemid',
		'warehouseid',
		'quantity',
		'reserved',
		'incoming',
		'clientid',
		'deleted',
	];
}

require_once $root . '/application/modules/items/controllers/StockController.php';

class StockSummaryController extends Items_StockController
{
	public function parseDate(string $date): ?DateTime
	{
		return $this->parseStockDate($date);
	}
}

$adapter = new StockSummaryAdapter([
	'host' => 'unused',
	'username' => 'unused',
	'password' => 'unused',
	'dbname' => 'unused',
]);

Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);

$config = Items_Model_Entity_Stock::listConfig();
$config['tableClass'] = 'StockSummaryTable';

$adapter->result = 4296.0;
$value = (new DEEC_List_Query())->sum(
	[
		'keyword' => 'ext',
		'warehouseid' => 3,
		'page' => 4,
		'limit' => 10,
		'order' => 'stockvalue',
		'sort' => 'ASC',
		'catid' => '12',
		'country' => 'DE',
		'tagid' => 55,
	],
	[],
	$config,
	Items_Model_Entity_Stock::stockValueExpression()
);

check($value === 4296.0, 'Filtered stock value was not returned');

$sql = end($adapter->sql);

check(str_contains($sql, "LIKE '%ext%'"), 'Keyword filter is missing');
check(str_contains($sql, 's.warehouseid = 3'), 'Warehouse filter is missing');
check(str_contains($sql, 's.clientid = 7'), 'Tenant filter is missing');
check(str_contains($sql, 's.deleted = 0'), 'Deleted stock filter is missing');
check(str_contains($sql, 'i.deleted = 0'), 'Deleted item filter is missing');
check(str_contains($sql, 'WHEN i.parentid > 0'), 'Parent cost fallback is missing');
check(str_contains($sql, 'COALESCE(p.cost, 0)'), 'Parent cost null fallback is missing');
check(!str_contains($sql, 'ORDER BY'), 'Aggregation depends on sorting');
check(!str_contains($sql, 'LIMIT'), 'Aggregation depends on pagination');
check(!str_contains($sql, 'tag_filter'), 'Stock rows are incorrectly filtered as tagged entities');
check(!str_contains($sql, 'catid'), 'Unsupported category filter was applied');
check(!str_contains($sql, 'country'), 'Unsupported country filter was applied');

$adapter->result = 229853.0;
$totalValue = (new StockSummaryTable())->getTotalValue(3);
$totalSql = end($adapter->sql);

check($totalValue === 229853.0, 'Warehouse total was not returned');
check(str_contains($totalSql, 's.warehouseid = 3'), 'Warehouse total is not warehouse-specific');
check(str_contains($totalSql, 's.clientid = 7'), 'Warehouse total is not tenant-specific');
check(str_contains($totalSql, 's.deleted = 0'), 'Warehouse total includes deleted stock');
check(str_contains($totalSql, 'i.deleted = 0'), 'Warehouse total includes deleted items');
check(str_contains($totalSql, 'w.id = s.warehouseid'), 'Warehouse total includes orphaned stock');
check(str_contains($totalSql, (string)Items_Model_Entity_Stock::stockValueExpression()), 'List and total valuation differ');

$toolbar = new Items_Form_ToolbarStock();

foreach(['catid', 'country', 'tagid'] as $name) {
	check($toolbar->getElement($name) === null, 'Unsupported stock filter is visible: ' . $name);
}

$translationKeys = [
	'ITEMS_STOCK_VALUE',
	'ITEMS_STOCK_DATE',
	'ITEMS_STOCK_TOTAL_VALUE',
	'ITEMS_STOCK_FILTERED_VALUE',
	'ITEMS_STOCK_PAGE_VALUE',
	'ITEMS_STOCK_FOUND_ITEMS',
	'STOCK',
];

foreach(['de_DE', 'en_US'] as $locale) {
	$stockTranslations = require $root . '/languages/' . $locale . '/items/stock.php';
	$toolbarTranslations = require $root . '/languages/' . $locale . '/default/toolbar.php';

	foreach($translationKeys as $key) {
		check(isset($stockTranslations[$key]), $locale . ' translation is missing: ' . $key);
	}

	check(isset($toolbarTranslations['CLEAR']), $locale . ' translation is missing: CLEAR');
}

$controller = (new ReflectionClass(StockSummaryController::class))->newInstanceWithoutConstructor();
check($controller->parseDate('2026-10-01')?->format('Y-m-d') === '2026-10-01', 'Valid stock date was rejected');
foreach(['', 'not-a-date', '2026-02-31', '2026-10-1', '01.10.2026'] as $stockDate) {
	check($controller->parseDate($stockDate) === null, 'Invalid stock date was accepted: ' . $stockDate);
}

echo "PASS: stock summary filters, valuation and translations\n";
