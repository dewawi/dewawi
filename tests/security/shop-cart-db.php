<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-shop-checkout-db-')) throw new RuntimeException('Run via isolated shop-checkout-db.py');
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$path = $root . '/application/modules/' . strtolower($module) . '/' . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
require_once $root . '/install/helper.php';
restore_exception_handler();
require_once $root . '/application/modules/shops/controllers/Action.php';
require_once $root . '/application/modules/shops/controllers/CartController.php';
$checks = 0;
function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
$pdo = new PDO('mysql:unix_socket=' . $argv[1], 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE dewawi_shop_cart_test');
$pdo = null;
$db = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_cart_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
$sql = [];
PMA_splitSqlFile($sql, file_get_contents($root . '/install/config/structure.sql'));
foreach ($sql as $query) $db->query($query['query']);
$sessionDir = dirname($argv[1]) . '/cart-sessions';
mkdir($sessionDir, 0700);
Zend_Session::setOptions(['save_path' => $sessionDir, 'use_cookies' => false]);
function context(int $client = 7, int $site = 41, array $features = ['cart', 'checkout']): void {
	Zend_Registry::set('Client', ['id' => $client]);
	Zend_Registry::set('User', ['id' => 0]);
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => $site, 'clientid' => $client], [], $features));
}
context();
$legacy = new Zend_Session_Namespace('shopping_cart');
$legacy->items = [999 => ['title' => 'Unverified cart', 'sku' => 'OLD', 'price' => 1, 'quantity' => 1]];
$cart = new Shops_Model_ShoppingCart();
check($cart->getItems() === [] && !isset($legacy->items), 'Legacy unscoped cart was imported');
check($cart->consumeNotices() === ['SHOPS_CART_RESET'], 'Legacy reset is silent');
check($cart->addItem(1, 'Old title', 'A', 10, 1.25), 'Decimal quantity rejected');
$firstToken = $cart->getCsrfToken();
context(7, 42);
$other = new Shops_Model_ShoppingCart();
check($other->getItems() === [] && $firstToken !== $other->getCsrfToken(), 'Shop switch leaked cart or CSRF token');
$other->addItem(2, 'Other shop', 'B', 20);
context(8, 41);
check((new Shops_Model_ShoppingCart())->getItems() === [], 'Tenant switch leaked cart');
context();
$cart = new Shops_Model_ShoppingCart();
check($cart->getItemCount() === 1.25 && count($cart->getItems()) === 1, 'Returning to shop lost its own cart');
foreach ([null, true, [], -1, 0, 'NaN', INF, NAN, '1e309', '1e2', '1foo', '100001', '0.00001', '1.12345', ''] as $quantity) {
	check(!Shops_Model_ShoppingCart::validQuantity($quantity), 'Invalid quantity accepted: ' . var_export($quantity, true));
}
foreach (['0.0001', '2.5000', 100000] as $quantity) check(Shops_Model_ShoppingCart::validQuantity($quantity), 'Valid decimal/boundary quantity rejected');
check(!$cart->updateItem(1, '1foo') && !$cart->addItem(1, 'Title', 'A', 10, 100000), 'Invalid or accumulated excess quantity accepted');
check(!$cart->addItem(3, 'Large value', 'C', 99999999, 2), 'Excess line value accepted');
check(!$cart->addItem(3, 'Large value', 'C', 99999999, 1), 'Excess cart value accepted');
$cart->addItem(11, 'Rounding', 'R', 10.075);
check($cart->getTotalPrice() === 22.58, 'Cart total does not sum rounded line totals');
$cart->clearCart();
$db->insert('uom', ['id' => 1, 'clientid' => 7, 'title' => 'Meter']);
foreach ([
	['id' => 1, 'title' => 'Public item', 'specialprice' => 5, 'uomid' => 1],
	['id' => 2, 'shopid' => 42], ['id' => 3, 'clientid' => 8], ['id' => 4, 'shopenabled' => 0], ['id' => 5, 'deleted' => 1],
	['id' => 6, 'currency' => 'USD'], ['id' => 7, 'price' => null], ['id' => 8, 'price' => -10],
	['id' => 9, 'specialprice' => 0], ['id' => 10, 'specialprice' => -1],
] as $item) $db->insert('item', array_replace(['clientid' => 7, 'shopid' => 41, 'catid' => 0, 'title' => 'Item', 'sku' => 'SKU-' . $item['id'], 'price' => 10, 'currency' => 'EUR', 'shopenabled' => 1], $item));
$db->insert('uom', ['id' => 2, 'clientid' => 8, 'title' => 'Foreign unit']);
$db->update('item', ['uomid' => 2], 'id = 9');
$itemDb = new Shops_Model_DbTable_Item();
check(!$itemDb->getCartItem(9)['uom'], 'Foreign tenant unit leaked into cart');
foreach ([2, 3, 4, 5] as $id) check($itemDb->getCartItem($id) === null, 'Foreign, unpublished or deleted item available');
check(Shops_Model_ShoppingCart::itemPrice($itemDb->getCartItem(9)) === 10.0, 'Zero special price did not use regular price');
foreach ([6, 7, 8, 10] as $id) check(Shops_Model_ShoppingCart::itemPrice($itemDb->getCartItem($id)) === null, 'Invalid currency or price accepted');
$front = Zend_Controller_Front::getInstance();
$front->addControllerDirectory($root . '/application/modules/shops/controllers', 'shops');
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
function cartCall(string $action, array $post = [], string $method = 'POST'): array {
	$cart = new Shops_Model_ShoppingCart();
	$_POST = [];
	$request = new Zend_Controller_Request_HttpTestCase();
	$request->setModuleName('shops')->setControllerName('cart')->setActionName($action)->setMethod($method);
	$request->setPost(array_replace(['csrf_token' => $cart->getCsrfToken(), 'revision' => $cart->getRevision()], $post));
	$response = new Zend_Controller_Response_HttpTestCase();
	Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
	Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView(new Zend_View());
	$controller = new Shops_CartController($request, $response);
	$controller->{$action . 'Action'}();
	return [json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR), $response->getHttpResponseCode()];
}
foreach (['add', 'update', 'remove', 'clear'] as $action) {
	[, $status] = cartCall($action, ['id' => 1, 'quantity' => 1], 'GET');
	check($status === 405, 'GET mutation accepted');
	[, $status] = cartCall($action, ['csrf_token' => '', 'id' => 1, 'quantity' => 1]);
	check($status === 403, 'Missing CSRF token accepted');
	[, $status] = cartCall($action, ['csrf_token' => $other->getCsrfToken(), 'id' => 1, 'quantity' => 1]);
	check($status === 403, 'Other shop CSRF token accepted');
}
foreach ([2, 3, 4, 5, 6, 7, 8, 10] as $id) {
	[$result, $status] = cartCall('add', ['id' => $id]);
	check($status === 400 && !$result['success'] && !$cart->getItems(), 'Unavailable item added');
}
foreach ([['id' => [1]], ['id' => '1abc'], ['id' => 1, 'quantity' => []], ['id' => 1, 'quantity' => '1e309'], ['id' => 1, 'quantity' => '-1'], ['id' => 1, 'quantity' => '100001']] as $post) {
	[, $status] = cartCall('add', $post);
	check(in_array($status, [400, 413], true), 'Manipulated request accepted');
}
$revision = $cart->getRevision();
[$result, $status] = cartCall('add', ['id' => 1, 'quantity' => '1.2500', 'price' => 0, 'title' => 'Forged']);
check($status === 200 && $result['success'] && $result['total'] === 6.25 && $result['cart'][1]['price'] === 5, 'Trusted special price not used');
check($result['cart'][1]['title'] === 'Public item' && $result['cart'][1]['uom'] === 'Meter', 'Trusted metadata or UOM missing');
[, $status] = cartCall('add', ['id' => 1, 'revision' => $revision]);
check($status === 409 && $cart->getItemCount() === 1.25, 'Repeated/parallel stale add duplicated quantity');
foreach (['-1', '1e309', '100001', '1.12345'] as $quantity) {
	[, $status] = cartCall('update', ['id' => 1, 'quantity' => $quantity]);
	check($status === 400 && $cart->getItemCount() === 1.25, 'Invalid update changed cart');
}
$db->update('item', ['specialprice' => 7, 'title' => 'Updated title'], 'id = 1');
[$result, $status] = cartCall('update', ['id' => 1, 'quantity' => 2]);
check($status === 409 && $result['total'] === 8.75 && $result['cart'][1]['title'] === 'Updated title' && $result['notices'], 'Price change not reconciled visibly');
[$result, $status] = cartCall('update', ['id' => 1, 'quantity' => '2.5000']);
check($status === 200 && $result['total'] === 17.5 && $result['cartItemCount'] === 2.5, 'Decimal update failed');
$db->update('item', ['shopenabled' => 0], 'id = 1');
[$result, $status] = cartCall('count', [], 'GET');
check($status === 200 && !$result['cart'] && $result['notices'], 'Revoked sale not removed visibly');
$db->update('item', ['shopenabled' => 1], 'id = 1');
cartCall('add', ['id' => 1]);
$db->delete('item', 'id = 1');
[$result] = cartCall('count', [], 'GET');
check(!$result['cart'] && $result['notices'], 'Deleted item not removed visibly');
cartCall('add', ['id' => 9]);
[$result] = cartCall('update', ['id' => 9, 'quantity' => 0]);
check(!$result['cart'], 'Zero quantity removal changed');
cartCall('add', ['id' => 9]);
[$result] = cartCall('remove', ['id' => 9]);
check($result['success'] && !$result['cart'], 'Remove failed: ' . json_encode($result));
cartCall('add', ['id' => 9]);
[$result] = cartCall('clear');
check($result['success'] && !$result['cart'] && $result['total'] === 0, 'Clear failed');
context(7, 41, ['cart']);
[$result] = cartCall('add', ['id' => 9]);
check($result['success'] && !$result['checkoutEnabled'], 'Cart with disabled checkout failed');
context(7, 41, ['checkout']);
try { cartCall('clear'); throw new RuntimeException('Disabled cart accepted mutation'); }
catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 404, 'Disabled cart did not fail closed'); }
context();
echo "PASS: {$checks} cart validation, tenant/shop isolation, CSRF, stale requests and availability checks\n";
