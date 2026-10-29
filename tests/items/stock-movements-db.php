<?php

// Run with: python3 tests/items/stock-movements-db.py (isolated MariaDB only).
if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!isset($argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-stock-movements-db-')) {
	echo "SKIP: start the isolated database with python3 tests/items/stock-movements-db.py\n";
	exit;
}

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
	$base = $module === 'Application'
		? '/application/'
		: '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if(is_file($path)) require_once $path;
});

function checkMovement(bool $condition, string $message): void
{
	if(!$condition) throw new RuntimeException($message);
}

$socket = $argv[1];
$bootstrap = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bootstrap->exec('CREATE DATABASE dewawi_stock_movements_test');
$bootstrap = null;

$adapter = new Zend_Db_Adapter_Pdo_Mysql([
	'host' => 'localhost',
	'unix_socket' => $socket,
	'username' => 'root',
	'password' => '',
	'dbname' => 'dewawi_stock_movements_test',
	'charset' => 'utf8mb4',
]);

$adapter->query('CREATE TABLE item (id INT PRIMARY KEY, sku VARCHAR(100), title VARCHAR(255), clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$adapter->query('CREATE TABLE warehouse (id INT PRIMARY KEY, code VARCHAR(100), title VARCHAR(255), clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$adapter->query('CREATE TABLE ledger (id INT PRIMARY KEY, itemid INT NOT NULL, warehouseid INT NOT NULL, type VARCHAR(20), reason VARCHAR(50), quantity DECIMAL(12,4), ledgerdate DATETIME, referencemodule VARCHAR(50), referencetype VARCHAR(50), referenceid INT, referencepositionid INT, comment TEXT, clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$adapter->query('CREATE TABLE stockplanning (id INT PRIMARY KEY, type VARCHAR(20), itemid INT NOT NULL, warehouseid INT NOT NULL, quantity DECIMAL(12,4), fulfilled DECIMAL(12,4), planningdate DATE, status VARCHAR(20), referencemodule VARCHAR(50), referencetype VARCHAR(50), referenceid INT, referencepositionid INT, externalnumber VARCHAR(255), comment TEXT, clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');

foreach([
	['id' => 1, 'sku' => 'A-100', 'title' => 'Alpha', 'clientid' => 7],
	['id' => 2, 'sku' => 'B-200', 'title' => 'Beta', 'clientid' => 7],
	['id' => 3, 'sku' => 'FOREIGN', 'title' => 'Foreign', 'clientid' => 8],
] as $row) $adapter->insert('item', $row);
foreach([
	['id' => 1, 'code' => 'MAIN', 'title' => 'Main warehouse', 'clientid' => 7],
	['id' => 2, 'code' => 'OTHER', 'title' => 'Other warehouse', 'clientid' => 7],
	['id' => 3, 'code' => 'FOREIGN', 'title' => 'Foreign warehouse', 'clientid' => 8],
] as $row) $adapter->insert('warehouse', $row);

$adapter->insert('ledger', ['id' => 1, 'itemid' => 1, 'warehouseid' => 1, 'type' => 'inflow', 'reason' => 'receipt', 'quantity' => 5, 'ledgerdate' => '2026-10-29 10:00:00', 'referencetype' => 'purchaseorder', 'referenceid' => 501, 'comment' => 'First receipt', 'clientid' => 7]);
$adapter->insert('ledger', ['id' => 2, 'itemid' => 2, 'warehouseid' => 2, 'type' => 'outflow', 'reason' => 'delivery', 'quantity' => 2, 'ledgerdate' => '2026-10-31 10:00:00', 'referencetype' => 'salesorder', 'referenceid' => 601, 'comment' => 'First issue', 'clientid' => 7]);
$adapter->insert('ledger', ['id' => 3, 'itemid' => 3, 'warehouseid' => 3, 'type' => 'inflow', 'reason' => 'receipt', 'quantity' => 999, 'ledgerdate' => '2026-11-02 10:00:00', 'clientid' => 8]);
$adapter->insert('ledger', ['id' => 4, 'itemid' => 1, 'warehouseid' => 1, 'type' => 'inflow', 'reason' => 'receipt', 'quantity' => 99, 'ledgerdate' => '2026-11-03 10:00:00', 'clientid' => 7, 'deleted' => 1]);
$adapter->insert('stockplanning', ['id' => 10, 'type' => 'incoming', 'itemid' => 1, 'warehouseid' => 1, 'quantity' => 10, 'fulfilled' => 0, 'planningdate' => '2026-10-30', 'status' => 'planned', 'externalnumber' => 'SUP-10', 'comment' => 'Incoming plan', 'clientid' => 7]);
$adapter->insert('stockplanning', ['id' => 11, 'type' => 'reservation', 'itemid' => 2, 'warehouseid' => 2, 'quantity' => 3, 'fulfilled' => 0, 'planningdate' => '2026-11-01', 'status' => 'draft', 'comment' => 'Reservation plan', 'clientid' => 7]);
$adapter->insert('stockplanning', ['id' => 12, 'type' => 'incoming', 'itemid' => 3, 'warehouseid' => 3, 'quantity' => 999, 'fulfilled' => 0, 'planningdate' => '2026-11-04', 'status' => 'planned', 'clientid' => 8]);

Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);

$defaults = [
	'keyword' => '', 'movement' => 'all', 'itemid' => 0, 'item' => '', 'warehouseid' => 0,
	'status' => 'all', 'daterange' => 'all', 'from' => '2026-10-01', 'to' => '2026-11-30',
	'order' => 'movementdate', 'sort' => 'DESC', 'page' => 1, 'limit' => 2, '_export' => false,
];
$query = new Items_Model_MovementQuery();

[$page1, $records] = $query->fetch($defaults);
checkMovement($records === 4, 'Combined list includes deleted or foreign-tenant rows');
checkMovement(array_column($page1, 'source') === ['stockplanning', 'ledger'], 'Global newest-first ordering is not applied across both tables');
checkMovement(array_map('intval', array_column($page1, 'id')) === [11, 2], 'First mixed page contains incorrect records');
checkMovement((float)$page1[0]['signedquantity'] === -3.0 && (float)$page1[1]['signedquantity'] === -2.0, 'Reservation or outflow sign is incorrect');

[$page2, $records2, $applied] = $query->fetch(array_replace($defaults, ['page' => 2]));
checkMovement($records2 === 4 && array_map('intval', array_column($page2, 'id')) === [10, 1], 'Second mixed page is incorrect');
checkMovement((float)$page2[0]['signedquantity'] === 10.0 && (float)$page2[1]['signedquantity'] === 5.0, 'Incoming or inflow sign is incorrect');
[$lastPage, , $clamped] = $query->fetch(array_replace($defaults, ['page' => 99]));
checkMovement($clamped['page'] === 2 && array_map('intval', array_column($lastPage, 'id')) === [10, 1], 'Out-of-range pagination was not clamped');

[$booked, $bookedCount] = $query->fetch(array_replace($defaults, ['movement' => 'booked', 'limit' => 25]));
checkMovement($bookedCount === 2 && array_unique(array_column($booked, 'source')) === ['ledger'], 'Booked movement filter is incorrect');
[$incoming, $incomingCount] = $query->fetch(array_replace($defaults, ['movement' => 'incoming', 'limit' => 25]));
checkMovement($incomingCount === 1 && (int)$incoming[0]['id'] === 10, 'Incoming planning filter is incorrect');
[$itemRows, $itemCount] = $query->fetch(array_replace($defaults, ['itemid' => 1, 'limit' => 25]));
checkMovement($itemCount === 2 && array_map('intval', array_values(array_unique(array_column($itemRows, 'itemid')))) === [1], 'Item filter is incorrect');
[$warehouseRows, $warehouseCount] = $query->fetch(array_replace($defaults, ['warehouseid' => 2, 'limit' => 25]));
checkMovement($warehouseCount === 2 && array_map('intval', array_values(array_unique(array_column($warehouseRows, 'warehouseid')))) === [2], 'Warehouse filter is incorrect');
[$statusRows, $statusCount] = $query->fetch(array_replace($defaults, ['status' => 'draft', 'limit' => 25]));
checkMovement($statusCount === 1 && (int)$statusRows[0]['id'] === 11, 'Unified status filter is incorrect');
[$dateRows, $dateCount] = $query->fetch(array_replace($defaults, ['daterange' => 'custom', 'from' => '2026-10-30', 'to' => '2026-10-31', 'limit' => 25]));
checkMovement($dateCount === 2 && array_map('intval', array_column($dateRows, 'id')) === [2, 10], 'Unified date filter is incorrect');
[$searchRows, $searchCount] = $query->fetch(array_replace($defaults, ['keyword' => 'SUP-10', 'limit' => 25]));
checkMovement($searchCount === 1 && (int)$searchRows[0]['id'] === 10, 'Unified text search is incorrect');
[$exportRows, $exportCount] = $query->fetch(array_replace($defaults, ['_export' => true]));
checkMovement($exportCount === 4 && count($exportRows) === 4, 'CSV export query is incorrectly paginated');

checkMovement((int)$adapter->fetchOne('SELECT COUNT(*) FROM ledger WHERE clientid = 7') === 3, 'Combined list changed physical ledger rows');
checkMovement((float)$adapter->fetchOne('SELECT SUM(quantity) FROM stockplanning WHERE clientid = 7') === 13.0, 'Combined list changed planning quantities');

$toolbar = new Items_Form_ToolbarMovements();
$actions = $toolbar->getToolbarElements('actions');
checkMovement(array_keys($actions) === ['receipt', 'delivery', 'correction', 'incoming', 'reservation', 'filter', 'reset'], 'Movement toolbar actions are incomplete or incorrectly ordered');
checkMovement($actions['receipt']['attribs']['data-route-controller'] === 'ledger' && $actions['receipt']['attribs']['data-route-reason'] === 'receipt', 'Receipt action route is incorrect');
checkMovement($actions['delivery']['attribs']['data-route-type'] === 'outflow', 'Delivery action does not preselect an outflow');
checkMovement($actions['incoming']['attribs']['data-route-controller'] === 'stockplanning', 'Incoming action does not reuse stock planning');
checkMovement($toolbar->getElement('add') === null, 'Generic movement add action is visible');
foreach(['movement', 'itemid', 'item', 'warehouseid', 'status', 'daterange', 'from', 'to', 'order', 'sort'] as $name) {
	checkMovement($toolbar->getElement($name) !== null, 'Movement filter is missing: ' . $name);
}

$list = new Items_Model_List_Movements();
$columns = array_column($list->getColumns(), 'name');
checkMovement($columns === ['movementdate', 'sku', 'itemtitle', 'warehousetitle', 'movementtype', 'signedquantity', 'status', 'reference', 'comment', 'actions'], 'Common movement columns changed');
$list->setItems($exportRows);
$csvStream = fopen('php://temp', 'w+');
$list->writeCsv($csvStream);
rewind($csvStream);
$csv = stream_get_contents($csvStream);
fclose($csvStream);
checkMovement(substr_count($csv, "\n") === 5 && str_contains($csv, 'SUP-10'), 'Common CSV export is incomplete');

$menu = file_get_contents($root . '/application/views/helpers/MainMenu.php');
checkMovement(substr_count($menu, "'label' => 'MENU_STOCK_MOVEMENTS'") === 1, 'Main menu does not contain exactly one stock movement entry');
$stockToolbar = new Items_Form_ToolbarStock();
checkMovement($stockToolbar->getElement('add') === null, 'Generic stock add action is visible');
$planningController = file_get_contents($root . '/application/modules/items/controllers/StockplanningController.php');
checkMovement(str_contains($planningController, "\$params = ['movement' => 'planned'];"), 'Legacy stock planning overview does not open the common movement list');
checkMovement(str_contains($planningController, "\$params['movement'] = \$type;"), 'Legacy planning type filters are not preserved');
$globalJs = file_get_contents($root . '/js/global.js');
checkMovement(str_contains($globalJs, "var routeReason = String(\$button.data('route-reason') || '');"), 'Movement reason is not forwarded to the existing form');

foreach(['de_DE', 'en_US'] as $locale) {
	$translations = require $root . '/languages/' . $locale . '/items/ledger.php';
	foreach(['ITEMS_MOVEMENTS', 'ITEMS_MOVEMENT_DATE', 'ITEMS_MOVEMENT_TYPE', 'ITEMS_MOVEMENT_STATUS', 'ITEMS_MOVEMENT_BOOK_RECEIPT', 'ITEMS_MOVEMENT_PLAN_INCOMING', 'ITEMS_MOVEMENT_RESERVE_ITEM'] as $key) {
		checkMovement(isset($translations[$key]), $locale . ' translation is missing: ' . $key);
	}
}

echo "PASS: unified stock movements, filters, ordering, pagination, tenant isolation and routes\n";
