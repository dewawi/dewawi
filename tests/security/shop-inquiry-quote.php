<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: python3 tests/security/shop-inquiry-quote.py (isolated MariaDB and real PDF rendering).
if (!isset($argv[1])) {
	echo "SKIP: run python3 tests/security/shop-inquiry-quote.py\n";
	exit;
}
if (!str_starts_with($argv[1], '/tmp/dewawi-shop-inquiry-quote-')) throw new RuntimeException('Only isolated test sockets are accepted');

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
$testRoot = dirname($argv[1]) . '/base';
mkdir($testRoot, 0775, true);
symlink($root . '/library', $testRoot . '/library');
define('BASE_PATH', $testRoot);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});

require_once $root . '/application/controllers/helpers/Calculate.php';
require_once $root . '/application/controllers/helpers/Currency.php';
require_once $root . '/application/controllers/helpers/PriceRule.php';
require_once $root . '/application/modules/shops/controllers/InquiryController.php';

$checks = 0;
function checkInquiryQuote(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

function inquiryQuoteSchema(string $structure, string $table): string
{
	$start = strpos($structure, 'CREATE TABLE IF NOT EXISTS `' . $table . '`');
	$end = $start === false ? false : strpos($structure, ') ENGINE=InnoDB', $start);
	if ($start === false || $end === false) throw new RuntimeException("Schema for $table was not found");
	$end = strpos($structure, ';', $end);
	return substr($structure, $start, $end - $start + 1);
}

class ShopInquiryQuoteController extends Shops_InquiryController
{
	public function init() {}

	public function setup(DEEC_Site_Context $context, array $formRow, array $suggestions): void
	{
		$this->_siteContext = $context;
		$this->formRow = $formRow;
		$this->formDataSession = new stdClass();
		$this->formDataSession->suggestions = $suggestions;
	}

	public function createQuote(array $data, int $inquiryId): ?int
	{
		return $this->createQuoteFromInquiry($data, $inquiryId);
	}

	public function createPdf(int $quoteId): array
	{
		return $this->generateQuotePdf($quoteId);
	}

	public function restore(array $inquiry): array
	{
		return $this->restoreInquiryResult($inquiry);
	}
}

$socket = $argv[1];
$bootstrap = new PDO('mysql:unix_socket=' . $socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bootstrap->exec('CREATE DATABASE dewawi_shop_inquiry_quote_test');
$bootstrap = null;
$adapter = new Zend_Db_Adapter_Pdo_Mysql([
	'host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '',
	'dbname' => 'dewawi_shop_inquiry_quote_test', 'charset' => 'utf8mb4',
]);
$structure = file_get_contents($root . '/install/config/structure.sql');
foreach (['address', 'category', 'contact', 'currency', 'email', 'filename', 'footer', 'increment', 'item', 'language', 'pricerulepos', 'quote', 'quotepos', 'shopinquirydata', 'taxrate', 'template', 'uom'] as $table) {
	$adapter->query(inquiryQuoteSchema($structure, $table));
}

$adapter->insert('increment', [
	'clientid' => 100, 'contactid' => 501, 'creditnoteid' => 1, 'deliveryorderid' => 1, 'invoiceid' => 1,
	'purchaseorderid' => 1, 'quoteid' => 1000, 'quoterequestid' => 1, 'reminderid' => 1, 'salesorderid' => 1, 'shoporderid' => 1,
]);
$adapter->insert('category', ['id' => 20, 'title' => 'Chillers', 'type' => 'shop', 'parentid' => 0, 'ordering' => 1, 'activated' => 1, 'shopid' => 120, 'clientid' => 100]);
$adapter->insert('item', [
	'id' => 10, 'catid' => 0, 'shopcatid' => 20, 'sku' => 'ZCE110', 'title' => 'ZCE 110', 'description' => 'Cooling unit',
	'type' => 'default', 'price' => 1000, 'specialprice' => 900, 'taxid' => 7, 'uomid' => 3,
	'shopenabled' => 1, 'shopid' => 120, 'clientid' => 100,
]);
$adapter->insert('taxrate', ['id' => 7, 'name' => 'Reduced fixture', 'rate' => 7, 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('uom', ['id' => 3, 'title' => 'Stück', 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('currency', ['id' => 1, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('language', ['id' => 1, 'code' => 'de_DE', 'name' => 'Deutsch', 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('template', ['id' => 100, 'description' => 'DEEC', 'company' => 'DEEC GmbH', 'default' => 1, 'ordering' => 1, 'activated' => 1, 'clientid' => 100]);
$adapter->insert('footer', ['id' => 1, 'templateid' => 100, 'column' => 1, 'text' => 'Fixture footer', 'width' => 180, 'language' => 'de_DE', 'clientid' => 100]);
$adapter->insert('filename', [
	'id' => 1, 'clientid' => 100, 'creditnote' => 'Gutschrift-%NUMBER%.pdf', 'deliveryorder' => 'Lieferschein-%NUMBER%.pdf',
	'invoice' => 'Rechnung-%NUMBER%.pdf', 'purchaseorder' => 'Bestellung-%NUMBER%.pdf', 'quote' => 'Angebot-%NUMBER%.pdf',
	'quoterequest' => 'Anfrage-%NUMBER%.pdf', 'reminder' => 'Mahnung-%NUMBER%.pdf', 'salesorder' => 'Auftrag-%NUMBER%.pdf', 'language' => 'de_DE',
]);
$adapter->insert('contact', ['id' => 1, 'contactid' => 500, 'catid' => 0, 'name1' => 'Private Master Name', 'taxfree' => 0, 'clientid' => 100]);
$adapter->insert('address', [
	'id' => 1, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing',
	'street' => 'Private Master Street 99', 'postcode' => '99999', 'city' => 'Private City', 'country' => 'DE', 'ordering' => 1, 'clientid' => 100,
]);
$adapter->insert('email', [
	'id' => 1, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'buyer@example.test',
	'password' => 'unused', 'ordering' => 1, 'clientid' => 100,
]);
$inquiryData = [
	'sku' => 'ZCE110', 'quantity' => '2', 'firma' => 'Submitted GmbH', 'ansprechpartner' => 'Ada Submitted',
	'email' => 'buyer@example.test', 'adresse' => 'Submitted Street 1', 'plz' => '12345', 'stadt' => 'Submitted City',
	'country' => 'DE', 'telefon' => '+49 123', 'projektname' => 'Cooling Project',
];
$adapter->insert('shopinquirydata', [
	'id' => 1, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('a', 32), 'data' => json_encode($inquiryData),
	'status' => 'new', 'clientid' => 100,
]);

Zend_Db_Table::setDefaultAdapter($adapter);
$context = new DEEC_Site_Context(['id' => 120, 'clientid' => 100]);
Zend_Registry::set('SiteContext', $context);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', 'de_DE'));
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Calculate());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Currency());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_PriceRule());
Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view = new Zend_View();
Zend_Controller_Front::getInstance()->setControllerDirectory(['shops' => $root . '/application/modules/shops/controllers']);

$request = new Zend_Controller_Request_Simple('index', 'inquiry', 'shops');
$controller = new ShopInquiryQuoteController($request, new Zend_Controller_Response_Http());
$controller->setup($context, ['id' => 865, 'language' => 'de_DE', 'quotetemplateid' => 100, 'quoteheader' => 'Header', 'quotefooter' => 'Footer'], [
	['description' => 'ZCE110', 'value' => 9.87, 'percent' => 100.7, 'title' => 'referenzleistung'],
]);
checkInquiryQuote(!Zend_Registry::isRegistered('Client') && !Zend_Registry::isRegistered('User'), 'Guest test injected internal Client or User registry state');

$quoteId = $controller->createQuote($inquiryData, 1);
checkInquiryQuote($quoteId > 0, 'Guest inquiry did not create a quote');
$quote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$quoteId]);
$position = $adapter->fetchRow('SELECT * FROM quotepos WHERE parentid = ?', [$quoteId]);
$inquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 1');
checkInquiryQuote((int)$quote['quoteid'] === 1000 && $quote['filename'] === 'Angebot-1000.pdf', 'Quote number or filename was not finalized');
checkInquiryQuote(abs((float)$quote['subtotal'] - 1800) < 0.001 && abs((float)$quote['taxes'] - 126) < 0.001 && abs((float)$quote['total'] - 1926) < 0.001, 'Quantity, special price or configured tax rate was calculated incorrectly');
checkInquiryQuote((float)$position['price'] === 900.0 && (float)$position['quantity'] === 2.0 && (float)$position['taxrate'] === 7.0 && $position['title'] === 'ZCE 110', 'Quote position did not use trusted item values');
checkInquiryQuote($quote['billingname1'] === 'Submitted GmbH' && $quote['billingstreet'] === 'Submitted Street 1' && !str_contains(implode(' ', $quote), 'Private Master'), 'Existing email match exposed private contact master data');
checkInquiryQuote((int)$quote['contactid'] === 500 && (int)$inquiry['contactid'] === 1 && (int)$inquiry['quoteid'] === $quoteId, 'Inquiry, contact and quote were not linked consistently');
checkInquiryQuote((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1001, 'Quote number was not reserved exactly once');

$pdf = $controller->createPdf($quoteId);
checkInquiryQuote(is_file($pdf['path']) && str_starts_with((string)file_get_contents($pdf['path']), '%PDF-'), 'Real quote PDF was not rendered');
checkInquiryQuote($pdf['url'] === '/files/contacts/1/0/100/5/0/500/Angebot-1000.pdf', 'Quote PDF URL is incorrect');
$grant = $controller->restore($inquiry);
checkInquiryQuote($grant['status'] === 'ready' && $grant['url'] === $pdf['url'], 'Safe replay did not restore the existing quote result');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === 1 && (int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1001, 'Safe replay created another quote or consumed a number');

$adapter->insert('shopinquirydata', [
	'id' => 2, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('b', 32), 'data' => json_encode($inquiryData),
	'status' => 'new', 'clientid' => 100,
]);
$adapter->query("CREATE TRIGGER reject_inquiry_quote_position BEFORE INSERT ON quotepos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture position failure'");
$failed = false;
try {
	$controller->createQuote(array_replace($inquiryData, ['email' => 'new-buyer@example.test']), 2);
} catch (Throwable $e) {
	$failed = true;
}
checkInquiryQuote($failed, 'Quote position failure did not reach the transaction');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === 1 && (int)$adapter->fetchOne('SELECT COUNT(*) FROM quotepos') === 1, 'Failed quote left partial document data');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact') === 1 && (int)$adapter->fetchOne('SELECT COUNT(*) FROM email') === 1, 'Failed quote left a partial contact');
$increments = $adapter->fetchRow('SELECT contactid, quoteid FROM increment WHERE clientid = 100');
checkInquiryQuote((int)$increments['contactid'] === 501 && (int)$increments['quoteid'] === 1001, 'Failed quote consumed a contact or document number');
$failedInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 2');
checkInquiryQuote($failedInquiry['quoteid'] === null && $failedInquiry['contactid'] === null, 'Failed quote partially linked the inquiry');

echo "PASS: $checks guest quote transaction, calculation, privacy, PDF and replay cases\n";
