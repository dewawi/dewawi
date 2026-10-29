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

class StockSummaryQuery extends DEEC_List_Query
{
	public function exportParams(array $params): array
	{
		return $this->normalizeParams($params);
	}
}

class StockSummaryLedger extends Items_Model_DbTable_Ledger
{
	protected $_primary = 'id';
	protected $_cols = [
		'id',
		'itemid',
		'warehouseid',
		'type',
		'quantity',
		'ledgerdate',
		'clientid',
		'deleted',
	];

	public function historicalSql(string $status): string
	{
		return (string)$this->buildStockAtSelect(
			'2026-10-01 23:59:59',
			3,
			[
				'categoryids' => [12, 13],
				'manufacturerid' => 8,
				'stockstatus' => $status,
				'keyword' => 'ext unit',
				'order' => 'quantity',
				'sort' => 'DESC',
			]
		);
	}
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
		'manufacturerid' => 8,
		'stockstatus' => 'negative',
		'country' => 'DE',
		'tagid' => 55,
	],
	[
		'catid' => [
			12 => ['parentid' => 0, 'childs' => [13]],
			13 => ['parentid' => 12, 'childs' => [],],
		],
	],
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
check(str_contains($sql, 'i.catid IN (12, 13)'), 'Category descendants are missing');
check(str_contains($sql, 'i.manufacturerid = 8'), 'Manufacturer filter is missing');
check(str_contains($sql, 's.quantity < 0'), 'Negative stock filter is missing');
check(!str_contains($sql, 'country'), 'Unsupported country filter was applied');

foreach([
	'positive' => 's.quantity > 0',
	'zero' => 's.quantity = 0',
	'negative' => 's.quantity < 0',
] as $status => $condition) {
	(new DEEC_List_Query())->sum(
		['stockstatus' => $status],
		[],
		$config,
		Items_Model_Entity_Stock::stockValueExpression()
	);
	check(str_contains(end($adapter->sql), $condition), 'Stock status condition is missing: ' . $status);
}

(new DEEC_List_Query())->sum(
	['stockstatus' => 'invalid'],
	[],
	$config,
	Items_Model_Entity_Stock::stockValueExpression()
);
check(str_contains(end($adapter->sql), '1 = 0'), 'Invalid stock status was not rejected');

$exportParams = (new StockSummaryQuery())->exportParams([
	'_export' => true,
	'page' => 4,
	'limit' => 25,
	'keyword' => 'ext',
	'catid' => 12,
	'manufacturerid' => 8,
	'warehouseid' => 3,
	'stockstatus' => 'zero',
]);
check($exportParams['limit'] === 0 && $exportParams['offset'] === 0, 'Stock CSV export is restricted to the visible page');
check(
	$exportParams['keyword'] === 'ext'
	&& $exportParams['catid'] === 12
	&& $exportParams['manufacturerid'] === 8
	&& $exportParams['warehouseid'] === 3
	&& $exportParams['stockstatus'] === 'zero',
	'Stock CSV export lost active filters'
);

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

foreach(['add', 'edit', 'copy', 'delete', 'apply', 'country', 'tagid'] as $name) {
	check($toolbar->getElement($name) === null, 'Unsupported stock toolbar element is visible: ' . $name);
}

foreach(['add', 'edit', 'copy', 'delete'] as $name) {
	check((new Items_Form_Toolbar())->getElement($name) !== null, 'Generic item toolbar action was removed: ' . $name);
}

$stockActions = $toolbar->getToolbarElements('actions');
check(array_keys($stockActions) === ['ledger', 'incoming', 'reservation', 'planning', 'filter', 'reset'], 'Stock actions are incomplete or incorrectly ordered');
foreach(['keyword', 'clear', 'filter', 'reset', 'order', 'sort'] as $name) {
	check($toolbar->getElement($name) !== null, 'Stock search, filter or sorting element is missing: ' . $name);
}
check($stockActions['ledger']['attribs']['data-action'] === 'add', 'Stock booking does not use the add action');
check($stockActions['ledger']['attribs']['data-route-controller'] === 'ledger', 'Stock booking route is incorrect');
check($stockActions['incoming']['attribs']['data-route-controller'] === 'stockplanning' && $stockActions['incoming']['attribs']['data-route-type'] === 'incoming', 'Incoming planning route is incorrect');
check($stockActions['reservation']['attribs']['data-route-controller'] === 'stockplanning' && $stockActions['reservation']['attribs']['data-route-type'] === 'reservation', 'Reservation route is incorrect');
check($stockActions['planning']['attribs']['data-action'] === 'index' && $stockActions['planning']['attribs']['data-route-controller'] === 'stockplanning', 'Stock planning overview route is incorrect');

$historicalToolbar = new Items_Form_ToolbarStock();
$historicalToolbar->setHistoricalMode();
check($historicalToolbar->getElement('availabilitydate') === null && $historicalToolbar->getElement('availabilitystatus') === null, 'Historical stock exposes availability-only filters');
check($historicalToolbar->getElement('stockdate') !== null && $historicalToolbar->getElement('ledger') !== null, 'Historical stock lost its date filter or stock actions');

check($toolbar->getElement('catid')['source'] === 'category:item', 'Stock category source is incorrect');
check($toolbar->getElement('catid')['default'] === 'all', 'Stock category default is incorrect');
check($toolbar->getElement('manufacturerid')['source'] === 'manufacturer', 'Manufacturer source is incorrect');
check($toolbar->getElement('manufacturerid')['default'] === '0', 'Manufacturer default is incorrect');
check(
	array_keys($toolbar->getElement('stockstatus')['options']) === ['all', 'positive', 'zero', 'negative'],
	'Stock status options are not fixed'
);

foreach(['positive' => ' > 0', 'zero' => ' = 0', 'negative' => ' < 0'] as $status => $condition) {
	$historicalSql = (new StockSummaryLedger())->historicalSql($status);
	check(str_contains($historicalSql, 'l.clientid = 7'), 'Historical tenant filter is missing');
	check(str_contains($historicalSql, 'l.deleted = 0'), 'Historical deleted ledger filter is missing');
	check(str_contains($historicalSql, 'i.deleted = 0'), 'Historical deleted item filter is missing');
	check(str_contains($historicalSql, 'l.warehouseid = 3'), 'Historical warehouse filter is missing');
	check(str_contains($historicalSql, 'i.catid IN (12, 13)'), 'Historical category filter is missing');
	check(str_contains($historicalSql, 'i.manufacturerid = 8'), 'Historical manufacturer filter is missing');
	check(substr_count($historicalSql, 'LIKE') === 8, 'Historical multi-word search is incomplete');
	check(str_contains($historicalSql, 'HAVING') && str_contains($historicalSql, $condition), 'Historical status does not filter the calculated quantity: ' . $status);
	check(str_contains($historicalSql, 'ORDER BY `quantity` DESC'), 'Historical ordering is incorrect');
}
check(str_contains((new StockSummaryLedger())->historicalSql('invalid'), 'HAVING (1 = 0)'), 'Invalid historical stock status was not rejected');

$translationKeys = [
	'ITEMS_STOCK_VALUE',
	'ITEMS_STOCK_DATE',
	'ITEMS_STOCK_TOTAL_VALUE',
	'ITEMS_STOCK_FILTERED_VALUE',
	'ITEMS_STOCK_PAGE_VALUE',
	'ITEMS_STOCK_FOUND_ITEMS',
	'ITEMS_MANUFACTURERS_ALL',
	'ITEMS_STOCK_STATUS',
	'ITEMS_STOCK_STATUS_ALL',
	'ITEMS_STOCK_STATUS_POSITIVE',
	'ITEMS_STOCK_STATUS_ZERO',
	'ITEMS_STOCK_STATUS_NEGATIVE',
	'ITEMS_STOCK_ACTION_LEDGER',
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

$globalJs = file_get_contents($root . '/js/global.js');
check(str_contains($globalJs, "index: { selection: 'none' }"), 'Toolbar index navigation is not registered');
check(str_contains($globalJs, "var targetController = String(\$button.data('route-controller') || controller);"), 'Toolbar add action ignores its target controller');
check(str_contains($globalJs, "url += '/type/' + encodeURIComponent(routeType);"), 'Toolbar add action loses the planning type');

$stockView = file_get_contents($root . '/application/modules/items/views/scripts/stock/index.phtml');
check(!str_contains($stockView, '/items/stock/add'), 'Stock view still links to the invalid add route');
check(!str_contains($stockView, '/items/stockplanning/'), 'Stock view duplicates planning toolbar actions');

check(
	str_contains(
		$globalJs,
		"if (\$field.attr('name') === 'daterange') {\n"
		. "\t\t\t\t\tDewawiToolbar.toggleDateRange(\$field.val());\n"
		. "\t\t\t\t}\n\n"
		. "\t\t\t\t$('#pagination-page').val(1);\n"
		. "\t\t\t\tsearch();"
	),
	'Filter changes do not reset pagination'
);

echo "PASS: stock filters, historical stock, valuation, AJAX and translations\n";
