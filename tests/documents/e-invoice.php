<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Reuse the real models/calculation helpers and isolated adapter; never access a real database.
ob_start();
require __DIR__ . '/position-read.php';
ob_end_clean();
$checks = 0;
$adapter->allowWrites = true;
$adapter->rows['permission'] = [['id' => 1, 'userid' => 42, 'deleted' => 0, 'sales' => '{"invoice":["view"]}', 'contacts' => '{"contact":["view"]}']];
$adapter->rows['client'] = [
    ['id' => 7, 'company' => 'Seller GmbH', 'address' => 'Seller Street 1', 'postcode' => '10115', 'city' => 'Berlin', 'country' => 'DE', 'email' => 'billing@seller.example', 'deleted' => 0, 'token' => 'DO-NOT-EXPORT'],
    ['id' => 8, 'company' => 'Other tenant', 'deleted' => 0],
];
$adapter->rows['bankaccount'] = [
    ['id' => 1, 'clientid' => 7, 'deleted' => 0, 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'currency' => 'EUR', 'title' => 'Explicitly selected seller account'],
    ['id' => 2, 'clientid' => 8, 'deleted' => 0, 'iban' => 'DE89370400440532013000'],
    ['id' => 3, 'clientid' => 7, 'deleted' => 1, 'iban' => 'DE89370400440532013000'],
];
$adapter->rows['invoice'] = [array_replace($parent, [
    'invoiceid' => 7300, 'invoicedate' => '2026-10-25', 'deliverydate' => '2026-10-24',
    'state' => 105, 'completed' => 1, 'contactid' => 12, 'notes' => 'INTERNAL-NOTE',
    'billingname1' => 'Buyer GmbH', 'billingname2' => 'Accounts', 'billingdepartment' => '',
    'billingstreet' => 'Buyer Street 2', 'billingpostcode' => '20095', 'billingcity' => 'Hamburg', 'billingcountry' => 'DE',
    'vatin' => '', 'prepayment' => '0.0000', 'paymentmethod' => 'Überweisung', 'filename' => 'original.pdf',
])];
$adapter->rows['invoicepos'] = [$position, $child];
$adapter->rows['pricerulepos'] = [array_replace($rule, ['controller' => 'invoicepos'])];
Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct(1, 'sales', 'invoice');
$adapter->allowWrites = false;
$fixture = $adapter->rows;
$export = [
    'seller' => ['vat_id' => 'DE123456789', 'contact_name' => 'Billing Team', 'contact_phone' => '+49 30 123456', 'endpoint' => 'billing@seller.example', 'endpoint_scheme' => 'EM'],
    'buyer' => ['endpoint' => 'billing@buyer.example', 'endpoint_scheme' => 'EM'],
    'buyer_reference' => 'BUYER-REFERENCE', 'business_process' => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
    'payment' => ['means_code' => '58', 'bank_account_id' => 1, 'due_date' => '2026-11-08'],
    'unit_codes' => ['Piece' => 'H87'],
];
$service = new Sales_Service_DocumentPayloadService();
function prepareInvoice(array $export, string $profile = 'en16931'): array
{
    global $adapter, $service;
    $before = $adapter->rows;
    $writes = $adapter->writes;
    $currency = Zend_Registry::get('Zend_Currency');
    $currencyState = serialize($currency);
    $result = $service->prepareEInvoice(1, $export, $profile);
    check($adapter->rows === $before && $adapter->writes === $writes, 'E-invoice preparation changed a document or master data');
    check(Zend_Registry::get('Zend_Currency') === $currency && serialize($currency) === $currencyState, 'E-invoice preparation changed the PDF currency context');
    return $result;
}
function invoiceHasError(array $result, string $field): bool
{
    return in_array($field, array_column($result['errors'], 'field'), true);
}

foreach (['en16931', 'xrechnung'] as $profile) {
    $result = prepareInvoice($export, $profile);
    check($result['valid'] && $result['xml_validation_required'], 'Complete invoice failed: ' . json_encode($result['errors']));
    check($result['data']['number'] === 7300 && $result['data']['issue_date'] === '2026-10-25', 'Invoice identity was not preserved');
    check($result['data']['totals']['total'] === $fixture['invoice'][0]['total'], 'Stored invoice amount was replaced');
    check(count($result['data']['lines']) === 2 && (float)$result['data']['lines'][0]['unit_net_price'] === 90.0, 'Discounted price did not use existing calculation');
    check($result['data']['lines'][1]['rules_inherited'] && (float)$result['data']['lines'][1]['unit_net_price'] === 18.0, 'Inherited discount was lost');
    check(!str_contains(json_encode($result['data']), 'INTERNAL-NOTE') && !str_contains(json_encode($result['data']), 'DO-NOT-EXPORT'), 'Internal notes or tenant token leaked');
}

// Snapshot buyer data must not be replaced by a changed live contact or caller-provided party.
$adapter->rows['contact'] = [['id' => 12, 'contactid' => 12, 'clientid' => 8, 'name1' => 'Foreign contact', 'deleted' => 0]];
$result = prepareInvoice(array_replace_recursive($export, ['buyer' => ['name' => 'Override', 'street' => 'Override']]));
check($result['data']['buyer']['name'] === 'Buyer GmbH' && $result['data']['buyer']['street'] === 'Buyer Street 2', 'Buyer snapshot was overridden');
$adapter->rows = $fixture;

foreach (['invoiceid' => null, 'invoicedate' => '2026-02-30', 'billingname1' => '', 'billingcountry' => 'ZZ', 'currency' => 'ZZZ', 'subtotal' => null, 'total' => 'not-a-number'] as $field => $value) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0][$field] = $value;
    check(!prepareInvoice($export)['valid'], 'Missing/invalid invoice field was accepted: ' . $field);
}
$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['billingcity'] = '';
check(invoiceHasError(prepareInvoice($export, 'xrechnung'), 'buyer.city'), 'Incomplete XRechnung buyer address was accepted');
check(prepareInvoice($export)['valid'], 'EN preflight invented a mandatory buyer city');
$adapter->rows = $fixture;
$adapter->rows['client'][0]['company'] = '';
check(invoiceHasError(prepareInvoice($export), 'seller.name'), 'Missing seller was accepted');
$adapter->rows = $fixture;
$adapter->rows['client'][0]['postcode'] = '';
check(invoiceHasError(prepareInvoice($export, 'xrechnung'), 'seller.postcode'), 'Incomplete XRechnung seller address was accepted');
$adapter->rows = $fixture;
$missingSeller = $export;
$missingSeller['seller'] = [];
check(invoiceHasError(prepareInvoice($missingSeller), 'seller.tax_id'), 'Missing issuer tax identification was accepted');
$missingSeller['seller'] = ['tax_id' => '12/345/67890', 'identifier' => 'SUPPLIER-123'];
check(prepareInvoice($missingSeller)['valid'], 'Tax registration plus seller identifier incorrectly required a VAT ID');
$missingSeller['seller'] = ['tax_id' => '12/345/67890'];
check(invoiceHasError(prepareInvoice($missingSeller), 'seller.identifier'), 'Tax registration was incorrectly used as supplier identifier');

// Existing calculation supplies mixed tax groups; stored differences must never be repaired.
$adapter->rows = $fixture;
$adapter->rows['invoicepos'][1]['taxrate'] = 7;
$adapter->rows['invoicepos'][1]['total'] = 57.78;
$adapter->rows['invoice'][0]['taxes'] = 37.98;
$adapter->rows['invoice'][0]['total'] = 271.98;
$result = prepareInvoice($export);
check($result['valid'] && array_column($result['data']['tax_breakdown'], 'rate') === [19.0, 7.0], 'Mixed tax rates were lost');
$adapter->rows['invoice'][0]['total'] = 999;
check(invoiceHasError(prepareInvoice($export), 'totals.total'), 'Historical mismatch was silently recalculated');

// Tax-free does not identify E, Z, reverse charge or an out-of-scope transaction by itself.
$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['taxfree'] = 1;
$adapter->rows['invoice'][0]['taxes'] = 0;
$adapter->rows['invoice'][0]['total'] = 234;
$adapter->rows['invoicepos'][0]['total'] = 180;
$adapter->rows['invoicepos'][1]['total'] = 54;
check(invoiceHasError(prepareInvoice($export), 'lines.10.tax_category'), 'Tax-free invoice silently guessed a tax category');
$exempt = $export + ['tax' => ['category' => 'E', 'exemption_reason' => 'Steuerbefreiung nach § 4 UStG']];
$result = prepareInvoice($exempt);
check($result['valid'] && $result['data']['tax_breakdown'][0]['tax'] === 0 && $result['data']['tax_breakdown'][0]['net'] === 234.0, 'Exempt invoice failed');
$exempt['tax']['exemption_reason'] = '';
check(invoiceHasError(prepareInvoice($exempt), 'lines.10.exemption_reason'), 'Exemption reason was not required');
check(prepareInvoice($export + ['tax' => ['category' => 'Z']])['valid'], 'Zero rating incorrectly required an exemption reason');
check(!prepareInvoice($export + ['tax' => ['category' => 'AE']])['valid'], 'Unsupported reverse charge was silently accepted');

// Foreign currency must not be converted or impose an arbitrary matching bank currency.
$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['currency'] = 'USD';
foreach ($adapter->rows['invoicepos'] as &$line) $line['currency'] = 'USD';
unset($line);
$result = prepareInvoice($export);
check($result['valid'] && $result['data']['currency'] === 'USD' && $result['data']['totals']['total'] === $fixture['invoice'][0]['total'], 'Foreign currency was converted or rejected');

$adapter->rows = $fixture;
foreach (['price' => -1, 'quantity' => null, 'taxrate' => -1, 'total' => 1, 'title' => '', 'currency' => 'USD', 'masterid' => 999] as $field => $value) {
    $adapter->rows = $fixture;
    $adapter->rows['invoicepos'][0][$field] = $value;
    check(!prepareInvoice($export)['valid'], 'Invalid line field was accepted: ' . $field);
}
$adapter->rows = $fixture;
$adapter->rows['invoicepos'] = [];
check(invoiceHasError(prepareInvoice($export), 'lines'), 'Empty invoice was accepted');
$adapter->rows = $fixture;
check(invoiceHasError(prepareInvoice(array_replace($export, ['unit_codes' => []])), 'lines.10.unit_code'), 'Missing unit mapping was accepted');

// Payment requirements depend on the amount due and selected means; BIC is not mandatory.
$cash = array_replace($export, ['payment' => ['means_code' => '10', 'terms' => 'Bereits bar bezahlt']]);
check(prepareInvoice($cash)['valid'], 'Cash payment incorrectly required a bank account');
check(invoiceHasError(prepareInvoice(array_replace($export, ['payment' => ['means_code' => '58']])), 'payment.account'), 'Transfer without account was accepted');
check(invoiceHasError(prepareInvoice(array_replace($export, ['payment' => ['means_code' => '10']])), 'payment.terms'), 'Positive balance without due date/terms was accepted');
check(invoiceHasError(prepareInvoice(array_replace($export, ['payment' => ['bank_account_id' => 1, 'terms' => '14 Tage netto']])), 'payment.means_code'), 'Payment instructions without a means code were accepted');
$adapter->rows['invoice'][0]['prepayment'] = 300;
$result = prepareInvoice(array_replace($export, ['payment' => []]));
check($result['valid'] && abs($result['data']['totals']['payable'] + 21.54) < .00001, 'Overpayment was clamped or invented mandatory terms');
$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['prepayment'] = 1.0001;
check(invoiceHasError(prepareInvoice($export), 'totals.prepayment'), 'Prepayment with excess decimal places was rounded silently');
$adapter->rows = $fixture;
$adapter->rows['bankaccount'][0]['iban'] = 'DE00000000000000000000';
check(invoiceHasError(prepareInvoice($export), 'payment.account.iban'), 'Invalid SEPA IBAN was accepted');
$adapter->rows = $fixture;
$adapter->rows['bankaccount'][0]['bic'] = '';
check(prepareInvoice($export)['valid'], 'Preflight invented mandatory BIC');
$adapter->rows = $fixture;
$incomplete = prepareInvoice([], 'xrechnung');
check(invoiceHasError($incomplete, 'buyer_reference') && invoiceHasError($incomplete, 'buyer.endpoint') && invoiceHasError($incomplete, 'seller.contact_name'), 'Missing XRechnung fields were not reported');
check(invoiceHasError(prepareInvoice($export + ['period_start' => '2026-10-25', 'period_end' => '2026-10-01']), 'period_end'), 'Invalid service period was accepted');

// Tenant and permission failures return no invoice or account data, including selected child tenants.
foreach (['clientid' => 8, 'deleted' => 1] as $field => $value) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0][$field] = $value;
    try { $service->prepareEInvoice(1, $export); throw new LogicException('Inaccessible invoice was prepared'); }
    catch (RuntimeException $expected) { check($expected->getMessage() === 'Invoice not found', 'Unexpected invoice access error'); }
}
foreach ([2, 3, 999] as $accountId) {
    $adapter->rows = $fixture;
    $selection = $export;
    $selection['payment']['bank_account_id'] = $accountId;
    try { $service->prepareEInvoice(1, $selection); throw new LogicException('Inaccessible bank account was read'); }
    catch (RuntimeException $expected) { check($expected->getMessage() === 'Bank account not found', 'Unexpected bank access error'); }
}
$adapter->rows = $fixture;
Zend_Registry::set('User', ['id' => 42, 'clientid' => 99]);
check(prepareInvoice($export)['data']['seller']['name'] === 'Seller GmbH', 'Home tenant replaced selected invoice issuer');
$adapter->rows['permission'][0]['contacts'] = '{}';
try { $service->prepareEInvoice(1, $export); throw new LogicException('Missing bank permission was accepted'); }
catch (RuntimeException $expected) { check($expected->getMessage() === 'Bank account access denied', 'Unexpected bank permission failure'); }
$adapter->rows = $fixture;
$adapter->rows['permission'][0]['sales'] = '{"invoice":["edit"]}';
try { $service->prepareEInvoice(1, $export); throw new LogicException('Missing view permission was accepted'); }
catch (RuntimeException $expected) { check($expected->getMessage() === 'Invoice access denied', 'Unexpected permission failure'); }
// XML generation reuses preparation and must leave the original invoice/PDF untouched.
$adapter->rows = $fixture;
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
$xmlService = new Sales_Service_XRechnungService();
$originalPdf = tempnam(sys_get_temp_dir(), 'dewawi-einvoice-original-');
file_put_contents($originalPdf, "%PDF-1.4\nUnchanged original\n%%EOF\n");
register_shutdown_function(static function () use ($originalPdf): void { unlink($originalPdf); });
$xmlDirectory = $argv[1] ?? null;
if ($xmlDirectory !== null) {
    if (!str_starts_with($xmlDirectory, '/tmp/dewawi-xrechnung-')) throw new InvalidArgumentException('Only temporary XML fixture directories are accepted');
    if (!is_dir($xmlDirectory)) mkdir($xmlDirectory, 0775, true);
}
function generateInvoice(array $export): array
{
    global $adapter, $xmlService, $originalPdf;
    $before = [$adapter->rows, $adapter->writes, hash_file('sha256', $originalPdf), filemtime($originalPdf)];
    $currency = serialize(Zend_Registry::get('Zend_Currency'));
    try { return $xmlService->generate(1, $export); }
    finally {
        check([$adapter->rows, $adapter->writes, hash_file('sha256', $originalPdf), filemtime($originalPdf)] === $before, 'XML export changed database records or original PDF');
        check(serialize(Zend_Registry::get('Zend_Currency')) === $currency, 'XML export changed PDF currency context');
    }
}
function invoiceXml(array $export, string $name): DOMXPath
{
    global $xmlDirectory;
    $result = generateInvoice($export);
    check($result['valid'] && is_string($result['xml']), 'XML generation failed: ' . json_encode($result['errors']));
    $document = new DOMDocument();
    check($document->loadXML($result['xml'], LIBXML_NONET), 'Generated XML is not well-formed');
    check($document->documentElement->namespaceURI === 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Wrong UBL root namespace');
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    check($xpath->evaluate('string(/*/cbc:CustomizationID)') === 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0', 'Wrong XRechnung customization');
    check($xpath->evaluate('string(/*/cbc:InvoiceTypeCode)') === '380', 'Wrong invoice type');
    check($xpath->evaluate('count(//cac:AllowanceCharge)') === 0.0, 'Already applied discounts were deducted a second time');
    if ($xmlDirectory !== null) file_put_contents($xmlDirectory . '/' . $name . '.xml', $result['xml']);
    return $xpath;
}

$xpath = invoiceXml($export, 'standard-s-58');
check($xpath->evaluate('count(/*/cac:InvoiceLine)') === 2.0, 'XML lost invoice lines');
check($xpath->evaluate('string(/*/cac:InvoiceLine[1]/cac:Price/cbc:PriceAmount)') === '90', 'XML changed the effective discounted price');
check($xpath->evaluate('string(/*/cac:InvoiceLine[2]/cac:Price/cbc:PriceAmount)') === '18', 'XML lost inherited discount');
check($xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)') === '278.46', 'XML changed gross amount');
check($xpath->evaluate('string(/*/cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode)') === 'H87', 'XML lost unit code');
check($xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID/@schemeID)') === 'EM', 'XML lost endpoint scheme');

// A single line is also a complete ordinary invoice.
$adapter->rows['invoicepos'] = [$fixture['invoicepos'][0]];
$adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], ['subtotal' => 180, 'taxes' => 34.20, 'total' => 214.20]);
$xpath = invoiceXml($export, 'single-s-58');
check($xpath->evaluate('count(/*/cac:InvoiceLine)') === 1.0, 'Single-line invoice failed');

$adapter->rows = $fixture;
$adapter->rows['invoicepos'][1]['taxrate'] = 7;
$adapter->rows['invoicepos'][1]['total'] = 57.78;
$adapter->rows['invoice'][0]['taxes'] = 37.98;
$adapter->rows['invoice'][0]['total'] = 271.98;
$adapter->rows['client'][0]['company'] = 'Müller & Söhne <GmbH>';
$adapter->rows['invoice'][0]['billingname1'] = 'Käufer & Partner';
$adapter->rows['invoicepos'][0]['title'] = 'Öl & Zubehör <Spezial>';
$adapter->rows['invoicepos'][0]['description'] = 'Größe "Ä" > 1';
$xpath = invoiceXml($export + ['period_start' => '2026-10-01', 'period_end' => '2026-10-24'], 'mixed-s-58');
check($xpath->evaluate('count(/*/cac:TaxTotal/cac:TaxSubtotal)') === 2.0, 'Mixed tax groups were merged');
check($xpath->evaluate('string(/*/cac:TaxTotal/cbc:TaxAmount)') === '37.98', 'Mixed VAT amount was changed');
check($xpath->evaluate('string(/*/cac:InvoiceLine[1]/cac:Item/cbc:Name)') === 'Öl & Zubehör <Spezial>', 'XML did not escape/restore special characters');
check($xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName)') === 'Müller & Söhne <GmbH>', 'XML lost seller umlauts');
check($xpath->evaluate('string(/*/cac:InvoicePeriod/cbc:StartDate)') === '2026-10-01', 'Service period was lost');

$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['prepayment'] = 100;
$transfer = $export;
$transfer['payment']['means_code'] = '30';
$transfer['payment']['terms'] = '14 Tage netto';
$xpath = invoiceXml($transfer, 'standard-s-30');
check($xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PrepaidAmount)') === '100.00' && $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)') === '178.46', 'Advance payment was changed');

$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['prepayment'] = 278.46;
$cash = array_replace($export, ['payment' => ['means_code' => '10', 'terms' => 'Bereits bar bezahlt']]);
$xpath = invoiceXml($cash, 'standard-s-10');
check($xpath->evaluate('count(//cac:PayeeFinancialAccount)') === 0.0 && $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)') === '0.00', 'Cash payment invented account or balance');

$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['prepayment'] = 300;
$xpath = invoiceXml($export, 'overpaid-s-58');
check($xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)') === '-21.54', 'XML clamped the signed payload balance');

// Effective prices can be much smaller than their stored list price after price rules.
$adapter->rows = $fixture;
$adapter->rows['invoicepos'] = [array_replace($position, ['price' => 0.0001, 'quantity' => 10000000, 'total' => 1.19])];
$adapter->rows['pricerulepos'][0]['amount'] = 99.9;
$adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], ['subtotal' => 1, 'taxes' => 0.19, 'total' => 1.19]);
$xpath = invoiceXml($export, 'fractional-price-s-58');
$serializedPrice = $xpath->evaluate('string(/*/cac:InvoiceLine/cac:Price/cbc:PriceAmount)');
check(!str_contains(strtolower($serializedPrice), 'e') && (float)$serializedPrice > 0 && (float)$serializedPrice < 0.0001, 'XML rounded a small effective price or used scientific notation');

$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['vatin'] = 'DE987654321';
$taxRegistration = $export;
$taxRegistration['seller'] = array_replace($taxRegistration['seller'], ['vat_id' => '', 'tax_id' => '12/345/67890', 'identifier' => 'SUPPLIER-123']);
$xpath = invoiceXml($taxRegistration, 'seller-tax-id');
check($xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cac:TaxScheme/cbc:ID)') === 'FC', 'Seller tax registration was incorrectly serialized as VAT ID');
check($xpath->evaluate('string(/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID)') === 'DE987654321', 'Buyer VAT identification was lost');

foreach (['Z', 'E'] as $category) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], ['taxfree' => 1, 'taxes' => 0, 'total' => 234]);
    $adapter->rows['invoicepos'][0]['total'] = 180;
    $adapter->rows['invoicepos'][1]['total'] = 54;
    $zero = $export + ['tax' => ['category' => $category, 'exemption_reason' => $category === 'E' ? 'Steuerbefreiung nach § 4 Nr. 14 UStG' : '']];
    $xpath = invoiceXml($zero, 'standard-' . strtolower($category) . '-58');
    check($xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID)') === $category, 'Zero VAT category was changed');
    check($xpath->evaluate('string(/*/cac:TaxTotal/cbc:TaxAmount)') === '0.00', 'Tax-free XML invented VAT');
    foreach (['30', '10'] as $code) {
        $zero['payment'] = $code === '10' ? ['means_code' => '10', 'terms' => 'Zahlung bei Erhalt'] : array_replace($export['payment'], ['means_code' => '30']);
        invoiceXml($zero, 'standard-' . strtolower($category) . '-' . $code);
    }
}

// FIN-03 errors remain unchanged and no XML is emitted for failed preparation.
$adapter->rows = $fixture;
$expected = $service->prepareEInvoice(1, [], 'xrechnung');
$result = generateInvoice([]);
check(!$result['valid'] && $result['xml'] === null && $result['errors'] === $expected['errors'], 'XML export replaced FIN-03 errors');
foreach ([['state' => 100, 'completed' => 0], ['completed' => 0], ['state' => 106, 'cancelled' => 1]] as $lifecycle) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], $lifecycle);
    $result = generateInvoice($export);
    check(!$result['valid'] && $result['xml'] === null && invoiceHasError($result, 'state'), 'Draft/incomplete/cancelled invoice was exported');
}
foreach (['AE', 'K', 'G', 'O'] as $category) {
    $adapter->rows = $fixture;
    $result = generateInvoice($export + ['tax' => ['category' => $category]]);
    check(!$result['valid'] && $result['xml'] === null, 'Unsupported tax case was exported');
}
foreach (['currency' => 'USD', 'billingcountry' => 'AT'] as $field => $value) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0][$field] = $value;
    if ($field === 'currency') foreach ($adapter->rows['invoicepos'] as &$line) $line['currency'] = $value;
    unset($line);
    $result = generateInvoice($export);
    check(!$result['valid'] && $result['xml'] === null, 'Unsupported export scope was accepted');
}
$adapter->rows = $fixture;
foreach ([['business_process' => 'unknown'], ['buyer' => ['endpoint_scheme' => '0088', 'endpoint' => '1234567890128']], ['seller' => ['vat_id' => 'DE123']], ['seller' => ['contact_phone' => 'abc']], ['payment' => ['terms' => '#SKONTO#INVALID#']]] as $metadata) {
    $result = generateInvoice(array_replace_recursive($export, $metadata));
    check(!$result['valid'] && $result['xml'] === null, 'Unsupported profile/address or invalid tax/contact data was exported');
}
$adapter->rows['bankaccount'][0]['bic'] = 'INVALID';
check(generateInvoice($export)['xml'] === null, 'Invalid BIC was emitted');
$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['total'] = 999;
check(generateInvoice($export)['xml'] === null, 'Contradictory totals were exported');

// FIN-03's historical half-cent tolerance is insufficient for serialized XML totals.
$adapter->rows = $fixture;
$adapter->rows['invoicepos'] = [array_replace($position, ['price' => 0.025, 'quantity' => 1, 'total' => 0.04])];
$adapter->rows['pricerulepos'] = [];
$adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], ['subtotal' => 0.03, 'taxes' => 0.01, 'total' => 0.04, 'prepayment' => 0.000001]);
check(generateInvoice($export)['xml'] === null, 'A non-cent prepayment was rounded silently');
$adapter->rows = $fixture;
$adapter->rows['invoicepos'][0]['title'] = "Invalid\x01text";
check(generateInvoice($export)['xml'] === null, 'XML 1.0 control character was emitted');

foreach (['clientid' => 8, 'deleted' => 1] as $field => $value) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0][$field] = $value;
    try { generateInvoice($export); throw new LogicException('Foreign/deleted XML was exported'); }
    catch (RuntimeException $expected) { check($expected->getMessage() === 'Invoice not found', 'Unexpected XML tenant failure'); }
}
$adapter->rows = $fixture;
$adapter->rows['permission'][0]['sales'] = '{}';
try { generateInvoice($export); throw new LogicException('XML without permission was exported'); }
catch (RuntimeException $expected) { check($expected->getMessage() === 'Invoice access denied', 'Unexpected XML permission failure'); }

// FIN-04B form/controller integration: flat form values are validated, mapped and downloaded without writes.
require_once $root . '/application/modules/sales/controllers/InvoiceController.php';
class XRechnungUiTestController extends Sales_InvoiceController
{
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->view = new Zend_View();
    }
}
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$translator = new DEEC_Translate('de_DE');
$translator->load('sales', $root . '/languages/de_DE/sales/invoice.php');
Zend_Registry::set('DEEC_Translate', $translator);

function xRechnungUiRequest(array $post, array $params = [], string $method = 'POST'): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('sales')->setControllerName('invoice')->setActionName('xrechnung')
        ->setParams(['module' => 'sales', 'controller' => 'invoice', 'action' => 'xrechnung', 'id' => 1] + $params)
        ->setPost($post);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    (new XRechnungUiTestController($request, $response))->xrechnungAction();
    $headers = array_column($response->getHeaders(), 'value', 'name');
    return [$response->getHttpResponseCode(), $response->getBody(), $headers];
}

$uiPost = [
    'seller_vat_id' => 'DE123456789', 'seller_tax_id' => '', 'seller_identifier' => '',
    'seller_contact_name' => 'Billing Team', 'seller_contact_phone' => '+49 30 123456',
    'seller_contact_email' => 'billing@seller.example', 'seller_endpoint' => 'billing@seller.example',
    'seller_endpoint_scheme' => 'EM', 'buyer_reference' => 'BUYER-REFERENCE',
    'buyer_endpoint' => 'billing@buyer.example', 'buyer_endpoint_scheme' => 'EM',
    'payment_means_code' => '58', 'bank_account_id' => '1', 'payment_due_date' => '08.11.2026',
    'payment_terms' => '', 'business_process' => Sales_Form_XRechnung::getBusinessProcess(),
    'period_start' => '', 'period_end' => '', 'unit_code_0' => 'H87',
    'line_tax_category_10' => 'S', 'line_exemption_reason_10' => '',
    'line_tax_category_11' => 'S', 'line_exemption_reason_11' => '',
];

$adapter->rows = $fixture;
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
check((new DEEC_Permission((array)Zend_Registry::get('User')))->getPermissionForAction('xrechnung') === 'view', 'XRechnung route does not require invoice view permission');
[$status, $body] = xRechnungUiRequest([], [], 'GET');
check($status === 405 && json_decode($body, true, 512, JSON_THROW_ON_ERROR)['valid'] === false, 'XRechnung action accepted a non-POST request');
$before = [$adapter->rows, $adapter->writes];
[$status, $body] = xRechnungUiRequest(array_diff_key($uiPost, ['buyer_reference' => true]));
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && !$json['valid'] && in_array('buyer_reference', array_column($json['errors'], 'field'), true), 'Required XRechnung form field was accepted or not associated');
[$status, $body] = xRechnungUiRequest(array_replace($uiPost, ['payment_means_code' => '99']));
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && !$json['valid'] && in_array('payment_means_code', array_column($json['errors'], 'field'), true), 'Unsupported payment means was accepted by the export form');
[$status, $body] = xRechnungUiRequest(array_replace($uiPost, ['line_tax_category_10' => 'AE']));
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && !$json['valid'] && in_array('line_tax_category_10', array_column($json['errors'], 'field'), true), 'Unsupported tax category was accepted by the export form');
[$status, $body] = xRechnungUiRequest(array_replace($uiPost, ['payment_due_date' => '31.02.2026']));
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && !$json['valid'] && in_array('payment_due_date', array_column($json['errors'], 'field'), true), 'Invalid localized due date was normalized into another date');
[$status, $body] = xRechnungUiRequest(array_replace($uiPost, ['seller_contact_phone' => 'x']));
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && !$json['valid'] && in_array('seller_contact_phone', array_column($json['errors'], 'field'), true), 'Service error was not associated with its export form field');
[$status, $body] = xRechnungUiRequest($uiPost);
$json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
check($status === 200 && $json['valid'] && $json['errors'] === [] && count($json['warnings']) === 2, 'Corrected XRechnung form did not pass the internal pre-check');
check(str_contains(implode(' ', array_column($json['warnings'], 'message')), 'KoSIT'), 'Successful pre-check was not distinguished from external KoSIT validation');
[$status, $xml, $headers] = xRechnungUiRequest($uiPost + ['download' => '1']);
check($status === 200 && str_starts_with($xml, '<?xml') && str_starts_with($headers['Content-Type'] ?? '', 'application/xml'), 'Validated XML was not returned directly');
check(str_contains($headers['Content-Disposition'] ?? '', 'attachment; filename="XRechnung-7300.xml"'), 'XML download filename or disposition is unsafe or missing');
$downloadDocument = new DOMDocument();
check($downloadDocument->loadXML($xml, LIBXML_NONET) && $downloadDocument->getElementsByTagNameNS('*', 'DueDate')->item(0)->textContent === '2026-11-08', 'Localized form date was not mapped explicitly to the export payload');
check([$adapter->rows, $adapter->writes] === $before, 'XRechnung UI requests changed invoice or master data');

foreach ([['state' => 100, 'completed' => 0], ['state' => 106, 'cancelled' => 1]] as $lifecycle) {
    $adapter->rows = $fixture;
    $adapter->rows['invoice'][0] = array_replace($fixture['invoice'][0], $lifecycle);
    [$status, $body] = xRechnungUiRequest($uiPost + ['download' => '1']);
    $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    check($status === 200 && !$json['valid'] && in_array('state', array_column($json['errors'], 'field'), true), 'Draft or cancelled invoice reached XML download');
}

$adapter->rows = $fixture;
$adapter->rows['invoice'][0]['clientid'] = 8;
[$status, $body] = xRechnungUiRequest($uiPost);
check($status === 404 && json_decode($body, true, 512, JSON_THROW_ON_ERROR)['valid'] === false, 'Foreign tenant invoice reached XRechnung generation');
$adapter->rows = $fixture;
$adapter->rows['permission'][0]['sales'] = '{}';
[$status, $body] = xRechnungUiRequest($uiPost);
check($status === 403 && json_decode($body, true, 512, JSON_THROW_ON_ERROR)['valid'] === false, 'Missing invoice view permission reached XRechnung generation');

echo 'PASS: ' . $checks . " e-invoice preparation and UBL XML checks; discounts, tax groups, escaping, lifecycle, permissions and unchanged originals\n";
