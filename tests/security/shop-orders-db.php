<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-shop-checkout-db-')) throw new RuntimeException('Run via isolated shop-checkout-db.py');
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class); if (count($parts) < 3) return;
	$module = array_shift($parts); $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
require_once $root . '/install/helper.php';
restore_exception_handler();
ini_set('display_errors', '1');
$checks = 0;
function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function denied(callable $action, string $key): void {
	try { $action(); } catch (DomainException $e) { check($e->getMessage() === $key, 'Unexpected denial: ' . $e->getMessage()); return; }
	throw new RuntimeException('Expected denial: ' . $key);
}
function adapter(string $socket): Zend_Db_Adapter_Pdo_Mysql {
	return new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_orders_test', 'charset' => 'utf8mb4']);
}
$socket = $argv[1];
$pdo = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE dewawi_shop_orders_test'); $pdo = null;
$db = adapter($socket); Zend_Db_Table::setDefaultAdapter($db);
$sql = []; PMA_splitSqlFile($sql, file_get_contents($root . '/install/config/structure.sql'));
foreach ($sql as $query) $db->query($query['query']);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'name' => 'Order reviewer']);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => 'de_DE']));
Zend_Registry::set('Config', ['language' => 'de_DE']);
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
$sessionDir = dirname($socket) . '/order-sessions'; mkdir($sessionDir, 0700);
Zend_Session::setOptions(['save_path' => $sessionDir, 'use_cookies' => false]);
$db->insert('permission', ['userid' => 42, 'clientid' => 7, 'sales' => json_encode(['salesorder' => ['view', 'edit', 'add']]), 'contacts' => json_encode(['contact' => ['view', 'add', 'edit']])]);
foreach ([7, 8, 9] as $tenant) {
	$db->insert('currency', ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'clientid' => $tenant]);
	$db->insert('language', ['code' => 'de_DE', 'name' => 'Deutsch', 'clientid' => $tenant]);
	$db->insert('template', ['description' => 'Order test', 'clientid' => $tenant, 'activated' => 1]);
	$db->insert('increment', ['clientid' => $tenant, 'contactid' => 1, 'creditnoteid' => 1, 'deliveryorderid' => 1, 'invoiceid' => 1, 'purchaseorderid' => 1, 'quoteid' => 1, 'quoterequestid' => 1, 'reminderid' => 1, 'salesorderid' => 1, 'shoporderid' => 1000]);
}
$shop = ['id' => 41, 'clientid' => 7, 'title' => 'Original shop', 'url' => 'https://shop.example.test', 'language' => 'de_DE', 'timezone' => 'Europe/Berlin', 'checkoutenabled' => 1, 'activated' => 1];
$db->insert('shop', $shop);
$db->insert('shop', array_replace($shop, ['id' => 42, 'title' => 'Other shop']));
$db->insert('shop', array_replace($shop, ['id' => 43, 'clientid' => 8]));
$db->insert('contact', ['id' => 21, 'contactid' => 2001, 'name1' => 'Changed contact name', 'currency' => 'USD', 'taxfree' => 1, 'clientid' => 7, 'catid' => 0]);
$db->insert('contact', ['id' => 22, 'contactid' => 2002, 'name1' => 'Foreign contact', 'clientid' => 8, 'catid' => 0]);
$db->insert('item', ['id' => 101, 'clientid' => 7, 'shopid' => 41, 'catid' => 0, 'title' => 'Changed item', 'sku' => 'CHANGED', 'price' => 999, 'currency' => 'EUR']);
$post = ['billingname' => 'Alice Example', 'billingcompany' => 'Original buyer GmbH', 'billingdepartment' => 'Accounting', 'billingstreet' => "Old street 1\nFloor 2", 'billingpostcode' => '10115', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'billingphone' => '+49 111', 'email' => 'alice@example.test', 'message' => 'Deliver carefully', 'differentshippingaddress' => 1, 'shippingname' => 'Bob Receiver', 'shippingcompany' => 'Original destination', 'shippingdepartment' => 'Warehouse', 'shippingstreet' => 'Delivery street 2', 'shippingpostcode' => '20095', 'shippingcity' => 'Hamburg', 'shippingcountry' => 'DE', 'shippingphone' => '+49 222'];
$positions = [
	['itemid' => 101, 'price' => 12.3456, 'quantity' => 2.5, 'total' => 30.86, 'snapshot' => json_encode(['version' => 1, 'sku' => 'ORIGINAL-SKU', 'title' => 'Original item title', 'description' => 'Original description', 'currency' => 'EUR', 'taxrate' => 19, 'uom' => 'Stück', 'price_source' => 'specialprice'])],
	['itemid' => 102, 'price' => 8, 'quantity' => 1, 'total' => 8, 'snapshot' => json_encode(['version' => 1, 'sku' => 'REMOVED-SKU', 'title' => 'Removed item title', 'description' => '', 'currency' => 'EUR', 'taxrate' => 7.7, 'uom' => 'Stück', 'price_source' => 'price'])],
];
$data = array_merge(['invoiceid' => 0, 'contactid' => 0, 'orderdate' => '2026-11-20'], Shops_Service_OrderSnapshot::build($shop, $post, $positions));
check($data['total'] === 38.86 && $data['taxes'] === 6.48 && $data['grosstotal'] === 45.34, 'Tax-group rounding differs from ERP calculation');
$save = static function(string $token, ?array $orderData = null, ?array $rows = null) use ($data, $positions): array {
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 41, 'clientid' => 7], [], ['checkout', 'catalog']));
	try { return (new Shops_Model_DbTable_Order())->setClientId(7)->saveCheckoutOnce(41, $token, $orderData ?? $data, $rows ?? $positions); }
	finally { Zend_Registry::getInstance()->offsetUnset('SiteContext'); }
};

// The real guest controller stores normalized addresses and item/tax snapshots before notification.
require_once $root . '/application/modules/shops/controllers/Action.php';
require_once $root . '/application/modules/shops/controllers/CheckoutController.php';
class OrderCheckoutControllerFixture extends Shops_CheckoutController {
    protected function consumeSubmissionLimit(string $operation, int $limit, int $window): array { return ['allowed' => true]; }
}
class OrderMailFixture extends Zend_Controller_Action_Helper_Abstract {
    public int $calls = 0;
    public function getName() { return 'Email'; }
    public function sendEmail(...$arguments): array { $this->calls++; return ['customerAccepted' => true, 'internalAccepted' => true]; }
}
$mail = new OrderMailFixture(); Zend_Controller_Action_HelperBroker::addHelper($mail);
$db->insert('country', ['code' => 'DE', 'name' => 'Germany', 'language' => 'de_DE', 'clientid' => 7]);
$db->insert('taxrate', ['id' => 11, 'name' => 'VAT', 'rate' => 19, 'clientid' => 7]);
$db->insert('item', ['id' => 103, 'clientid' => 7, 'shopid' => 41, 'catid' => 0, 'title' => 'Checkout article', 'sku' => 'CHECKOUT', 'price' => 500, 'specialprice' => 100, 'taxid' => 11, 'currency' => 'EUR']);
$tr = new DEEC_Translate('de_DE'); $tr->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $tr);
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop, [], ['catalog', 'checkout', 'cart']));
$reviewer = Zend_Registry::get('User'); Zend_Registry::set('User', ['id' => 0]);
$_SERVER['REQUEST_METHOD'] = 'POST';
$request = new Zend_Controller_Request_Http(); $request->setModuleName('shops')->setControllerName('checkout')->setActionName('send');
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request)->setResponse($response)->addControllerDirectory($root . '/application/modules/shops/controllers', 'shops');
$front->getRouter()->addRoute('checkout_success', new Zend_Controller_Router_Route_Static('checkout/success', ['module' => 'shops', 'controller' => 'checkout', 'action' => 'success']));
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$view = new Zend_View(); Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
$controller = new OrderCheckoutControllerFixture($request, $response);
$form = new Shops_Form_Checkout(); $form->csrf_token->initCsrfToken();
$checkoutPost = array_merge($post, ['email' => 'alice@example.com', 'csrf_token' => $form->csrf_token->getHash(), 'checkout_token' => $form->checkout_token->getValue(), 'privacy' => 1, 'module' => 'shops', 'controller' => 'checkout', 'differentshippingaddress' => 0]);
$cart = new Shops_Model_ShoppingCart(); $cart->addItem(103, 'Stale basket label', 'CHECKOUT', 100, 2);
$request->setPost($checkoutPost); $controller->sendAction();
check($response->isRedirect(), 'Real checkout failed: ' . $response->getBody());
$publicOrder = (new Shops_Model_DbTable_Order())->setClientId(7)->getByCheckoutToken(41, $checkoutPost['checkout_token']);
check($publicOrder && (float)$publicOrder['total'] === 200.0 && (float)$publicOrder['taxes'] === 38.0 && (float)$publicOrder['grosstotal'] === 238.0, 'Checkout price/tax snapshot missing');
$publicSnapshot = Shops_Service_OrderSnapshot::decode($publicOrder['snapshot']);
check($publicSnapshot['shipping'] === $publicSnapshot['billing'] && $publicSnapshot['billing']['company'] === $post['billingcompany'], 'Real checkout lost normalized addresses');
$publicPositions = (new Shops_Model_DbTable_Orderpos())->setClientId(7)->getForOrder($publicOrder);
check($publicPositions[0]['snapshot_data']['title'] === 'Checkout article' && $publicPositions[0]['snapshot_data']['price_source'] === 'specialprice', 'Checkout used session label or lost special-price basis');
check($mail->calls === 1 && $cart->getItems() === [], 'Checkout notifications or cart clearing changed');
$controller->sendAction();
check($mail->calls === 1 && (int)$db->fetchOne('SELECT COUNT(*) FROM shoporder WHERE token = ?', $checkoutPost['checkout_token']) === 1, 'Repeated checkout duplicated storage or email');
Zend_Registry::set('User', $reviewer); Zend_Registry::getInstance()->offsetUnset('SiteContext');
Zend_Registry::set('SiteContext', new DEEC_Site_Context($shop, [], ['catalog']));
try {
    new OrderCheckoutControllerFixture($request, new Zend_Controller_Response_Http());
    throw new RuntimeException('Disabled checkout feature accepted');
} catch (Zend_Controller_Action_Exception $e) { check($e->getCode() === 404, 'Checkout feature denial failed'); }
Zend_Registry::getInstance()->offsetUnset('SiteContext');


$result = $save(str_repeat('a', 32)); $id = (int)$result['order']['id'];
$workflow = new Shops_Service_OrderWorkflow(); $order = $workflow->detail($id);
$db->insert('shoporderpos', ['shopid' => 42, 'orderid' => $result['order']['orderid'], 'shoporderid' => $id, 'itemid' => 101, 'total' => 999, 'quantity' => 1, 'price' => 999, 'clientid' => 7]);
$db->insert('shoporderpos', ['shopid' => 41, 'orderid' => $result['order']['orderid'], 'shoporderid' => $id, 'itemid' => 101, 'total' => 999, 'quantity' => 1, 'price' => 999, 'clientid' => 8]);
check(count($workflow->detail($id)['positions']) === 2, 'Foreign shop/tenant positions attached to an order');

check($order['snapshot_data']['billing']['street'] === $post['billingstreet'] && $order['snapshot_data']['shipping']['street'] === $post['shippingstreet'], 'Billing/shipping snapshot lost');
check($order['snapshot_data']['email'] === $post['email'] && $order['snapshot_data']['message'] === $post['message'], 'Contact details/notes lost');
check($order['positions'][0]['snapshot_data']['sku'] === 'ORIGINAL-SKU' && $order['positions'][0]['snapshot_data']['title'] === 'Original item title', 'Current item master replaced historical values');
check(count($order['history']) === 1 && $order['history'][0]['newstatus'] === 'new', 'Initial order audit missing');
check(!$save(str_repeat('a', 32))['inserted'], 'Checkout retry created a duplicate');
check(!$save(str_repeat('a', 32), array_replace($data, ['snapshot' => '{}']))['inserted'] && $workflow->detail($id)['snapshot'] === $order['snapshot'], 'Checkout retry overwrote original snapshot');
$normal = Shops_Service_OrderSnapshot::build($shop, array_replace($post, ['differentshippingaddress' => 0, 'shippingstreet' => 'Forged ignored address']), $positions);
check(Shops_Service_OrderSnapshot::decode($normal['snapshot'])['shipping'] === Shops_Service_OrderSnapshot::decode($normal['snapshot'])['billing'], 'Default shipping address not normalized');
$beforeSnapshot = $order['snapshot'];
$workflow->changeStatus($id, 'in_progress'); $workflow->changeStatus($id, 'in_progress');
$order = $workflow->detail($id);
check(count($order['history']) === 2 && $order['history'][1]['createdby'] == 42 && $order['snapshot'] === $beforeSnapshot, 'Status audit or snapshot immutability failed');
denied(fn() => $workflow->changeStatus($id, 'paid'), 'SHOPS_ORDER_STATUS_INVALID');
denied(fn() => $workflow->changeStatus($id, 'transferred'), 'SHOPS_ORDER_STATUS_INVALID');
denied(fn() => $workflow->db()->updateById($id, ['total' => 1]), 'SHOPS_ORDER_SNAPSHOT_IMMUTABLE');
denied(fn() => (new Shops_Model_DbTable_Orderpos())->setClientId(7)->updateById((int)$order['positions'][0]['id'], ['price' => 1]), 'SHOPS_ORDER_SNAPSHOT_IMMUTABLE');
denied(fn() => $workflow->createSalesorder($id, 22), 'SHOPS_ORDER_SELECT_CONTACT');
$created = $workflow->createSalesorder($id, 21); $salesId = $created['id'];
$sales = (new Sales_Model_DbTable_Salesorder())->setClientId(7)->getById($salesId);
check($created['created'] && $sales['state'] == 100 && !$sales['salesorderid'] && !$sales['completed'] && !$sales['cancelled'], 'Sales order is not a regular unissued draft');
check($sales['contactid'] == 2001 && $sales['currency'] === 'EUR' && !$sales['taxfree'], 'Contact number/currency/tax snapshot incorrectly replaced with contact master');
check($sales['billingname1'] === 'Original buyer GmbH' && $sales['billingname2'] === 'Alice Example' && $sales['billingstreet'] === $post['billingstreet'] && $sales['shippingstreet'] === $post['shippingstreet'], 'Historical addresses not transferred');
check($sales['shippingphone'] === '+49 222' && str_contains($sales['info'], $post['email']) && str_contains($sales['info'], $post['message']), 'Order contact details/notes not transferred');
$salesPositions = (new Sales_Model_DbTable_Salesorderpos())->setClientId(7)->getPositions($salesId)->toArray();
check(count($salesPositions) === 2 && $salesPositions[0]['sku'] === 'ORIGINAL-SKU' && (float)$salesPositions[0]['price'] === 12.3456 && (float)$salesPositions[0]['quantity'] === 2.5 && (float)$salesPositions[1]['taxrate'] === 7.7, 'Historical line values not transferred');
check($salesPositions[1]['itemid'] === null && $salesPositions[1]['sku'] === 'REMOVED-SKU', 'Removed item was reconstructed or foreign reference leaked');
check((float)$sales['subtotal'] === 38.86 && (float)$sales['taxes'] === 6.48 && (float)$sales['total'] === 45.34, 'ERP totals differ from order');
check($workflow->detail($id)['status'] === 'transferred' && $workflow->detail($id)['salesorderid'] == $salesId, 'Persistent sales/order link missing');
check(!$workflow->createSalesorder($id, 21)['created'] && $workflow->createSalesorder($id, 21)['id'] === $salesId, 'Repeated conversion created second sales order');
$workflow->changeStatus($id, 'completed');
denied(fn() => $workflow->changeStatus($id, 'in_progress'), 'SHOPS_ORDER_STATUS_INVALID');
check(!$workflow->createSalesorder($id, 21)['created'], 'Completed linked order duplicated');
$legacyId = (new Shops_Model_DbTable_Order())->setClientId(7)->create(['shopid' => 41, 'orderid' => 'OLD-1', 'invoiceid' => 0, 'total' => 11]);
check($workflow->detail($legacyId)['snapshot_data'] === null && $workflow->detail($legacyId)['status'] === null, 'Unknown historical data was invented');
denied(fn() => $workflow->createSalesorder($legacyId, 21), 'SHOPS_ORDER_INCOMPLETE');
$cancelId = $save(str_repeat('b', 32))['order']['id']; $workflow->changeStatus($cancelId, 'cancelled');
denied(fn() => $workflow->createSalesorder($cancelId, 21), 'SHOPS_ORDER_STATUS_INVALID');
$wrongTotal = $save(str_repeat('c', 32), array_replace($data, ['grosstotal' => 100]))['order']['id'];
$count = (int)$db->fetchOne('SELECT COUNT(*) FROM salesorder');
denied(fn() => $workflow->createSalesorder($wrongTotal, 21), 'SHOPS_ORDER_TOTAL_MISMATCH');
check((int)$db->fetchOne('SELECT COUNT(*) FROM salesorder') === $count && !$workflow->detail($wrongTotal)['salesorderid'], 'Failed conversion did not roll back atomically');
$foreign = array_replace($data, ['shopid' => 43, 'orderid' => 'FOREIGN', 'clientid' => 8]);
$foreignId = (new Shops_Model_DbTable_Order())->setClientId(8)->create($foreign);
denied(fn() => $workflow->detail($foreignId), 'SHOPS_ORDER_NOT_FOUND');
denied(fn() => $workflow->createSalesorder($foreignId, 21), 'SHOPS_ORDER_NOT_FOUND');
$db->update('permission', ['sales' => json_encode(['salesorder' => ['view']])], 'userid = 42');
$reader = new Shops_Service_OrderWorkflow(); check($reader->detail($id)['id'] == $id, 'Read-only order permission rejected');
denied(fn() => $reader->changeStatus($id, 'cancelled'), 'SHOPS_ORDER_DENIED');
denied(fn() => $reader->createSalesorder($wrongTotal, 21), 'SHOPS_ORDER_DENIED');
$db->update('permission', ['sales' => json_encode(['salesorder' => []])], 'userid = 42');
denied(fn() => (new Shops_Service_OrderWorkflow())->detail($id), 'SHOPS_ORDER_DENIED');
$db->update('permission', ['sales' => json_encode(['salesorder' => ['view', 'edit', 'add']])], 'userid = 42');
$query = new DEEC_List_Query();
[$rows, $records] = $query->fetch(['keyword' => 'Original buyer', 'status' => 'all', 'order' => 'created', 'sort' => 'DESC'], [], Shops_Model_Entity_Order::listConfig());
check($records >= 1 && count($rows) === $records && !in_array($foreignId, array_map('intval', array_column($rows->toArray(), 'id')), true), 'Order search leaked tenant or lost snapshot customer');
[$rows, $records] = $query->fetch(['status' => 'cancelled', 'shopid' => 41], [], Shops_Model_Entity_Order::listConfig());
check($records === 1 && $rows[0]['id'] == $cancelId, 'Status/shop filter incorrect');
[$rows, $records] = $query->fetch(['status' => 'unknown'], [], Shops_Model_Entity_Order::listConfig());
check($records === 1 && $rows[0]['id'] == $legacyId, 'Legacy unknown status filter incorrect');



// Snapshot validation never fills missing tax, address, date or currency from master data.
$validOrder = $workflow->detail($wrongTotal);
foreach ([['currency' => 'USD'], ['orderdate' => '0000-00-00'], ['taxes' => null]] as $change) {
    check((bool)Shops_Service_OrderSnapshot::problems(array_replace($validOrder, $change), $validOrder['positions']), 'Invalid historical header accepted');
}
$badAddress = json_decode($validOrder['snapshot'], true); $badAddress['shipping']['street'] = '';
check(in_array('SHOPS_ORDER_ADDRESS_INCOMPLETE', Shops_Service_OrderSnapshot::problems(array_replace($validOrder, ['snapshot' => json_encode($badAddress)]), $validOrder['positions'])), 'Missing shipping address not explained');
$badShop = json_decode($validOrder['snapshot'], true); $badShop['shop']['clientid'] = 8;
check((bool)Shops_Service_OrderSnapshot::problems(array_replace($validOrder, ['snapshot' => json_encode($badShop)]), $validOrder['positions']), 'Foreign snapshot owner accepted');
$db->update('permission', ['sales' => json_encode(['salesorder' => ['view', 'edit']])], 'userid = 42');
denied(fn() => (new Shops_Service_OrderWorkflow())->createSalesorder($wrongTotal, 21), 'SHOPS_ORDER_DENIED');
$db->update('permission', ['sales' => json_encode(['salesorder' => ['view', 'edit', 'add']]), 'contacts' => json_encode(['contact' => []])], 'userid = 42');
denied(fn() => (new Shops_Service_OrderWorkflow())->createSalesorder($wrongTotal, 21), 'SHOPS_ORDER_DENIED');
check((new Shops_Service_OrderWorkflow())->contacts('Changed') === [], 'Contact suggestions ignore contact permissions');
$db->update('permission', ['contacts' => json_encode(['contact' => ['view', 'add', 'edit']])], 'userid = 42');

// Parent-child sharing uses separate shop, contact, item and sales owners.
$sharedId = $save(str_repeat('f', 32))['order']['id'];
Zend_Registry::set('Client', ['id' => 9, 'parentid' => 7, 'modules' => ['shops' => 7, 'sales' => 9, 'contacts' => 8, 'items' => 8]]);
$shared = new Shops_Service_OrderWorkflow();
check($shared->detail($sharedId)['clientid'] == 7, 'Authorized shared shop data unavailable');
$sharedResult = $shared->createSalesorder($sharedId, 22);
$sharedSales = (new Sales_Model_DbTable_Salesorder())->setClientId(9)->getById($sharedResult['id']);
check($sharedSales && $sharedSales['contactid'] == 2002 && $sharedSales['clientid'] == 9, 'Parent-child contact and sales scopes were conflated');
$sharedRows = (new Sales_Model_DbTable_Salesorderpos())->setClientId(9)->getPositions($sharedResult['id'])->toArray();
check($sharedRows[0]['itemid'] === null && $sharedRows[0]['sku'] === 'ORIGINAL-SKU', 'Unshared item reference exposed through ERP');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$own = new Shops_Service_OrderWorkflow();
check($own->salesorder($own->detail($sharedId)) === null, 'ERP link leaked a different sales owner');
denied(fn() => $own->createSalesorder($sharedId, 21), 'SHOPS_ORDER_LINK_UNAVAILABLE');
Zend_Registry::set('Client', ['id' => 8, 'parentid' => 0]);
denied(fn() => (new Shops_Service_OrderWorkflow())->detail($id), 'SHOPS_ORDER_NOT_FOUND');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$unknownTax = $positions; $unknownRow = json_decode($unknownTax[0]['snapshot'], true); $unknownRow['taxrate'] = null; $unknownTax[0]['snapshot'] = json_encode($unknownRow);
$unknownData = Shops_Service_OrderSnapshot::build($shop, $post, $unknownTax);
check($unknownData['taxes'] === null && $unknownData['grosstotal'] === null, 'Unknown tax silently treated as zero');
$zeroTax = $positions; foreach ($zeroTax as &$zero) { $r = json_decode($zero['snapshot'], true); $r['taxrate'] = 0; $zero['snapshot'] = json_encode($r); } unset($zero);
check(Shops_Service_OrderSnapshot::build($shop, $post, $zeroTax)['taxes'] === 0.0, 'Explicit zero VAT lost');

// Concurrent conversions use independent connections and row locks, with one persistent outcome.
$concurrentId = $save(str_repeat('d', 32))['order']['id'];
$children = [];
for ($i = 0; $i < 2; $i++) {
	$pid = pcntl_fork();
	if ($pid === 0) {
		try {
			$childDb = adapter($socket); Zend_Db_Table::setDefaultAdapter($childDb);
			$r = (new Shops_Service_OrderWorkflow())->createSalesorder($concurrentId, 21);
			file_put_contents(dirname($socket) . '/order-child-' . $i, json_encode($r)); exit(0);
		} catch (Throwable $e) { file_put_contents(dirname($socket) . '/order-child-' . $i, $e->getMessage()); exit(1); }
	}
	$children[] = $pid;
}
foreach ($children as $pid) { pcntl_waitpid($pid, $status); check(pcntl_wexitstatus($status) === 0, 'Concurrent conversion failed'); }
$db = adapter($socket); Zend_Db_Table::setDefaultAdapter($db);
$r1 = json_decode(file_get_contents(dirname($socket) . '/order-child-0'), true);
$r2 = json_decode(file_get_contents(dirname($socket) . '/order-child-1'), true);
check($r1['id'] === $r2['id'] && $r1['created'] !== $r2['created'], 'Parallel conversion created more than one sales order');
$httpId = $save(str_repeat('e', 32))['order']['id'];
$db->insert('permission', ['userid' => 43, 'clientid' => 7, 'sales' => json_encode(['salesorder' => ['view']])]);
$db->insert('permission', ['userid' => 44, 'clientid' => 7, 'sales' => json_encode(['salesorder' => []])]);
file_put_contents(dirname($socket) . '/order-fixtures.json', json_encode(['new' => $httpId, 'legacy' => $legacyId, 'linked' => $id, 'contact' => 21, 'foreign_contact' => 22]));
echo "PASS: $checks persistent order, status, permissions, snapshots, ERP draft and concurrency checks\n";
