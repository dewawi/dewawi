<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/lists/summary.php (no database required).
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

	$relative = 'application/modules/' . strtolower(array_shift($parts)) . '/'
		. strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	$path = $root . '/' . $relative;

	if(is_file($path)) require_once $path;
});

function check(bool $condition, string $message): void
{
	if(!$condition) throw new RuntimeException($message);
}

class ListSummaryAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $sql = [];
	public array $oneResults = [];
	public array $allResults = [];

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
		return array_shift($this->oneResults) ?? 0;
	}

	public function fetchAll($sql, $bind = [], $fetchMode = null)
	{
		$this->sql[] = (string)$sql;
		return array_shift($this->allResults) ?? [];
	}
}

class ListSummaryTable extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'document';
	protected $_primary = 'id';
	protected $_cols = [
		'id',
		'title',
		'total',
		'currency',
		'warehouseid',
		'clientid',
		'deleted',
	];
}

class ListSummaryList extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'title',
				'label' => 'TITLE',
				'type' => 'text',
			],
			[
				'name' => 'total',
				'label' => 'TOTAL',
				'type' => 'currency',
				'summary' => 'sum',
				'summary_currency_field' => 'currency',
			],
		];
	}
}

class ListWithoutSummary extends DEEC_List
{
	protected function buildColumns()
	{
		return [[
			'name' => 'price',
			'label' => 'PRICE',
			'type' => 'currency',
		]];
	}
}

class ListSummaryControllerProbe extends DEEC_Controller_Action
{
	public function summary(DEEC_List $list, array $params, array $options, array $config): array
	{
		return $this->buildListSummary($list, $params, $options, $config);
	}

	public function shouldSummary(DEEC_List $list, array $params, array $config): bool
	{
		return $this->shouldBuildListSummary($list, $params, $config);
	}
}

class AppliedParamsQuery extends DEEC_List_Query
{
	protected int $calls = 0;

	protected function fetchResult(array $params, array $options, array $config): array
	{
		$this->calls++;

		return [
			'items' => [],
			'records' => $this->calls === 1 ? 0 : 6,
		];
	}
}

class ListSummaryToolbar
{
	public function getElement($name)
	{
		return ['name' => $name];
	}

	public function renderElement($name)
	{
		return '<button data-name="' . $name . '"></button>';
	}
}

$adapter = new ListSummaryAdapter([
	'host' => 'unused',
	'username' => 'unused',
	'password' => 'unused',
	'dbname' => 'unused',
]);

Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);

$config = [
	'tableClass' => 'ListSummaryTable',
	'alias' => 'd',
	'joins' => [[
		'table' => 'document_relation',
		'alias' => 'r',
		'on' => 'r.documentid = d.id',
		'columns' => [],
	]],
	'search' => ['title'],
	'filters' => [
		'warehouseid' => [
			'type' => 'equals',
			'column' => 'warehouseid',
		],
	],
	'orders' => ['title', 'total'],
];

$list = new ListSummaryList([
	'items' => [
		(object)['id' => 1, 'title' => 'One', 'total' => 10, 'currency' => 'EUR'],
		(object)['id' => 2, 'title' => 'Two', 'total' => 5, 'currency' => 'USD'],
	],
	'pagination' => [
		'records' => 4,
		'pages' => 2,
	],
]);

check($list->getSummaryColumn()['field'] === 'total', 'Summable column was not detected');
check($list->getPageSummary($list->getSummaryColumn()) === ['EUR' => 10.0, 'USD' => 5.0], 'Page currencies were mixed');
check((new ListWithoutSummary())->getSummaryColumn() === null, 'Unit price was treated as a total');

$summaryColumns = [
	Items_Model_List_Stocks::class => ['stockvalue', null],
	Processes_Model_List_Processes::class => ['total', 'currency'],
	Purchases_Model_List_Quoterequests::class => ['total', 'currency'],
	Purchases_Model_List_Purchaseorders::class => ['total', 'currency'],
];
foreach($summaryColumns as $class => [$field, $currencyField]) {
	$column = (new $class())->getSummaryColumn();
	check(($column['summary']['field'] ?? $column['field'] ?? null) === $field, $class . ' has the wrong summary field');
	check(($column['summary']['currency_field'] ?? null) === $currencyField, $class . ' has the wrong summary currency field');
}

$secondPage = new ListSummaryList([
	'items' => [
		(object)['id' => 3, 'title' => 'Three', 'total' => 25, 'currency' => 'EUR'],
	],
]);
check($secondPage->getPageSummary($secondPage->getSummaryColumn()) === ['EUR' => 25.0], 'Page summary did not change with page rows');

$list->setFooter(['total' => 'table footer']);
$list->setSummary(['total' => ['EUR' => 15.0]]);
check($list->getFooter()['total'] === 'table footer', 'Table footer was replaced by list summary');
check($list->getSummary()['total']['EUR'] === 15.0, 'List summary was not stored separately');
$list->setTotal(99);
check($list->getTotal() === 99, 'Legacy getTotal behavior changed');
check($list->getRecordCount() === 4, 'Pagination is not the record-count source');
$directList = new ListWithoutSummary(['items' => [(object)['id' => 1], (object)['id' => 2]]]);
check($directList->getRecordCount() === 2, 'Direct list does not count its loaded rows');
$directList->setTotal(7);
check($directList->getRecordCount() === 7, 'Direct list no longer honors its legacy total');

$controller = (new ReflectionClass(ListSummaryControllerProbe::class))->newInstanceWithoutConstructor();

$adapter->allResults = [
	[
		['aggregate_group' => 'EUR', 'total' => 100],
		['aggregate_group' => 'USD', 'total' => 50],
	],
	[
		['aggregate_group' => 'EUR', 'total' => 40],
	],
];

$summary = $controller->summary(
	$list,
	[
		'keyword' => 'acme',
		'warehouseid' => 3,
		'page' => 2,
		'limit' => 2,
		'order' => 'total',
		'sort' => 'DESC',
	],
	[],
	$config
);

check($summary['total'] === ['EUR' => 100.0, 'USD' => 50.0], 'Currency totals are incorrect');
check($summary['filtered'] === ['EUR' => 40.0, 'USD' => 0.0], 'Filtered currencies are incorrect');
check($summary['page'] === ['EUR' => 10.0, 'USD' => 5.0], 'Page summary is incorrect');
check(count($adapter->sql) === 2, 'Unexpected number of summary queries');

$baseSql = $adapter->sql[0];
$filteredSql = $adapter->sql[1];

check(!str_contains($baseSql, "LIKE '%acme%'"), 'Overall total includes keyword filter');
check(!str_contains($baseSql, 'd.warehouseid = 3'), 'Overall total includes optional filter');
check(str_contains($filteredSql, "LIKE '%acme%'"), 'Filtered total is missing keyword filter');
check(str_contains($filteredSql, 'd.warehouseid = 3'), 'Filtered total is missing configured filter');
check(str_contains($filteredSql, 'd.clientid = 7'), 'Tenant filter is missing');
check(str_contains($filteredSql, 'd.deleted = 0'), 'Deleted filter is missing');
check(str_contains($filteredSql, 'MAX(d.total)'), 'Duplicate joins are not collapsed per record');
check(str_contains($filteredSql, 'GROUP BY `d`.`id`'), 'Summary is not grouped by entity');
check(str_contains($filteredSql, 'GROUP BY `filtered_values`.`aggregate_group`'), 'Currencies are not grouped');
check(!str_contains($filteredSql, 'ORDER BY'), 'Summary depends on sorting');
check(!str_contains($filteredSql, 'LIMIT'), 'Summary depends on pagination');

$appliedQuery = new AppliedParamsQuery();
$appliedQuery->fetch(
	[
		'keyword' => 'archived',
		'states' => ['100', '101', '102', '103', '104'],
	],
	[],
	[]
);
check(
	$appliedQuery->getAppliedParams()['states'] === ['100', '101', '102', '103', '104', '105', '106'],
	'Search state expansion was not exposed to summaries'
);

$adapter->sql = [];
$adapter->allResults = [[
	['aggregate_group' => 'EUR', 'total' => 150],
]];

$unfiltered = $controller->summary(
	$list,
	[
		'keyword' => '',
		'warehouseid' => '0',
		'page' => 1,
		'limit' => 25,
	],
	[],
	$config
);

check(count($adapter->sql) === 1, 'Unfiltered summary executed a duplicate query');
check(!isset($unfiltered['filtered']), 'Identical filtered value was repeated');

$adapter->sql = [];

if($controller->shouldSummary($list, ['_export' => true], ['entity' => $config])) {
	$controller->summary($list, ['_export' => true], [], $config);
}

check($adapter->sql === [], 'Export executed summary queries');

$adapter->allResults = [
	[['aggregate_group' => 'EUR', 'total' => 100]],
	[],
];
$emptyList = new ListSummaryList([
	'items' => [],
	'pagination' => [
		'records' => 0,
		'pages' => 1,
	],
]);
$emptySummary = $controller->summary(
	$emptyList,
	['keyword' => 'missing', 'page' => 1, 'limit' => 25],
	[],
	$config
);
check($emptySummary['filtered'] === ['EUR' => 0.0], 'Empty filtered result is not shown as zero');

$adapter->sql = [];
$adapter->oneResults = [229853, 4296];
$stockConfig = Items_Model_Entity_Stock::listConfig();
$stockConfig['tableClass'] = 'ListSummaryTable';
$stockList = new Items_Model_List_Stocks([
	'items' => [
		(object)['stockvalue' => 1962],
		(object)['stockvalue' => 1406],
		(object)['stockvalue' => 928],
	],
	'pagination' => [
		'records' => 5,
		'pages' => 1,
	],
]);

$stockSummary = $controller->summary(
	$stockList,
	[
		'keyword' => 'ext',
		'warehouseid' => 3,
		'page' => 1,
		'limit' => 25,
	],
	[],
	$stockConfig
);

check($stockSummary['total'][''] === 229853.0, 'Stock total is incorrect');
check($stockSummary['filtered'][''] === 4296.0, 'Filtered stock total is incorrect');
check(str_contains($adapter->sql[0], 's.warehouseid = 3'), 'Warehouse was not retained as a base filter');
check(!str_contains($adapter->sql[0], "LIKE '%ext%'"), 'Stock total includes keyword filter');
check(str_contains($adapter->sql[1], "LIKE '%ext%'"), 'Filtered stock total is missing keyword filter');

require_once $root . '/application/views/helpers/ToolbarBottom.php';
$view = new Zend_View();
$view->toolbar = new ListSummaryToolbar();
$view->action = 'index';
$toolbarBottom = new Zend_View_Helper_ToolbarBottom();
$toolbarBottom->setView($view);
$toolbarHtml = $toolbarBottom->ToolbarBottom();

foreach(['add', 'edit', 'copy', 'delete'] as $action) {
	check(str_contains($toolbarHtml, 'data-name="' . $action . '"'), 'Bottom toolbar action is missing: ' . $action);
}

$view->action = 'select';
$selectHtml = $toolbarBottom->ToolbarBottom();
check(str_contains($selectHtml, 'data-name="apply"'), 'Select action was not preserved');
check(!str_contains($selectHtml, 'data-name="delete"'), 'Select mode exposes regular actions');

$renderView = new Zend_View();
$renderView->addScriptPath($root . '/application/views/scripts');
$renderView->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper_');
$renderList = new ListWithoutSummary([
	'items' => [],
	'view' => $renderView,
	'listFooter' => true,
	'toolbar' => new ListSummaryToolbar(),
	'context' => ['action' => 'index'],
	'pagination' => ['records' => 0, 'pages' => 1],
]);
$renderedList = $renderList->render();
check(str_contains($renderedList, 'dw-list-footer'), 'Central list footer was not rendered');
check(str_contains($renderedList, 'data-name="add"'), 'List footer lost its explicit toolbar');
check(str_contains($renderedList, 'LIST_RECORDS'), 'Record count label is missing');
check(
	glob($root . '/application/modules/*/views/scripts/list/list.phtml') === [],
	'A module-specific list partial shadows the central footer'
);

foreach(['campaigns', 'contacts', 'items', 'processes', 'purchases'] as $module) {
	$moduleView = new Zend_View();
	$moduleView->addScriptPath($root . '/application/views/scripts');
	$moduleView->addScriptPath($root . '/application/modules/' . $module . '/views/scripts');
	$moduleView->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper_');
	$moduleList = new ListWithoutSummary([
		'items' => [],
		'view' => $moduleView,
		'listFooter' => true,
		'pagination' => ['records' => 0, 'pages' => 1],
	]);

	check(
		str_contains($moduleList->render(), 'dw-list-footer'),
		'Module view path hides the central list footer: ' . $module
	);
}

$itemlist = new Items_Model_List_Itemlists();
check($itemlist->getSummaryColumn() === null, 'Item lists received a monetary summary');
check(Items_Model_Entity_Itemlist::listConfig()['tableClass'] === 'Items_Model_DbTable_Itemlist', 'Item list uses the wrong entity table');
$itemlistToolbar = new Items_Form_ToolbarItemlist();
foreach(['catid', 'country', 'tagid'] as $name) {
	check($itemlistToolbar->getElement($name) === null, 'Unsupported item-list filter is visible: ' . $name);
}

foreach(['de_DE', 'en_US'] as $locale) {
	$translations = require $root . '/languages/' . $locale . '/default/common.php';
	$itemlistTranslations = require $root . '/languages/' . $locale . '/items/itemlist.php';

	foreach(['LIST_RECORDS', 'LIST_TOTAL_VALUE', 'LIST_FILTERED_VALUE', 'LIST_PAGE_VALUE'] as $key) {
		check(isset($translations[$key]), $locale . ' translation is missing: ' . $key);
	}

	foreach(['ITEM_LISTS', 'ITEM_LISTS_TITLE', 'ITEM_LISTS_DESCRIPTION'] as $key) {
		check(isset($itemlistTranslations[$key]), $locale . ' translation is missing: ' . $key);
	}
}

echo "PASS: central list footer and monetary summaries\n";
