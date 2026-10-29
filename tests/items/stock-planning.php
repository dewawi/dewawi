<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/items/stock-planning.php (no database required).
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

function checkPlanning(bool $condition, string $message): void
{
	if(!$condition) throw new RuntimeException($message);
}

class StockPlanningTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	protected function _connect()
	{
	}

	protected function _quote($value)
	{
		return Zend_Db_Adapter_Abstract::_quote($value);
	}
}

class StockPlanningTestRows
{
	public function toArray(): array
	{
		return [];
	}
}

class StockPlanningTestTable extends Items_Model_DbTable_Stockplanning
{
	protected $_primary = 'id';
	protected $_cols = ['id', 'type', 'itemid', 'warehouseid', 'quantity', 'fulfilled', 'planningdate', 'dateconfirmed', 'status', 'clientid', 'deleted'];
	public string $sql = '';

	public function fetchAll($where = null, $order = null, $count = null, $offset = null)
	{
		$this->sql = (string)$where;
		return new StockPlanningTestRows();
	}
}

$service = (new ReflectionClass(Items_Service_StockPlanning::class))->newInstanceWithoutConstructor();
$events = [
	['id' => 1, 'type' => 'reservation', 'planningdate' => '2026-11-02', 'dateconfirmed' => 1, 'remaining' => 15],
	['id' => 2, 'type' => 'incoming', 'planningdate' => '2026-11-05', 'dateconfirmed' => 1, 'remaining' => 30],
	['id' => 3, 'type' => 'reservation', 'planningdate' => '2026-11-10', 'dateconfirmed' => 1, 'remaining' => 10],
	['id' => 4, 'type' => 'incoming', 'planningdate' => '2026-11-15', 'dateconfirmed' => 1, 'remaining' => 20],
];
$availability = $service->calculateAvailability(20, $events, '2026-11-10', 25);

checkPlanning($availability['physical'] === 20.0, 'Physical stock changed during forecast');
checkPlanning($availability['projectedStock'] === 50.0, 'Confirmed incoming was not added to projected stock');
checkPlanning($availability['available'] === 25.0, 'Chronological availability is incorrect');
checkPlanning($availability['shortage'] === 0.0, 'Available request has a shortage');
checkPlanning($availability['earliestDate'] === '2026-11-05', 'Earliest delivery date is incorrect');
checkPlanning(array_column($availability['timeline'], 'forecast') === [20.0, 5.0, 35.0, 25.0, 45.0], 'Timeline forecast differs from the planning example');

$partial = $service->calculateAvailability(5.25, [
	['id' => 10, 'type' => 'incoming', 'planningdate' => '2026-11-01', 'dateconfirmed' => 1, 'quantity' => 10.5, 'fulfilled' => 4.25],
	['id' => 11, 'type' => 'reservation', 'planningdate' => '2026-11-02', 'dateconfirmed' => 1, 'quantity' => 7.75, 'fulfilled' => 2.25],
], '2026-11-02', 6);
checkPlanning($partial['openIncoming'] === 6.25, 'Partial receipt remaining quantity is incorrect');
checkPlanning($partial['futureReservations'] === 5.5, 'Partial fulfillment remaining quantity is incorrect');
checkPlanning($partial['available'] === 6.0, 'Decimal quantities lost precision');
checkPlanning($partial['shortage'] === 0.0, 'Exact decimal availability has a shortage');

$unconfirmed = $service->calculateAvailability(2, [
	['id' => 20, 'type' => 'incoming', 'planningdate' => '2026-11-01', 'dateconfirmed' => 0, 'remaining' => 100],
	['id' => 21, 'type' => 'incoming', 'planningdate' => null, 'dateconfirmed' => 0, 'remaining' => 50],
	['id' => 22, 'type' => 'reservation', 'planningdate' => '2026-11-01', 'dateconfirmed' => 0, 'remaining' => 4],
], '2026-11-01', 1);
checkPlanning($unconfirmed['openIncoming'] === 150.0, 'Unconfirmed incoming is missing from open totals');
checkPlanning($unconfirmed['available'] === -2.0, 'Unconfirmed incoming was incorrectly guaranteed');
checkPlanning($unconfirmed['shortage'] === 3.0, 'Overreservation shortage is incorrect');
checkPlanning($unconfirmed['earliestDate'] === date('Y-m-d'), 'Current stock was not returned as the earliest delivery date');

$sameDate = $service->calculateAvailability(0, [
	['id' => 30, 'type' => 'reservation', 'planningdate' => '2026-11-03', 'dateconfirmed' => 1, 'remaining' => 6],
	['id' => 31, 'type' => 'incoming', 'planningdate' => '2026-11-03', 'dateconfirmed' => 1, 'remaining' => 10],
], '2026-11-03', 4);
checkPlanning($sameDate['available'] === 4.0, 'Same-day incoming was not processed before demand');
checkPlanning($sameDate['earliestDate'] === '2026-11-03', 'Same-day delivery date is incorrect');

$otherWarehouse = $service->calculateAvailability(3, [], '2026-11-15', 5);
checkPlanning($otherWarehouse['available'] === 3.0 && $otherWarehouse['shortage'] === 2.0, 'Warehouse-specific availability was mixed');

$adapter = new StockPlanningTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
$planningTable = new StockPlanningTestTable();
$planningTable->getOpenEvents(10, 3, 22);
checkPlanning(str_contains($planningTable->sql, 'clientid = 7'), 'Planning event query lacks tenant isolation');
checkPlanning(str_contains($planningTable->sql, 'itemid = 10') && str_contains($planningTable->sql, 'warehouseid = 3'), 'Planning event query mixes items or warehouses');
checkPlanning(str_contains($planningTable->sql, "status = 'planned'") && str_contains($planningTable->sql, 'deleted = 0'), 'Closed planning events are not excluded');
checkPlanning(str_contains($planningTable->sql, 'id != 22'), 'Reservation availability does not exclude the edited plan');

$schema = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
foreach([$schema, $migration] as $sql) {
	checkPlanning(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `stockplanning`'), 'Planning table is missing');
	checkPlanning(str_contains($sql, '`clientid` int(11) NOT NULL'), 'Planning tenant column is missing');
	checkPlanning(str_contains($sql, '`fulfilled` decimal(12,4)'), 'Partial fulfillment column is missing');
	checkPlanning(str_contains($sql, '`referencepositionid` int(11)'), 'Document position reference is missing');
}

$itemstock = file_get_contents($root . '/application/modules/items/models/DbTable/Itemstock.php');
checkPlanning(str_contains($itemstock, 'SUM(CASE'), 'Derived planning totals are not recalculated');
checkPlanning(str_contains($itemstock, 'AND `clientid` = ?'), 'Planning total synchronization lacks tenant isolation');
$planningService = file_get_contents($root . '/application/modules/items/services/StockPlanning.php');
checkPlanning(str_contains($planningService, 'getByIdForUpdate($id)'), 'Partial fulfillment is not protected by a row lock');
checkPlanning(str_contains($itemstock, 'lockPlanningScope'), 'Concurrent reservations do not share a stock scope lock');
checkPlanning(str_contains($planningService, "'referencetype' => 'stockplanning'"), 'Planning ledger reference is missing');
checkPlanning(str_contains($planningService, 'quantity > $remaining'), 'Duplicate physical fulfillment guard is missing');
checkPlanning(str_contains($planningService, 'validateDuplicate'), 'Duplicate source planning is not detected');

echo "PASS: stock planning chronology, partial quantities, shortages, warehouse isolation and schema guards\n";
