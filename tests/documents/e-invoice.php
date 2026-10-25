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
echo 'PASS: ' . $checks . " e-invoice data preflight checks; calculation reuse, tax/payment conditions, snapshots, tenant isolation and no writes\n";
