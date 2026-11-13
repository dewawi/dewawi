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
		$this->formConfig = [1 => [['name' => 'kuehlleistung', 'type' => 'number', 'calculate' => ['target' => 'vorlauf_temp / umgebung_temp_max', 'reference' => 'referenzleistung', 'tolerance' => 0.1]]]];
		$formRow['fields'] = json_encode(array_merge($this->formConfig, ['_settings' => ['selection' => ['method' => 'form_calc', 'automatic_quote' => true], 'quote' => ['tax_country' => 'DE', 'pdf' => ['cover' => false]]]]));
		$this->formRow = $formRow;
		$this->formDataSession = new stdClass();
		$this->formDataSession->suggestions = $suggestions;
	}

	public function configure(array $settings, ?array $fields = null, ?string $header = null, ?string $footer = null): void
	{
		if($fields !== null) $this->formConfig = Shops_Model_FormConfig::normalize($fields);
		$this->formRow['fields'] = json_encode(array_merge($this->formConfig, ['_settings' => $settings]));
		if($header !== null) $this->formRow['quoteheader'] = $header;
		if($footer !== null) $this->formRow['quotefooter'] = $footer;
	}

	public function preparePost(): void
	{
		$this->_site = ['id' => 120, 'clientid' => 100];
		$this->formId = 865;
		$this->csrfToken = 'csrf-value';
		$this->inquiryToken = str_repeat('9', 32);
		$this->formDataSession->formData = [];
		$this->formDataSession->inquiryToken = $this->inquiryToken;
		$this->formDataSession->lastInquiryToken = '';
	}

	protected function consumeSubmissionLimit(string $operation, int $limit, int $window): array { return ['allowed' => true]; }
	protected function sendInquiryNotification(array $data): bool { return false; }
	protected function sendOfferNotification(array $data, string $pdfPath): bool { throw new RuntimeException('Unexpected offer email'); }

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
foreach (['address', 'category', 'contact', 'country', 'currency', 'documentarchive', 'email', 'filename', 'footer', 'increment', 'item', 'itematr', 'itematrset', 'itemopt', 'itemoptset', 'itemvariantopt', 'media', 'language', 'pricerulepos', 'quote', 'quotepos', 'shopinquirydata', 'shopinquiryform', 'taxrate', 'template', 'uom'] as $table) {
	$adapter->query(inquiryQuoteSchema($structure, $table));
}

$adapter->insert('increment', [
	'clientid' => 100, 'contactid' => 501, 'creditnoteid' => 1, 'deliveryorderid' => 1, 'invoiceid' => 1,
	'purchaseorderid' => 1, 'quoteid' => 1000, 'quoterequestid' => 1, 'reminderid' => 1, 'salesorderid' => 1, 'shoporderid' => 1,
]);
$adapter->insert('category', ['id' => 20, 'title' => 'Chillers', 'type' => 'shop', 'parentid' => 0, 'ordering' => 1, 'activated' => 1, 'shopid' => 120, 'clientid' => 100]);
$adapter->insert('item', [
	'id' => 10, 'catid' => 0, 'shopcatid' => 20, 'sku' => 'ZCE110', 'title' => 'ZCE 110', 'description' => 'Cooling unit',
	'type' => 'default', 'currency' => 'EUR', 'price' => 1000, 'specialprice' => 900, 'taxid' => 7, 'uomid' => 3,
	'shopenabled' => 1, 'shopid' => 120, 'clientid' => 100,
]);
$adapter->insert('itematr', ['parentid' => 0, 'atrsetid' => 0, 'sku' => 'FORM_CALC', 'title' => 'kuehlleistung_20 / 30', 'value' => '1.41', 'clientid' => 100]);
$adapter->insert('itematr', ['parentid' => 0, 'atrsetid' => 0, 'sku' => 'FORM_CALC', 'title' => 'referenzleistung', 'value' => '7', 'description' => 'ZCE110', 'clientid' => 100]);
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
	'sku' => 'ZCE110', 'kuehlleistung' => '9.8', 'vorlauf_temp' => '20', 'umgebung_temp_max' => '30', 'quantity' => '2', 'firma' => 'Submitted GmbH', 'ansprechpartner' => 'Ada Submitted',
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
$translator = new DEEC_Translate('de_DE');
$translator->loadDir('default', $root.'/languages/de_DE/default');
$translator->loadDir('sales', $root.'/languages/de_DE/sales');
Zend_Registry::set('DEEC_Translate', $translator);
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


$adapter->query('DROP TRIGGER reject_inquiry_quote_position');
$adapter->insert('itematrset', ['id' => 1, 'parentid' => 10, 'title' => 'Technical fixture', 'clientid' => 100]);
$adapter->insert('itematr', ['parentid' => 10, 'atrsetid' => 1, 'sku' => 'ZCE110', 'title' => 'Voltage fixture', 'description' => '400 V fixture', 'clientid' => 100]);
$adapter->insert('itematr', ['parentid' => 10, 'atrsetid' => 0, 'sku' => 'ZCE110', 'title' => 'Private foreign attribute', 'description' => 'FOREIGN-ATTRIBUTE-MARKER', 'clientid' => 200]);
$adapter->insert('itemoptset', ['id' => 1, 'parentid' => 10, 'title' => 'Accessories', 'clientid' => 100]);
foreach([
	['id' => 1, 'sku' => 'SELECTED-PAID', 'title' => 'Selected paid accessory', 'price' => 150],
	['id' => 2, 'sku' => 'SELECTED-KIT', 'title' => 'Selected included kit', 'price' => 0],
	['id' => 3, 'sku' => 'UNSELECTED-OPTION', 'title' => 'Unselected accessory', 'price' => 999],
] as $option) $adapter->insert('itemopt', array_merge(['parentid' => 10, 'optsetid' => 1, 'quantity' => 1, 'taxrate' => 7, 'currency' => 'EUR', 'clientid' => 100], $option));
$imageDir = BASE_PATH.'/media/1/0/100/images';
mkdir($imageDir, 0775, true);
$image = imagecreatetruecolor(320, 180);
imagefill($image, 0, 0, imagecolorallocate($image, 30, 100, 170));
imagestring($image, 5, 15, 70, 'PUBLIC ITEM IMAGE', imagecolorallocate($image, 255, 255, 255));
imagepng($image, $imageDir.'/fixture.png');
imagedestroy($image);
$adapter->insert('media', ['parentid' => 10, 'module' => 'items', 'controller' => 'item', 'type' => 'image', 'url' => 'fixture.png', 'title' => 'Public image fixture', 'clientid' => 100]);
$adapter->update('item', ['shopdescription' => '<p>Public product description fixture</p>'], 'id = 10');
$fullSettings = ['selection' => ['method' => 'form_calc', 'automatic_quote' => true, 'option_fields' => [
	'accessory' => ['yes' => ['itemoptid' => 1, 'selection_verified' => true]],
	'kit' => ['yes' => ['itemoptid' => 2, 'selection_verified' => true]],
]], 'quote' => ['tax_country' => 'DE', 'pdf' => ['cover' => true, 'attributes' => true, 'options' => true, 'images' => true, 'positionimages' => true]]];
$fullFields = [1 => [
	['name' => 'kuehlleistung', 'type' => 'number', 'calculate' => ['target' => 'vorlauf_temp / umgebung_temp_max', 'reference' => 'referenzleistung', 'tolerance' => 0.1]],
	['name' => 'accessory', 'type' => 'select', 'options' => ['yes' => 'Accessory']],
	['name' => 'kit', 'type' => 'select', 'options' => ['yes' => 'Included kit']],
]];
$controller->configure($fullSettings, $fullFields, '<p>HEADER-BEGIN '.str_repeat('Configured header text. ', 130).' HEADER-END</p>', '<p>FOOTER-BEGIN '.str_repeat('Configured long footer text. ', 550).' FOOTER-END</p>');
$fullData = array_replace($inquiryData, ['accessory' => 'yes', 'kit' => 'yes']);
$fullInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('a9', 16), $fullData);
$fullQuoteId = $controller->createQuote($fullData, $fullInquiryId);
checkInquiryQuote($fullQuoteId > 0, 'Complete configuration did not create a quote');
$fullQuote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$fullQuoteId]);
checkInquiryQuote(abs((float)$fullQuote['subtotal'] - 2100) < 0.001 && abs((float)$fullQuote['total'] - 2247) < 0.001, 'Selected option pricing differs from standard calculation');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quotepos WHERE parentid = ?', [$fullQuoteId]) === 3, 'Selected free option was lost or an unselected option was charged');
$fullPdf = $controller->createPdf($fullQuoteId);
$text = shell_exec('pdftotext -layout '.escapeshellarg($fullPdf['path']).' -');
foreach(['HEADER-BEGIN', 'HEADER-END', 'FOOTER-BEGIN', 'FOOTER-END', 'Voltage fixture', '400 V fixture', 'SELECTED-PAID', 'SELECTED-KIT', 'Public product description fixture', 'Public image fixture'] as $marker) {
	checkInquiryQuote(str_contains($text, $marker), 'Rendered full PDF misses '.$marker);
}
foreach(['UNSELECTED-OPTION', 'FOREIGN-ATTRIBUTE-MARKER', 'Private Master Name', 'Private Master Street'] as $private) checkInquiryQuote(!str_contains($text, $private), 'PDF includes unselected or private data: '.$private);
$images = shell_exec('pdfimages -f 1 -l 1 -list '.escapeshellarg($fullPdf['path']));
checkInquiryQuote(str_contains($images, '320') && str_contains($images, '180'), 'Cover image was not embedded');
$info = shell_exec('pdfinfo '.escapeshellarg($fullPdf['path']));
preg_match('/Pages:\s+(\d+)/', $info, $pageCount);
checkInquiryQuote((int)($pageCount[1] ?? 0) >= 5, 'Cover and long terms did not produce a complete multipage PDF');
$bbox = BASE_PATH.'/full-layout.html';
shell_exec('pdftotext -bbox '.escapeshellarg($fullPdf['path']).' '.escapeshellarg($bbox));
$layout = simplexml_load_file($bbox);
$layout->registerXPathNamespace('h', 'http://www.w3.org/1999/xhtml');
foreach($layout->xpath('//h:word[starts-with(text(), "Configured")]') as $word) checkInquiryQuote((float)$word['yMax'] < 765, 'Header/footer text overlaps the page footer');
$keptHash = hash_file('sha256', $fullPdf['path']);
$adapter->update('itematr', ['description' => 'CHANGED-AFTER-ARCHIVE'], "title = 'Voltage fixture'");
checkInquiryQuote(hash_equals($keptHash, hash_file('sha256', $controller->createPdf($fullQuoteId)['path'])), 'Archived full quote was regenerated from changed product data');

// A variant already includes its saved option prices; no second surcharge is permitted.
$adapter->update('item', ['parentid' => 11, 'price' => 1200, 'specialprice' => null], 'id = 10');
$adapter->insert('item', ['id' => 11, 'catid' => 0, 'shopid' => 120, 'shopcatid' => 20, 'sku' => 'PARENT', 'title' => 'Variant parent', 'clientid' => 100]);
$adapter->update('itemopt', ['parentid' => 11], 'id IN (1, 2)');
$adapter->update('itemoptset', ['parentid' => 11], 'id = 1');
$adapter->insert('itemvariantopt', ['itemid' => 10, 'itemoptid' => 1, 'clientid' => 100]);
$variantInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('b9', 16), $fullData);
$variantQuoteId = $controller->createQuote($fullData, $variantInquiryId);
checkInquiryQuote(abs((float)$adapter->fetchOne('SELECT subtotal FROM quote WHERE id = ?', [$variantQuoteId]) - 2400) < 0.001, 'Variant option was charged twice');
$adapter->update('item', ['parentid' => null, 'price' => 1000, 'specialprice' => 900], 'id = 10');
$adapter->update('itemopt', ['parentid' => 10], 'id IN (1, 2)');
$adapter->update('itemoptset', ['parentid' => 10], 'id = 1');


// Display switches suppress optional sections, never purchased position prices.
$hiddenSettings = $fullSettings;
$hiddenSettings['quote']['pdf'] = ['cover' => false, 'attributes' => false, 'options' => false, 'images' => false, 'positionimages' => false];
$controller->configure($hiddenSettings, $fullFields, 'Short header', 'Short footer');
$hiddenInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('c9', 16), $fullData);
$hiddenQuoteId = $controller->createQuote($fullData, $hiddenInquiryId);
$hiddenPdf = $controller->createPdf($hiddenQuoteId);
$hiddenText = shell_exec('pdftotext -layout '.escapeshellarg($hiddenPdf['path']).' -');
checkInquiryQuote(!str_contains($hiddenText, 'CHANGED-AFTER-ARCHIVE') && !str_contains($hiddenText, 'Public image fixture') && !str_contains($hiddenText, 'Inhaltsverzeichnis'), 'Disabled sections were rendered');
checkInquiryQuote(str_contains($hiddenText, 'SELECTED-PAID') && str_contains($hiddenText, 'SELECTED-KIT'), 'Purchased options disappeared with the catalog-option switch');
$controller->configure($fullSettings, $fullFields);
$adapter->insert('itemopt', ['id' => 4, 'parentid' => 10, 'optsetid' => 1, 'sku' => 'FOREIGN-OPTION', 'price' => 10, 'clientid' => 200]);
$adapter->insert('itemopt', ['id' => 5, 'parentid' => 11, 'optsetid' => 1, 'sku' => 'WRONG-PARENT', 'price' => 10, 'clientid' => 100]);
$adapter->insert('itemopt', ['id' => 6, 'parentid' => 10, 'optsetid' => 1, 'sku' => 'ON-REQUEST', 'price' => -1, 'clientid' => 100]);
$adapter->insert('itemopt', ['id' => 7, 'parentid' => 10, 'optsetid' => 1, 'sku' => 'UNAVAILABLE', 'price' => -2, 'clientid' => 100]);
$adapter->insert('itemopt', ['id' => 8, 'parentid' => 10, 'optsetid' => 999, 'sku' => 'MISSING-GROUP', 'price' => 10, 'clientid' => 100]);
foreach([4, 5, 6, 7, 8] as $invalidOption) {
	try {
		(new Shops_Service_InquirySelection())->resolveOptions((new Shops_Model_DbTable_Item())->getItem(10), [$invalidOption], 'EUR');
		throw new RuntimeException('Unverified option accepted');
	} catch(UnexpectedValueException $e) { checkInquiryQuote(true, 'Rejected option'); }
}
foreach([['price' => null, 'specialprice' => null], ['currency' => 'USD']] as $index => $invalidPrice) {
	$before = (int)$adapter->fetchOne('SELECT COUNT(*) FROM quote');
	$adapter->update('item', $invalidPrice, 'id = 10');
	$invalidInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat($index ? 'd9' : 'e9', 16), $fullData);
	try {
		$controller->createQuote($fullData, $invalidInquiryId);
		throw new RuntimeException('Unverified item price accepted');
	} catch(UnexpectedValueException $e) {
		checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === $before, 'Incomplete pricing created a quote');
	}
	$adapter->update('item', ['price' => 1000, 'specialprice' => 900, 'currency' => 'EUR'], 'id = 10');
}

// Persist a real-form-shaped incomplete inquiry through the controller, without sending email.
Zend_Controller_Action_HelperBroker::addPath($root.'/library/Zend/Layout/Controller/Action/Helper', 'Zend_Layout_Controller_Action_Helper');
Zend_Layout::startMvc(['layoutPath' => $root.'/application/layouts/scripts']);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
Zend_Controller_Front::getInstance()->getRouter()->addRoute('inquiry_success', new Zend_Controller_Router_Route_Static('inquiry/success', ['module' => 'shops', 'controller' => 'inquiry', 'action' => 'success']));
$reference = is_file('/tmp/dewawi-inquiry-referenzen/form-865.fields.json') ? json_decode(file_get_contents('/tmp/dewawi-inquiry-referenzen/form-865.fields.json'), true) : [1 => [
	['name' => 'sku', 'type' => 'text'], ['name' => 'email', 'type' => 'email', 'required' => true], ['name' => 'privacy', 'type' => 'checkbox', 'required' => true],
	['name' => 'medium', 'type' => 'select', 'options' => ['Wasser', 'Wasser/Glykol']],
]];
$requestFields = array_merge(...array_values($reference));
$post = array_replace($inquiryData, ['step' => 'request', 'csrf_token' => 'csrf-value', 'inquiry_token' => str_repeat('9', 32), 'einsatzbereich' => '0', 'medium' => '1', 'glykol_typ' => '1', 'glykol_anteil' => '30', 'pumpe' => '0', 'geraeuschreduktion' => '1', 'titel' => 'Requested device', 'privacy' => '1']);
unset($post['umgebung_temp_max']);
$postRequest = new Zend_Controller_Request_HttpTestCase();
$postRequest->setModuleName('shops')->setControllerName('inquiry')->setActionName('index')->setMethod('POST')->setPost($post);
$postResponse = new Zend_Controller_Response_HttpTestCase();
Zend_Controller_Front::getInstance()->setResponse($postResponse);
$postController = new ShopInquiryQuoteController($postRequest, $postResponse);
$postController->setup($context, ['id' => 865, 'quotetemplateid' => 100], []);
$postController->configure([], ['request' => $requestFields]);
$postController->preparePost();
$quotesBefore = (int)$adapter->fetchOne('SELECT COUNT(*) FROM quote');
$postController->indexAction();
$storedManual = (new Shops_Model_DbTable_Inquirydata())->getByToken(str_repeat('9', 32));
checkInquiryQuote($storedManual !== null && empty($storedManual['quoteid']), 'Incomplete request was lost or automatically offered');
$storedManualData = json_decode($storedManual['data'], true);
checkInquiryQuote(empty($storedManualData['_selection']['eligible']) && $storedManualData['glykol_anteil'] == 30 && empty($storedManualData['umgebung_temp_max']), 'Manual review lost requirements or invented ambient temperature');
checkInquiryQuote((int)$adapter->fetchOne('SELECT COUNT(*) FROM quote') === $quotesBefore, 'Incomplete request consumed a quote');
checkInquiryQuote($postController->restore($storedManual)['status'] === 'manual_review', 'Manual review status was lost on replay');


// Replaying a customer token must never finalize an internally linked manual draft.
$manualDraftData = array_merge($fullData, ['_selection' => ['method' => 'form_calc', 'eligible' => false, 'reasons' => ['commercial_review_required']]]);
$manualDraftInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('f9', 16), $manualDraftData);
$manualDraftId = (new Shops_Model_DbTable_Quote())->addQuote(['contactid' => 500, 'currency' => 'EUR', 'templateid' => 100, 'language' => 'de_DE', 'state' => 100]);
(new Shops_Model_DbTable_Inquirydata())->updateById($manualDraftInquiryId, ['quoteid' => $manualDraftId]);
$manualDraftInquiry = (new Shops_Model_DbTable_Inquirydata())->getById($manualDraftInquiryId);
$beforeNumber = $adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100');
checkInquiryQuote($controller->restore($manualDraftInquiry)['status'] === 'manual_review', 'Customer replay finalized a manual quote draft');
checkInquiryQuote(empty($adapter->fetchOne('SELECT quoteid FROM quote WHERE id = ?', [$manualDraftId])) && $adapter->fetchOne('SELECT quoteid FROM increment WHERE clientid = 100') === $beforeNumber, 'Manual draft replay consumed a document number');

$deliveryData = array_replace($fullData, ['lieferadresse_abweichend' => 'ja', 'lieferempfaenger' => 'Submitted Delivery GmbH', 'lieferstrasse' => 'Submitted Delivery Street', 'lieferplz' => '54321', 'lieferstadt' => 'Delivery City', 'lieferland' => 'DE']);
$deliveryInquiryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('a8', 16), $deliveryData);
$deliveryQuoteId = $controller->createQuote($deliveryData, $deliveryInquiryId);
$deliveryQuote = $adapter->fetchRow('SELECT * FROM quote WHERE id = ?', [$deliveryQuoteId]);
checkInquiryQuote($deliveryQuote['billingstreet'] === 'Submitted Street 1' && $deliveryQuote['shippingstreet'] === 'Submitted Delivery Street' && $deliveryQuote['shippingname1'] === 'Submitted Delivery GmbH', 'Separate delivery snapshot copied master data or billing address');
$foreignDelivery = array_replace($deliveryData, ['lieferland' => 'CH']);
$foreignDeliveryId = (new Shops_Model_DbTable_Inquirydata())->save(865, str_repeat('b8', 16), $foreignDelivery);
checkInquiryQuote($controller->createQuote($foreignDelivery, $foreignDeliveryId) === null, 'Different delivery tax context automatically offered');

// The Python runner continues with actual HTTP requests against this isolated database.
require __DIR__.'/fixtures/inquiry-pilot-config.php';
$pilotPath = '/tmp/dewawi-inquiry-pilot-865-2026-11-13/form-865.pilot.fields.json';
$pilotConfig = is_file($pilotPath) ? Shops_Model_FormConfig::decode(file_get_contents($pilotPath)) : inquiryPilotConfig();
$adapter->insert('shopinquiryform', ['id' => 865, 'shopid' => 120, 'clientid' => 100, 'title' => 'Synthetic pilot inquiry', 'fields' => json_encode($pilotConfig), 'language' => 'de_DE', 'quotetemplateid' => 100]);
$adapter->insert('shopinquiryform', ['id' => 866, 'shopid' => 121, 'clientid' => 100, 'title' => 'Other shop', 'fields' => json_encode(inquiryPilotConfig()), 'quotetemplateid' => 100]);
$adapter->insert('shopinquiryform', ['id' => 867, 'shopid' => 120, 'clientid' => 200, 'title' => 'Other tenant', 'fields' => json_encode(inquiryPilotConfig()), 'quotetemplateid' => 100]);
foreach(['DE' => 'Deutschland', 'CH' => 'Schweiz'] as $code => $name) $adapter->insert('country', ['code' => $code, 'name' => $name, 'language' => 'de_DE', 'clientid' => 100]);
$adapter->insert('country', ['code' => 'XX', 'name' => 'Foreign fixture country', 'language' => 'de_DE', 'clientid' => 200]);

if(getenv('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR')) {
	$artifactDir = getenv('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR');
	if(!str_starts_with($artifactDir, '/tmp/dewawi-')) throw new RuntimeException('Test artifacts must stay under /tmp/dewawi-*');
	if(!is_dir($artifactDir)) mkdir($artifactDir, 0775, true);
	copy($fullPdf['path'], $artifactDir.'/full-inquiry.pdf');
}
echo "PASS: $checks guest quote transaction, calculation, privacy, PDF and replay cases\n";
