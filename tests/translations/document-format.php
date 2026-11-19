<?php

// Real payload, positions, calculation and TCPDF; only data acquisition is synthetic.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['sales', 'purchases'], true)) exit(2);
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
$module = $argv[1];
define('BASE_PATH', sys_get_temp_dir() . '/dewawi-document-format-' . bin2hex(random_bytes(8)));
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function ($class) { if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path; });
require $root . '/application/controllers/helpers/Currency.php';
require $root . '/application/controllers/helpers/Calculate.php';
require $root . '/application/modules/' . $module . '/controllers/helpers/Positions.php';
require $root . '/application/modules/' . $module . '/services/DocumentPayloadService.php';
require $root . '/library/Tcpdf/config/tcpdf_config.php';
require $root . '/library/Tcpdf/tcpdf.php';
class Contacts_Model_DbTable_Contact { public function getContactWithID($id) { return ['name1' => 'Synthetic recipient']; } }
class Application_Model_DbTable_Template { public function getPrimaryTemplate() { return ['id' => 1]; } }
class Application_Model_DbTable_Footer { public function getFooters($id) { return []; } }
class Sales_Service_DocumentRevisionService { public function relation(...$args) { return null; } }
class TranslationFormatPriceRule extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'PriceRule'; }
    public function usePriceRulesOnPositions($positions, ...$args) { $prices = []; foreach ($positions as $p) $prices[$p->id] = $p->price; return ['calculated' => $prices]; }
}
class TranslationFormatPositions extends Application_Controller_Action_Helper_Positions
{
    public static array $rows;
    public function getName() { return 'Positions'; }
    protected function getDocumentDb(string $controller) { return new class { public function getPositions($id) { return new Zend_Db_Table_Rowset(['data' => TranslationFormatPositions::$rows]); } }; }
    public function getOptions($positions, $price, $currency, $positionTable) { return [[], []]; }
}
class_alias(ucfirst($module) . '_Service_DocumentPayloadService', 'TranslationFormatPayloadBase');
class TranslationFormatPayload extends TranslationFormatPayloadBase
{
    protected function getItemData($positions): array { return ['items' => [], 'categories' => [], 'media' => [], 'attributesByGroup' => []]; }
}
class TranslationFormatPdf extends DEEC_Pdf
{
    protected function resolveDocumentPayloadData(string $module, string $controller, array $document, array $options = []): array { return (new TranslationFormatPayload())->build($document, $controller, $options); }
    public function render(array $document, string $module, string $type): array {
        $payload = $this->buildDocumentPayload(['document' => $document, 'meta' => ['module' => $module, 'controller' => $type, 'templateid' => null]]);
        $pdf = (new ReflectionMethod(DEEC_Pdf::class, 'initPdf'))->invoke($this, 'Synthetic document', $payload['document'], []);
        $this->renderPayload($pdf, $payload);
        return [$payload, $pdf->Output('', 'S')];
    }
}
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Currency());
Zend_Controller_Action_HelperBroker::addHelper(new Application_Controller_Action_Helper_Calculate());
Zend_Controller_Action_HelperBroker::addHelper(new TranslationFormatPositions());
Zend_Controller_Action_HelperBroker::addHelper(new TranslationFormatPriceRule());
TranslationFormatPositions::$rows = [];
foreach ([[1, '1234.5', '1234.5678', '19.5'], [2, '2', '12.50', '7'], [3, '0.1234', '1.01', '0']] as [$id, $quantity, $price, $rate]) {
    TranslationFormatPositions::$rows[] = ['id' => $id, 'itemid' => 0, 'masterid' => 0, 'quantity' => $quantity, 'price' => $price, 'taxrate' => $rate, 'manufacturerid' => 0, 'total' => null, 'sku' => 'FIX-' . $id, 'title' => 'Synthetic item', 'description' => '', 'uom' => 'unit'];
}
$rowsBefore = TranslationFormatPositions::$rows;
$results = []; $numericBaseline = null;
foreach (['de_DE', 'en_US'] as $uiLocale) foreach (['de_DE', 'en_US'] as $documentLocale) foreach ($module === 'sales' ? ['quote', 'salesorder', 'invoice'] : ['quoterequest', 'purchaseorder'] as $type) {
    $ui = new DEEC_Translate($uiLocale); $ui->loadModule($module);
    Zend_Registry::set('DEEC_Translate', $ui);
    Zend_Registry::set('Zend_Locale', new Zend_Locale($uiLocale));
    $uiCurrency = new Zend_Currency('USD', $uiLocale); Zend_Registry::set('Zend_Currency', $uiCurrency);
    $currencyBefore = serialize($uiCurrency);
    $document = ['id' => 123, 'clientid' => 7, 'contactid' => 12, 'currency' => 'EUR', 'language' => $documentLocale, 'title' => 'Synthetic document', $type . 'id' => 123, $type . 'date' => '2026-11-19', 'billingname1' => 'Synthetic recipient', 'subtotal' => '1524099.07', 'taxes' => '297196.17', 'total' => '1821295.24', 'prepayment' => '1234.50', 'pdfshowcover' => 0, 'pdfshowoptions' => 0, 'pdfshowattributes' => 0, 'pdfshowimages' => 0];
    $before = $document;
    $numeric = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate($document, new Zend_Db_Table_Rowset(['data' => $rowsBefore]), $module, $type, null, $documentLocale)['row'];
    $numericBaseline ??= $numeric;
    [$payload, $bytes] = (new TranslationFormatPdf())->render($document, $module, $type);
    $pipes = []; $process = proc_open(['pdftotext', '-', '-'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fwrite($pipes[0], $bytes); fclose($pipes[0]); $text = stream_get_contents($pipes[1]); fclose($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($process);
    $flat = preg_replace('/\s+/u', '', $text);
    $de = $documentLocale === 'de_DE';
    $numbers = $de ? ['1.234,50', '0,1234', '1.234,5678', '1.524.099,07', '297.194,42', '1.821.295,24', '1.820.060,74', '19,50%', '7,00%'] : ['1,234.50', '0.1234', '1,234.5678', '1,524,099.07', '297,194.42', '1,821,295.24', '1,820,060.74', '19.50%', '7.00%'];
    $checks = ['readable_pdf' => $exit === 0 && str_starts_with($bytes, '%PDF-'), 'numeric_calculation_unchanged' => $numeric === $numericBaseline && $numeric['subtotal'] === 1524099.07 && $numeric['taxes']['total'] === 297196.17 && $numeric['total'] === 1821295.24,
        'aggregate_tax_formatted_in_payload' => str_contains($payload['document']['taxes'], $de ? '297.196,17' : '297,196.17'),
        'stored_inputs_unchanged' => $document === $before && TranslationFormatPositions::$rows === $rowsBefore,
        'ui_translator_and_locale_unchanged' => Zend_Registry::get('DEEC_Translate') === $ui && (string)Zend_Registry::get('Zend_Locale') === $uiLocale,
        'ui_currency_identity_and_options_unchanged' => Zend_Registry::get('Zend_Currency') === $uiCurrency && serialize($uiCurrency) === $currencyBefore];
    foreach ($numbers as $number) $checks['pdf_contains_' . $number] = str_contains($flat, $number);
    $results[] = ['module' => $module, 'type' => $type, 'ui_locale' => $uiLocale, 'document_locale' => $documentLocale, 'checks' => $checks, 'position_values' => $payload['positions']->toArray(), 'document_values' => $payload['document'], 'stderr' => $error];
}
$counts = ['passed' => 0, 'failed' => 0]; foreach ($results as $result) foreach ($result['checks'] as $ok) $counts[$ok ? 'passed' : 'failed']++;
echo json_encode(['scope' => 'actual payload/positions/calculation/TCPDF with synthetic data acquisition, no persistence', 'results' => $results, 'counts' => $counts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($counts['failed'] ? 1 : 0);
