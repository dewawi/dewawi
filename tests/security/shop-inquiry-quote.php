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
	public bool $failOriginalPdf = false;

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

	protected function generateQuotePdf(int $quoteId, bool $createOriginal = false): array
	{
		if ($createOriginal && $this->failOriginalPdf) throw new RuntimeException('fixture PDF failure');
		return parent::generateQuotePdf($quoteId, $createOriginal);
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
foreach (['address', 'category', 'contact', 'currency', 'documentarchive', 'email', 'filename', 'footer', 'increment', 'item', 'language', 'pricerulepos', 'quote', 'quotepos', 'shopinquirydata', 'taxrate', 'template', 'uom'] as $table) {
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
$adapter->insert('contact', [
	'id' => 1, 'contactid' => 500, 'catid' => 8, 'name1' => 'Private Master Name', 'taxfree' => 0,
	'currency' => 'USD', 'priceruleamount' => 35, 'priceruleaction' => 'bypercent', 'paymentterm' => 'private terms', 'clientid' => 100,
]);
$adapter->insert('contact', ['id' => 2, 'contactid' => 600, 'catid' => 0, 'name1' => 'Tax Free Contact', 'taxfree' => 1, 'clientid' => 100]);
$adapter->insert('contact', ['id' => 3, 'contactid' => 601, 'catid' => 0, 'name1' => 'Ambiguous One', 'taxfree' => 0, 'clientid' => 100]);
$adapter->insert('contact', ['id' => 4, 'contactid' => 602, 'catid' => 0, 'name1' => 'Ambiguous Two', 'taxfree' => 0, 'clientid' => 100]);
$adapter->insert('contact', [
	'id' => 5, 'contactid' => 700, 'catid' => 9, 'name1' => 'Foreign Privileged Contact', 'taxfree' => 1,
	'priceruleamount' => 50, 'priceruleaction' => 'bypercent', 'clientid' => 200,
]);
$adapter->insert('address', [
	'id' => 1, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing',
	'street' => 'Private Master Street 99', 'postcode' => '99999', 'city' => 'Private City', 'country' => 'DE', 'ordering' => 1, 'clientid' => 100,
]);
$adapter->insert('email', [
	'id' => 1, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'buyer@example.test',
	'password' => 'unused', 'ordering' => 1, 'clientid' => 100,
]);
$adapter->insert('email', ['id' => 2, 'parentid' => 2, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'taxfree@example.test', 'password' => 'unused', 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('email', ['id' => 3, 'parentid' => 3, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'shared@example.test', 'password' => 'unused', 'ordering' => 1, 'clientid' => 100]);
$adapter->insert('email', ['id' => 4, 'parentid' => 4, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'shared@example.test', 'password' => 'unused', 'ordering' => 2, 'clientid' => 100]);
$adapter->insert('email', ['id' => 5, 'parentid' => 5, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'foreign@example.test', 'password' => 'unused', 'ordering' => 1, 'clientid' => 200]);
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

$matchedContactBefore = $adapter->fetchRow('SELECT * FROM contact WHERE id = 1');
$quoteId = $controller->createQuote($inquiryData, 1);
checkInquiryQuote($quoteId > 0, 'Guest inquiry did not create a quote');
$quote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$quoteId]);
$position = $adapter->fetchRow('SELECT * FROM quotepos WHERE parentid = ?', [$quoteId]);
$inquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 1');
checkInquiryQuote((int)$quote['quoteid'] === 1000 && $quote['filename'] === 'Angebot-1000.pdf', 'Quote number or filename was not finalized');
checkInquiryQuote(abs((float)$quote['subtotal'] - 1800) < 0.001 && abs((float)$quote['taxes'] - 126) < 0.001 && abs((float)$quote['total'] - 1926) < 0.001, 'Quantity, special price or configured tax rate was calculated incorrectly');
checkInquiryQuote((float)$position['price'] === 900.0 && (float)$position['quantity'] === 2.0 && (float)$position['taxrate'] === 7.0 && $position['title'] === 'ZCE 110', 'Quote position did not use trusted item values');
checkInquiryQuote((int)$quote['taxfree'] === 0 && $quote['currency'] === 'EUR' && empty($quote['paymentmethod']), 'Unverified contact tax or commercial terms reached the public quote');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM pricerulepos WHERE parentid = ?', [$position['id']]) === 0, 'Unverified contact pricing created a quote price rule');
checkInquiryQuote($quote['billingname1'] === 'Submitted GmbH' && $quote['billingstreet'] === 'Submitted Street 1' && !str_contains(implode(' ', $quote), 'Private Master'), 'Existing email match exposed private contact master data');
checkInquiryQuote((int)$quote['contactid'] === 500 && (int)$inquiry['contactid'] === 1 && (int)$inquiry['quoteid'] === $quoteId, 'Inquiry, contact and quote were not linked consistently');
checkInquiryQuote($adapter->fetchRow('SELECT * FROM contact WHERE id = 1') === $matchedContactBefore, 'Existing contact data was changed by the public inquiry');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100') === 4, 'Existing contact email match created a duplicate contact');
checkInquiryQuote((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1001, 'Quote number was not reserved exactly once');

$pdf = $controller->createPdf($quoteId);
checkInquiryQuote(is_file($pdf['path']) && str_starts_with((string)file_get_contents($pdf['path']), '%PDF-'), 'Real quote PDF was not rendered');
$archive = $adapter->fetchRow('SELECT * FROM documentarchive WHERE documentid = ? AND module = ? AND controller = ?', [$quoteId, 'sales', 'quote']);
checkInquiryQuote($archive && $archive['filename'] === 'Angebot-1000.pdf' && hash_equals($archive['sha256'], hash_file('sha256', $pdf['path'])), 'Inquiry quote was not registered as the canonical sales quote original');
checkInquiryQuote($pdf['url'] === '/' . $archive['path'], 'Quote PDF URL is incorrect');
$originalHash = hash_file('sha256', $pdf['path']);
$samePdf = $controller->createPdf($quoteId);
checkInquiryQuote(hash_equals($originalHash, hash_file('sha256', $samePdf['path'])), 'Repeated PDF access changed the archived inquiry quote');
$foreignArchiveDenied = false;
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 220, 'clientid' => 200]));
try {
	$controller->createPdf($quoteId);
} catch (Throwable $e) {
	$foreignArchiveDenied = true;
}
Zend_Registry::set('SiteContext', $context);
checkInquiryQuote($foreignArchiveDenied, 'Inquiry quote archive crossed the tenant boundary');
$grant = $controller->restore($inquiry);
checkInquiryQuote($grant['status'] === 'ready' && $grant['url'] === $pdf['url'], 'Safe replay did not restore the existing quote result');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === 1 && (int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1001, 'Safe replay created another quote or consumed a number');

$adapter->insert('shopinquirydata', [
	'id' => 2, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('b', 32),
	'data' => json_encode(array_replace($inquiryData, ['email' => 'taxfree@example.test'])),
	'status' => 'new', 'clientid' => 100,
]);
$manualTax = false;
try {
	$controller->createQuote(array_replace($inquiryData, ['email' => 'taxfree@example.test']), 2);
} catch (Throwable $e) {
	$manualTax = true;
}
checkInquiryQuote($manualTax, 'Unverified tax-free contact received an automatic quote');
$taxInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 2');
checkInquiryQuote($taxInquiry['quoteid'] === null && $taxInquiry['contactid'] === null, 'Manual tax case was linked to an automatic quote or contact');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === 1 && (int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1001, 'Manual tax case changed quote data or numbering');

$adapter->insert('shopinquirydata', [
	'id' => 3, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('c', 32),
	'data' => json_encode(array_replace($inquiryData, ['email' => 'shared@example.test'])),
	'status' => 'new', 'clientid' => 100,
]);
$ambiguous = false;
try {
	$controller->createQuote(array_replace($inquiryData, ['email' => 'shared@example.test']), 3);
} catch (Throwable $e) {
	$ambiguous = true;
}
checkInquiryQuote($ambiguous, 'Ambiguous contact email received an automatic quote');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100') === 4, 'Ambiguous contact email created another contact');
$ambiguousInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 3');
checkInquiryQuote($ambiguousInquiry['quoteid'] === null && $ambiguousInquiry['contactid'] === null, 'Ambiguous contact email was linked automatically');

$foreignData = array_replace($inquiryData, ['email' => 'foreign@example.test', 'firma' => 'Submitted Foreign Match GmbH']);
$adapter->insert('shopinquirydata', [
	'id' => 4, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('d', 32), 'data' => json_encode($foreignData),
	'status' => 'new', 'clientid' => 100,
]);
$foreignQuoteId = $controller->createQuote($foreignData, 4);
$foreignQuote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$foreignQuoteId]);
$foreignPosition = $adapter->fetchRow('SELECT * FROM quotepos WHERE parentid = ?', [$foreignQuoteId]);
$localContact = $adapter->fetchRow('SELECT * FROM contact WHERE clientid = 100 AND contactid = 501');
$foreignInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 4');
checkInquiryQuote($foreignQuoteId > 0 && (int)$foreignQuote['quoteid'] === 1001, 'Foreign-tenant email did not create an isolated local quote');
checkInquiryQuote((int)$foreignQuote['contactid'] === 501 && (int)$foreignInquiry['contactid'] === (int)$localContact['id'] && (int)$foreignInquiry['quoteid'] === $foreignQuoteId, 'Foreign-tenant email crossed the contact or inquiry boundary');
checkInquiryQuote((int)$foreignQuote['taxfree'] === 0 && abs((float)$foreignQuote['taxes'] - 126) < 0.001 && (float)$foreignPosition['price'] === 900.0, 'Foreign contact tax or pricing affected the local public quote');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 200 AND contactid = 700 AND taxfree = 1') === 1, 'Foreign contact was changed');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100') === 5, 'Foreign-tenant email did not create exactly one local contact');

$pdfFailureData = array_replace($inquiryData, ['email' => 'pdf-failure@example.test']);
$adapter->insert('shopinquirydata', [
	'id' => 5, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('e', 32), 'data' => json_encode($pdfFailureData),
	'status' => 'new', 'clientid' => 100,
]);
$controller->failOriginalPdf = true;
$pdfFailed = false;
try {
	$controller->createQuote($pdfFailureData, 5);
} catch (Throwable $e) {
	$pdfFailed = str_contains($e->getMessage(), 'fixture PDF failure');
}
$controller->failOriginalPdf = false;
$pdfFailureInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 5');
$draftQuoteId = (int)$pdfFailureInquiry['quoteid'];
$draftQuote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$draftQuoteId]);
checkInquiryQuote($pdfFailed && $draftQuoteId > 0 && empty($draftQuote['quoteid']) && empty($draftQuote['completed']), 'PDF failure did not preserve a retryable linked quote draft');
checkInquiryQuote((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1002, 'PDF failure consumed a quote number');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM documentarchive WHERE documentid = ?', [$draftQuoteId]) === 0, 'PDF failure registered an archive');
$retriedQuoteId = $controller->createQuote($pdfFailureData, 5);
$retriedQuote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$retriedQuoteId]);
checkInquiryQuote($retriedQuoteId === $draftQuoteId && (int)$retriedQuote['quoteid'] === 1002 && (int)$retriedQuote['completed'] === 1, 'PDF retry created another quote or did not finalize the original draft');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100 AND contactid = 502') === 1, 'PDF retry created another contact');

$archiveFailureData = array_replace($inquiryData, ['email' => 'archive-failure@example.test']);
$adapter->insert('shopinquirydata', [
	'id' => 6, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('f', 32), 'data' => json_encode($archiveFailureData),
	'status' => 'new', 'clientid' => 100,
]);
$adapter->query("CREATE TRIGGER reject_inquiry_archive BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture archive failure'");
$archiveFailed = false;
try {
	$controller->createQuote($archiveFailureData, 6);
} catch (Throwable $e) {
	$archiveFailed = str_contains($e->getMessage(), 'fixture archive failure');
}
$archiveFailureInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 6');
$archiveDraftId = (int)$archiveFailureInquiry['quoteid'];
$archiveDraft = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$archiveDraftId]);
$archivePath = (new DEEC_DocumentArchive(BASE_PATH))->path(100, 'sales', 'quote', $archiveDraftId, 'pdf');
checkInquiryQuote($archiveFailed && $archiveDraftId > 0 && empty($archiveDraft['quoteid']) && !is_file($archivePath), 'Archive registration failure left a finalized quote or unregistered PDF');
checkInquiryQuote((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1003, 'Archive registration failure consumed a quote number');
$adapter->query('DROP TRIGGER reject_inquiry_archive');
$archiveRetryId = $controller->createQuote($archiveFailureData, 6);
checkInquiryQuote($archiveRetryId === $archiveDraftId && is_file($archivePath), 'Archive failure retry did not reuse and finalize the linked quote draft');

$collisionData = array_replace($inquiryData, ['email' => 'archive-collision@example.test']);
$adapter->insert('shopinquirydata', [
	'id' => 7, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('7', 32), 'data' => json_encode($collisionData),
	'status' => 'new', 'clientid' => 100,
]);
$controller->failOriginalPdf = true;
try {
	$controller->createQuote($collisionData, 7);
} catch (Throwable $e) {
}
$controller->failOriginalPdf = false;
$collisionInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 7');
$collisionDraftId = (int)$collisionInquiry['quoteid'];
$collisionPath = (new DEEC_DocumentArchive(BASE_PATH))->path(100, 'sales', 'quote', $collisionDraftId, 'pdf');
if (!is_dir(dirname($collisionPath)) && !mkdir(dirname($collisionPath), 0775, true) && !is_dir(dirname($collisionPath))) throw new RuntimeException('Could not create collision fixture directory');
if (!copy($pdf['path'], $collisionPath)) throw new RuntimeException('Could not create archive collision fixture');
$collisionHash = hash_file('sha256', $collisionPath);
$collisionBlocked = false;
try {
	$controller->createQuote($collisionData, 7);
} catch (Throwable $e) {
	$collisionBlocked = str_contains($e->getMessage(), 'already occupied');
}
$collisionDraft = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$collisionDraftId]);
checkInquiryQuote($collisionBlocked && empty($collisionDraft['quoteid']) && hash_equals($collisionHash, hash_file('sha256', $collisionPath)), 'Archive collision was overwritten or finalized');
checkInquiryQuote((int)$adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === 1004, 'Archive collision consumed a quote number');
unlink($collisionPath);
checkInquiryQuote($controller->createQuote($collisionData, 7) === $collisionDraftId, 'Collision recovery did not reuse the linked quote draft');

$failedData = array_replace($inquiryData, ['email' => 'new-buyer@example.test']);
$adapter->insert('shopinquirydata', [
	'id' => 8, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('8', 32), 'data' => json_encode($failedData),
	'status' => 'new', 'clientid' => 100,
]);
$beforeFailure = [
	'quotes' => (int)$adapter->fetchOne('SELECT COUNT(*) FROM quote WHERE clientid = 100'),
	'positions' => (int)$adapter->fetchOne('SELECT COUNT(*) FROM quotepos WHERE clientid = 100'),
	'contacts' => (int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100'),
	'emails' => (int)$adapter->fetchOne('SELECT COUNT(*) FROM email WHERE clientid = 100'),
];
$adapter->query("CREATE TRIGGER reject_inquiry_quote_position BEFORE INSERT ON quotepos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture position failure'");
$failed = false;
try {
	$controller->createQuote($failedData, 8);
} catch (Throwable $e) {
	$failed = true;
}
checkInquiryQuote($failed, 'Quote position failure did not reach the transaction');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote WHERE clientid = 100') === $beforeFailure['quotes'] && (int)$adapter->fetchOne('SELECT COUNT(*) FROM quotepos WHERE clientid = 100') === $beforeFailure['positions'], 'Failed quote left partial document data');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM contact WHERE clientid = 100') === $beforeFailure['contacts'] && (int)$adapter->fetchOne('SELECT COUNT(*) FROM email WHERE clientid = 100') === $beforeFailure['emails'], 'Failed quote left a partial contact');
$increments = $adapter->fetchRow('SELECT contactid, quoteid FROM increment WHERE clientid = 100');
checkInquiryQuote((int)$increments['contactid'] === 505 && (int)$increments['quoteid'] === 1005, 'Failed quote consumed a contact or document number');
$failedInquiry = $adapter->fetchRow('SELECT * FROM shopinquirydata WHERE id = 8');
checkInquiryQuote($failedInquiry['quoteid'] === null && $failedInquiry['contactid'] === null, 'Failed quote partially linked the inquiry');

echo "PASS: $checks guest quote transaction, calculation, privacy, PDF and replay cases\n";
