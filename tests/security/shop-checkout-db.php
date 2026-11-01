<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: python3 tests/security/shop-checkout-db.py (isolated MariaDB only).
if (!isset($argv[1])) {
	echo "SKIP: run python3 tests/security/shop-checkout-db.py\n";
	exit;
}
if (!str_starts_with($argv[1], '/tmp/dewawi-shop-checkout-db-')) {
	throw new RuntimeException('Only isolated test sockets are accepted');
}

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

function createAdapter(string $socket): Zend_Db_Adapter_Pdo_Mysql
{
	return new Zend_Db_Adapter_Pdo_Mysql([
		'host' => 'localhost',
		'unix_socket' => $socket,
		'username' => 'root',
		'password' => '',
		'dbname' => 'dewawi_shop_checkout_test',
		'charset' => 'utf8mb4',
	]);
}

function setContext(Zend_Db_Adapter_Abstract $adapter, int $clientId = 7, int $siteId = 41): void
{
	Zend_Db_Table::setDefaultAdapter($adapter);
	Zend_Registry::set('Client', ['id' => $clientId]);
	Zend_Registry::set('User', ['id' => 0]);
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => $siteId, 'clientid' => $clientId]));
}

function schemaTable(string $structure, string $table): string
{
	$start = strpos($structure, 'CREATE TABLE IF NOT EXISTS `' . $table . '`');
	$end = $start === false ? false : strpos($structure, ') ENGINE=InnoDB', $start);
	if ($start === false || $end === false) throw new RuntimeException("Schema for $table was not found");
	$end = strpos($structure, ';', $end);
	return substr($structure, $start, $end - $start + 1);
}

$socket = $argv[1];
$bootstrap = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bootstrap->exec('CREATE DATABASE dewawi_shop_checkout_test');
$bootstrap = null;
$adapter = createAdapter($socket);
$structure = file_get_contents($root . '/install/config/structure.sql');
foreach (['increment', 'shoporder', 'shoporderpos'] as $table) $adapter->query(schemaTable($structure, $table));
$adapter->insert('increment', [
	'clientid' => 7, 'contactid' => 1, 'creditnoteid' => 1, 'deliveryorderid' => 1, 'invoiceid' => 1,
	'purchaseorderid' => 1, 'quoteid' => 1, 'quoterequestid' => 1, 'reminderid' => 1,
	'salesorderid' => 1, 'shoporderid' => 1000,
]);
setContext($adapter);

$positions = [
	['itemid' => 101, 'total' => 20.0, 'quantity' => 2.0, 'price' => 10.0],
	['itemid' => 102, 'total' => 5.0, 'quantity' => 1.0, 'price' => 5.0],
];
$orderData = ['contactid' => 0, 'invoiceid' => 0, 'orderdate' => '2026-11-01', 'total' => 25.0];
$token = str_repeat('a', 32);
$orderDb = (new Shops_Model_DbTable_Order())->setClientId(7);
$saved = $orderDb->saveCheckoutOnce(41, $token, $orderData, $positions);
check($saved['inserted'] && (int)$saved['order']['orderid'] === 1000, 'Checkout was not stored with the reserved number');
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporder') === 1, 'Checkout order count is incorrect');
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporderpos') === 2, 'Checkout positions were not stored atomically');
check((int)$adapter->fetchOne('SELECT shoporderid FROM increment WHERE clientid = 7') === 1001, 'Checkout increment was not advanced once');
$duplicate = $orderDb->saveCheckoutOnce(41, $token, $orderData, $positions);
check(!$duplicate['inserted'] && (int)$duplicate['order']['id'] === (int)$saved['order']['id'], 'Checkout replay did not return the original order');
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporder') === 1 && (int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporderpos') === 2, 'Checkout replay duplicated data');
check((int)$adapter->fetchOne('SELECT shoporderid FROM increment WHERE clientid = 7') === 1001, 'Checkout replay consumed another number');

$adapter->query("CREATE TRIGGER reject_checkout_position BEFORE INSERT ON shoporderpos FOR EACH ROW BEGIN IF NEW.itemid = 999 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture position failure'; END IF; END");
$failed = false;
try {
	$orderDb->saveCheckoutOnce(41, str_repeat('b', 32), $orderData, [['itemid' => 999, 'total' => 1, 'quantity' => 1, 'price' => 1]]);
} catch (Exception $e) {
	$failed = true;
}
check($failed, 'Fixture position failure did not reach the checkout transaction');
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporder') === 1 && (int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporderpos') === 2, 'Failed position left partial checkout data');
check((int)$adapter->fetchOne('SELECT shoporderid FROM increment WHERE clientid = 7') === 1001, 'Failed checkout consumed an order number');

$isolated = false;
try {
	$orderDb->saveCheckoutOnce(42, str_repeat('c', 32), $orderData, $positions);
} catch (RuntimeException $e) {
	$isolated = true;
}
check($isolated, 'Checkout accepted another site in the current context');

$parallelToken = str_repeat('d', 32);
$resultFiles = [];
$children = [];
for ($i = 0; $i < 2; $i++) {
	$resultFile = sys_get_temp_dir() . '/dewawi-checkout-result-' . getmypid() . '-' . $i;
	$resultFiles[] = $resultFile;
	$pid = pcntl_fork();
	if ($pid === -1) throw new RuntimeException('Could not fork checkout regression child');
	if ($pid === 0) {
		try {
			$childAdapter = createAdapter($socket);
			setContext($childAdapter);
			$result = (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(41, $parallelToken, $orderData, $positions);
			file_put_contents($resultFile, $result['inserted'] ? 'inserted' : 'duplicate');
			exit(0);
		} catch (Throwable $e) {
			file_put_contents($resultFile, 'error:' . $e->getMessage());
			exit(1);
		}
	}
	$children[] = $pid;
}
foreach ($children as $pid) {
	pcntl_waitpid($pid, $status);
	check(pcntl_wexitstatus($status) === 0, 'Parallel checkout child failed');
}
$results = array_map(static fn($file) => trim((string)file_get_contents($file)), $resultFiles);
foreach ($resultFiles as $file) unlink($file);
sort($results);
check($results === ['duplicate', 'inserted'], 'Parallel replay was not reduced to one checkout');
$adapter = createAdapter($socket);
setContext($adapter);
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporder WHERE token = ?', [$parallelToken]) === 1, 'Parallel replay created multiple orders');
check((int)$adapter->fetchOne('SELECT COUNT(*) FROM shoporderpos WHERE orderid = (SELECT orderid FROM shoporder WHERE token = ?)', [$parallelToken]) === 2, 'Parallel replay created incomplete or duplicate positions');
check((int)$adapter->fetchOne('SELECT shoporderid FROM increment WHERE clientid = 7') === 1002, 'Parallel replay advanced the order number more than once');

echo "PASS: {$checks} checkout transaction, replay, concurrency and isolation checks\n";
