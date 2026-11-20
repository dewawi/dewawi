<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Pure fixtures: no bootstrap, database, session, HTTP or SMTP.
error_reporting(E_ALL);
set_error_handler(static function(int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
$root = dirname(__DIR__, 2);
require_once $root . '/application/modules/shops/models/ShoppingCart.php';
require_once $root . '/application/modules/shops/services/ShippingCalculator.php';
$checks = 0;
function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function scoped(array $row): array { return $row + ['clientid' => 7, 'shopid' => 41]; }

$config = ['version' => 1, 'clientid' => 7, 'shopid' => 41, 'currency' => 'EUR', 'minor_units' => 2, 'pickup' => false,
    'zones' => [scoped(['id' => 1, 'countries' => ['DE']]), scoped(['id' => 2, 'countries' => ['AT']])],
    'classes' => [], 'assignments' => [], 'tariffs' => [],
    'combinations' => [
        scoped(['id' => 1, 'hostclassid' => 20, 'guestclassid' => 10, 'priority' => 2, 'max_guest_packages' => 4, 'max_guest_weight' => '20', 'max_packlength' => '60']),
        scoped(['id' => 2, 'hostclassid' => 30, 'guestclassid' => 10, 'priority' => 1, 'max_guest_packages' => 4, 'max_guest_weight' => '20', 'max_packlength' => '60']),
    ]];
foreach ([10 => 'Parcel', 20 => 'Standard pallet', 30 => 'Large freight', 40 => 'Special transport', 50 => 'Separate transport', 60 => 'Weight', 70 => 'Value', 80 => 'Free'] as $id => $title) {
    $config['classes'][] = scoped(['id' => $id, 'title' => $title, 'combine_same_class' => $id !== 50, 'required_fields' => ['packweight', 'packlength', 'packwidth', 'packheight']]);
    $config['assignments'][] = scoped(['id' => $id, 'itemid' => 100 + intdiv($id, 10), 'classid' => $id, 'packages_per_unit' => 1, 'separate' => false]);
}
foreach ([
    ['classid' => 10, 'model' => 'fixed', 'amount' => '12.00'],
    ['classid' => 20, 'model' => 'additional_packages', 'base' => '120.00', 'additional' => '90.00'],
    ['classid' => 30, 'model' => 'fixed', 'amount' => '450.00'],
    ['classid' => 40, 'model' => 'manual'],
    ['classid' => 50, 'model' => 'fixed', 'amount' => '20.00'],
    ['classid' => 60, 'model' => 'weight', 'tiers' => [['up_to' => '5', 'amount' => '10.00'], ['up_to' => '10', 'amount' => '18.00']]],
    ['classid' => 70, 'model' => 'percentage', 'percent' => '2.5'],
    ['classid' => 80, 'model' => 'fixed', 'amount' => '0'],
] as $index => $tariff) $config['tariffs'][] = scoped($tariff + ['id' => $index + 1, 'zoneid' => 1]);
$items = [];
foreach ($config['assignments'] as $assignment) $items[] = scoped(['id' => $assignment['itemid'], 'price' => '100', 'specialprice' => '0', 'currency' => 'EUR', 'shopenabled' => 1, 'deleted' => 0,
    'weight' => '1', 'length' => '10', 'width' => '10', 'height' => '10', 'packweight' => '5', 'packlength' => '50', 'packwidth' => '20', 'packheight' => '20']);
$items[1]['packweight'] = '100'; $items[2]['packweight'] = '500';
$calculate = static function(array $lines, ?array $settings = null, ?array $catalog = null, array $destination = ['country' => 'DE'], array $context = ['clientid' => 7, 'shopid' => 41]) use ($config, $items): array {
    return (new Shops_Service_ShippingCalculator())->calculate($context, $settings ?? $config, $catalog ?? $items, $lines, $destination);
};
function line(int $id, $quantity = '1'): array { return ['itemid' => $id, 'quantity' => $quantity]; }
function outcome(array $result, string $status, ?string $net, string $message): void {
    check($result['status'] === $status && $result['net'] === $net, $message . ': ' . json_encode($result));
    check($status === 'calculated' || $result['net'] === null, 'Unknown cost was presented as a price');
}

// All amounts are synthetic fixtures, not merchant tariffs.
$a = $calculate([line(102), line(101)]);
outcome($a, 'calculated', '120.00', 'A: parcel did not ride with standard pallet');
check(count($a['groups']) === 1 && $a['groups'][0]['basis']['chargeable_packages'] === 1 && count($a['groups'][0]['combinations']) === 1, 'A: combination basis missing');
outcome($calculate([line(102, '2')]), 'calculated', '210.00', 'B: second pallet increment');
outcome($calculate([line(103), line(101)]), 'calculated', '450.00', 'C: parcel did not ride with large freight');
outcome($calculate([line(104)]), 'quote_required', null, 'D: manual freight');
outcome($calculate([line(102), line(103)]), 'calculated', '570.00', 'E: independent transports did not retain both tariffs');
outcome($calculate([line(101)]), 'calculated', '12.00', 'Single fixed tariff');
outcome($calculate([line(105, '2')]), 'calculated', '40.00', 'Separate units were merged');
$separate = $config; $separate['assignments'][0]['separate'] = true;
outcome($calculate([line(101, '2'), line(102)], $separate), 'calculated', '144.00', 'Item separation was ignored');
check($calculate([line(105, '100000')])['groups'][0]['shipments'] === 100000, 'Separate shipment multiplicity is not compact');
outcome($calculate([line(105, '.5')]), 'quote_required', null, 'Invalid fractional syntax');
outcome($calculate([line(105, '0.5')]), 'quote_required', null, 'Separate fractional shipment guessed');
$twoPackages = $config; $twoPackages['assignments'][1]['packages_per_unit'] = 2;
outcome($calculate([line(102)], $twoPackages), 'calculated', '210.00', 'Packages per sales unit ignored');
outcome($calculate([line(102, '0.5')], $twoPackages), 'calculated', '120.00', 'Known fractional sales unit packages');
outcome($calculate([line(102, '0.5')]), 'quote_required', null, 'Fractional package rounded silently');
outcome($calculate([line(101), line(101), line(102)]), 'calculated', '120.00', 'Repeated item not aggregated');
check($calculate([line(101), line(101), line(102)]) === $calculate([line(102), line(101, '2')]), 'Split lines changed reproducible result');
outcome($calculate([line(102, '3')]), 'calculated', '300.00', 'Third pallet');

outcome($calculate([line(106)]), 'calculated', '10.00', 'Weight tier inclusive boundary');
$heavier = $items; $heavier[5]['packweight'] = '5.0001';
outcome($calculate([line(106)], null, $heavier), 'calculated', '18.00', 'Weight beyond first tier');
outcome($calculate([line(106, '2')]), 'calculated', '18.00', 'Weight multiplication at upper boundary');
outcome($calculate([line(106, '2.0001')]), 'quote_required', null, 'Weight beyond tariff guessed');
$fraction = $items; $fraction[5]['packweight'] = '10.0001';
$result = $calculate([line(106, '0.0001')], (function() use ($twoPackages) { $c = $twoPackages; $c['assignments'][5]['packages_per_unit'] = 10000; return $c; })(), $fraction);
outcome($result, 'calculated', '10.00', 'Exact eight-decimal weight basis');
check($result['groups'][0]['basis']['weight_kg'] === '0.00100001', 'Weight precision was lost');
outcome($calculate([line(107)]), 'calculated', '2.50', 'Percentage tariff');
$rounding = $items; $rounding[6]['price'] = '0.20';
outcome($calculate([line(107)], null, $rounding), 'calculated', '0.01', 'Percentage half-cent rounding');
$rounding[6]['price'] = '0.1999';
outcome($calculate([line(107)], null, $rounding), 'calculated', '0.01', 'ERP-style line value rounds before percentage');
$rounding[6]['price'] = '0.1949';
outcome($calculate([line(107)], null, $rounding), 'calculated', '0.00', 'Percentage below half cent');
$special = $items; $special[6]['price'] = null; $special[6]['specialprice'] = '20';
outcome($calculate([line(107)], null, $special), 'calculated', '0.50', 'Positive special price not reused');
outcome($calculate([line(108)]), 'calculated', '0.00', 'Explicit free delivery');
$free = $config; $free['tariffs'][0]['amount'] = '0.00';
outcome($calculate([line(101), line(102)], $free), 'calculated', '120.00', 'Free guest tariff erased host charge');
$free['tariffs'][0]['amount'] = null;
outcome($calculate([line(101)], $free), 'quote_required', null, 'Null amount became free delivery');

$limits = $config; $limits['tariffs'][0] += ['min_weight' => '5', 'max_weight' => '10', 'min_packages' => 1, 'max_packages' => 2, 'min_value' => '100', 'max_value' => '200'];
outcome($calculate([line(101)], $limits), 'calculated', '12.00', 'Minimum boundaries');
outcome($calculate([line(101, '2')], $limits), 'calculated', '12.00', 'Maximum boundaries');
outcome($calculate([line(101, '3')], $limits), 'quote_required', null, 'Exceeded limits');
$limits['tariffs'][0]['min_packages'] = 2;
outcome($calculate([line(101)], $limits), 'quote_required', null, 'Below minimum packages');
$limits['tariffs'][0]['min_packages'] = 3;
outcome($calculate([line(101)], $limits), 'quote_required', null, 'Contradictory bounds');

outcome($calculate([line(101)], null, null, ['country' => 'US']), 'unavailable', null, 'Non-served country');
outcome($calculate([line(101)], null, null, ['country' => 'AT']), 'quote_required', null, 'Missing zone tariff');
outcome($calculate([line(101)], null, null, ['country' => 'DE', 'method' => 'pickup']), 'unavailable', null, 'Pickup not offered');
$pickup = $config; $pickup['pickup'] = true;
outcome($calculate([line(104)], $pickup, null, ['country' => 'US', 'method' => 'pickup']), 'calculated', '0.00', 'Explicit pickup should not require a delivery tariff');
$missing = $config; $missing['assignments'] = [];
outcome($calculate([line(101)], $missing), 'quote_required', null, 'Missing shipping class');
check($calculate([line(101)], $missing)['groups'][0]['basis']['net_value'] === null, 'Unresolved group basis became a zero value');
$manual = $config; $manual['tariffs'][0] = scoped(['id' => 1, 'classid' => 10, 'zoneid' => 1, 'model' => 'manual']);
outcome($calculate([line(101), line(102)], $manual), 'quote_required', null, 'Manual guest freight was absorbed');
$missing['tariffs'] = [];
outcome($calculate([line(101)], $missing), 'quote_required', null, 'No tariff must not mean free');
foreach (['packweight', 'packlength', 'packwidth', 'packheight'] as $field) {
    foreach ([null, '', '0'] as $value) {
        $incomplete = $items; $incomplete[0][$field] = $value;
        outcome($calculate([line(101), line(102)], null, $incomplete), 'quote_required', null, 'Missing packaging data: ' . $field);
    }
}
$optional = $config; $optional['classes'][0]['required_fields'] = [];
$incomplete = $items; $incomplete[0]['packweight'] = null;
outcome($calculate([line(101), line(102)], $optional, $incomplete), 'calculated', '132.00', 'Unknown guest weight allowed a combination');
$incomplete[0]['packweight'] = '5'; $incomplete[0]['packlength'] = null;
outcome($calculate([line(101), line(102)], $optional, $incomplete), 'calculated', '132.00', 'Unknown guest size allowed a bounded combination');
outcome($calculate([line(101, '5'), line(102)]), 'calculated', '132.00', 'Combination capacity exceeded');
$long = $items; $long[0]['packlength'] = '60.0001';
outcome($calculate([line(101), line(102)], null, $long), 'calculated', '132.00', 'Combination size limit exceeded');
$long[0]['packlength'] = '60';
outcome($calculate([line(101), line(102)], null, $long), 'calculated', '120.00', 'Combination inclusive size limit');
outcome($calculate([line(101), line(102), line(103)]), 'calculated', '570.00', 'Explicit host priority');
check($calculate([line(101), line(102), line(103)])['groups'][0]['classid'] === 20, 'Stable group ordering');
$noCombine = $config; $noCombine['combinations'] = [];
outcome($calculate([line(101), line(102)], $noCombine), 'calculated', '132.00', 'Combination assumed without rule');
$weightHost = $config; $weightHost['tariffs'][1] = scoped(['id' => 2, 'classid' => 20, 'zoneid' => 1, 'model' => 'weight', 'tiers' => [['up_to' => '100', 'amount' => '120'], ['up_to' => '105', 'amount' => '130']]]);
outcome($calculate([line(101), line(102)], $weightHost), 'calculated', '130.00', 'Absorbed parcel weight was excluded from host tariff');
$weightHost['tariffs'][1]['tiers'][1]['up_to'] = '104';
outcome($calculate([line(101), line(102)], $weightHost), 'calculated', '132.00', 'Impossible combined tariff did not fall back to independent transports');

// Adding another guest must preserve every earlier capacity declaration.
$multi = $config;
$multi['tariffs'][7]['amount'] = '7';
$multi['combinations'][0]['max_guest_packages'] = 1;
$multi['combinations'][] = scoped(['id' => 3, 'hostclassid' => 20, 'guestclassid' => 80, 'priority' => 3, 'max_guest_packages' => 4, 'max_guest_weight' => '20']);
outcome($calculate([line(101), line(102), line(108)], $multi), 'calculated', '127.00', 'Later guest bypassed earlier package capacity');
$multi['combinations'][0]['max_guest_packages'] = 4;
$multi['combinations'][0]['max_guest_weight'] = '5';
outcome($calculate([line(101), line(102), line(108)], $multi), 'calculated', '127.00', 'Later guest bypassed earlier weight capacity');
$multi['combinations'][0]['max_guest_weight'] = '20';
$oversize = $items; $oversize[7]['packlength'] = '100';
outcome($calculate([line(101), line(102), line(108)], $multi, $oversize), 'calculated', '127.00', 'Later guest bypassed earlier size capacity');
outcome($calculate([line(101), line(102), line(108)], $multi), 'calculated', '120.00', 'Two explicitly allowed guest classes');
$separateResult = $calculate([line(105, '3')]);
check($separateResult['groups'][0]['net'] === '60.00' && $separateResult['groups'][0]['net_per_shipment'] === '20.00' && $separateResult['groups'][0]['shipments'] === 3, 'Separate group price is not auditable');
$partial = $calculate([line(101), line(104)]);
outcome($partial, 'quote_required', null, 'A known partial amount became a final price');
check(count($partial['groups']) === 2 && $partial['groups'][0]['net'] === '12.00', 'Partial group evidence was lost');
$bad = $config; $bad['tariffs'][0]['max_weight'] = null;
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Unknown weight limit was treated as unlimited');
$bad = $config; $bad['tariffs'][0]['percent'] = '0';
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Conflicting tariff model fields');
$bad = $config; $bad['combinations'][0]['max_packlength'] = null;
outcome($calculate([line(101), line(102)], $bad), 'quote_required', null, 'Unknown combination limit was treated as unlimited');
$bad = $config; $bad['combinations'][1]['priority'] = 2;
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Ambiguous combination priority');
$bad = $config; $bad['assignments'][] = $bad['assignments'][0]; $bad['assignments'][8]['id'] = 99;
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Duplicate item assignment');
$badItems = $items; $badItems[] = $badItems[0];
outcome($calculate([line(101)], null, $badItems), 'quote_required', null, 'Duplicate item records');
outcome($calculate([]), 'quote_required', null, 'Empty selection became free shipping');
outcome($calculate([line(101)], null, null, ['country' => 'DE', 'postcode' => '12345']), 'quote_required', null, 'Unsupported postcode constraints were ignored');
foreach (['clientid', 'shopid'] as $field) outcome($calculate([line(101) + [$field => 7]]), 'quote_required', null, 'Client context injection accepted');

foreach (['0', '-1', '100001', '1e2', '1,5', '1.00001', INF, NAN, [], true, null] as $invalid) outcome($calculate([line(101, $invalid)]), 'quote_required', null, 'Invalid quantity');
outcome($calculate([line(101, '60000'), line(101, '60000')]), 'quote_required', null, 'Aggregated quantity limit');
foreach (['price', 'classid', 'packweight', 'tariffid'] as $injected) outcome($calculate([line(101) + [$injected => 0]]), 'quote_required', null, 'Untrusted selection field accepted');
foreach (['amount' => ['-1', '1e2', '1,5', '0.001', INF, NAN, [], true, '999999999999999999999999999999']] as $field => $invalids) foreach ($invalids as $invalid) {
    $bad = $config; $bad['tariffs'][0][$field] = $invalid;
    outcome($calculate([line(101)], $bad), 'quote_required', null, 'Invalid tariff amount');
}
foreach (['price', 'specialprice', 'packweight', 'packlength'] as $field) {
    $bad = $items; $bad[0][$field] = '-1';
    outcome($calculate([line(101)], null, $bad), 'quote_required', null, 'Invalid trusted item decimal');
}
foreach (['tiers' => [], 'model' => 'unknown', 'postcode_ranges' => ['123']] as $field => $invalid) {
    $bad = $config; $bad['tariffs'][5][$field] = $invalid;
    outcome($calculate([line(106)], $bad), 'quote_required', null, 'Unsupported tariff data');
}
$bad = $config; $bad['tariffs'][5]['tiers'][1]['up_to'] = '5';
outcome($calculate([line(106)], $bad), 'quote_required', null, 'Overlapping tiers');
$bad = $config; $bad['tariffs'][] = $bad['tariffs'][0]; $bad['tariffs'][8]['id'] = 99;
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Ambiguous tariff');
$bad = $config; $bad['zones'][1]['countries'] = ['DE'];
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Overlapping zones');
$bad = $config; $bad['classes'][0]['required_fields'] = [[]];
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Malformed required fields');
$bad = $config; $bad['combinations'][] = scoped(['id' => 3, 'hostclassid' => 10, 'guestclassid' => 20, 'priority' => 3, 'max_guest_weight' => '200', 'max_guest_packages' => 1]);
outcome($calculate([line(101), line(102)], $bad), 'quote_required', null, 'Cyclic combinations');
$bad['combinations'][2]['hostclassid'] = 40;
outcome($calculate([line(101), line(102)], $bad), 'quote_required', null, 'Transitive combinations');

foreach (['clientid' => 8, 'shopid' => 42] as $field => $foreign) {
    $bad = $config; $bad[$field] = $foreign;
    outcome($calculate([line(101)], $bad), 'unavailable', null, 'Foreign configuration');
    foreach (['zones', 'classes', 'assignments', 'tariffs', 'combinations'] as $table) {
        $bad = $config; $bad[$table][0][$field] = $foreign;
        $result = $calculate([line(101)], $bad);
        outcome($result, 'unavailable', null, 'Foreign configuration row: ' . $table);
        check(!$result['groups'] && $result['currency'] === null, 'Foreign configuration details leaked');
    }
    $badItems = $items; $badItems[0][$field] = $foreign;
    outcome($calculate([line(101)], null, $badItems), 'unavailable', null, 'Foreign item');
    outcome($calculate([line(101)], null, null, ['country' => 'DE'], ['clientid' => $field === 'clientid' ? 8 : 7, 'shopid' => $field === 'shopid' ? 42 : 41]), 'unavailable', null, 'Foreign caller context');
}
outcome($calculate([line(999)]), 'unavailable', null, 'Unknown item');
foreach (['deleted' => 1, 'shopenabled' => 0] as $field => $value) {
    $badItems = $items; $badItems[0][$field] = $value;
    outcome($calculate([line(101)], null, $badItems), 'unavailable', null, 'Unavailable item');
}
$badItems = $items; $badItems[0]['currency'] = 'USD';
outcome($calculate([line(101)], null, $badItems), 'quote_required', null, 'Mixed currency');
$usd = $config; $usd['currency'] = 'USD'; $usdItems = array_map(static function(array $item): array { $item['currency'] = 'USD'; return $item; }, $items);
outcome($calculate([line(101)], $usd, $usdItems), 'calculated', '12.00', 'Explicit two-decimal currency');
$bad = $config; $bad['minor_units'] = 0;
outcome($calculate([line(101)], $bad), 'quote_required', null, 'Unsupported currency precision');
$highValue = $items; $highValue[0]['price'] = '60000000'; $highValue[1]['price'] = '60000000';
outcome($calculate([line(101), line(102)], null, $highValue), 'quote_required', null, 'Combined goods value ceiling');
$badPickup = $items; $badPickup[0]['price'] = '-1';
outcome($calculate([line(101)], $pickup, $badPickup, ['country' => 'DE', 'method' => 'pickup']), 'quote_required', null, 'Pickup bypassed monetary validation');
check($a['goods_net'] === '200.00', 'Validated total goods value is missing');
$overflow = $config; $overflow['tariffs'][1]['base'] = '99999999.99';
outcome($calculate([line(102, '2')], $overflow), 'quote_required', null, 'Amount ceiling');
$overflow = $items; $overflow[1]['price'] = '99999999';
outcome($calculate([line(102, '100000')], null, $overflow), 'quote_required', null, 'Item value overflow');
$overflow = $items; $overflow[1]['packweight'] = '99999999'; $overflow[1]['price'] = '0';
outcome($calculate([line(102, '100000')], null, $overflow), 'quote_required', null, 'Physical arithmetic overflow');
$bad = $config; $bad['classes'][0]['title'] = 'Arbitrary merchant class';
check($calculate([line(101)], $bad)['status'] === 'calculated', 'Class names became fixed PHP categories');
$reordered = $config;
foreach (['classes', 'tariffs', 'assignments', 'zones', 'combinations'] as $table) $reordered[$table] = array_reverse($reordered[$table]);
check($a === $calculate([line(101), line(102)], $reordered, array_reverse($items)), 'Input ordering changed the result');
check($a === $calculate([line(102), line(101)]), 'Calculation mutated inputs or retained state');
check(json_decode(json_encode($a, JSON_THROW_ON_ERROR), true) === $a, 'Result cannot be stored as an immutable snapshot');
check($a['groups'][0]['tariff']['id'] === 2 && $a['groups'][0]['combinations'][0]['rule']['id'] === 1 && $a['groups'][0]['basis']['weight_kg'] === '105.00000000', 'Applied rule and physical basis are not auditable');
// Every configuration field rejects malformed nested input without PHP warnings or crashes.
foreach (['zones', 'classes', 'assignments', 'tariffs', 'combinations'] as $table) {
    foreach (array_keys($config[$table][0]) as $field) {
        foreach ([null, [], ['unexpected']] as $invalid) {
            if ($table === 'classes' && $field === 'required_fields' && $invalid === []) continue;
            $bad = $config; $bad[$table][0][$field] = $invalid;
            $result = $calculate([line(101)], $bad);
            check($result['status'] !== 'calculated' && $result['net'] === null, 'Malformed configuration accepted: ' . $table . '.' . $field);
        }
    }
}
echo "PASS: $checks pure shipping tariff, combination, validation, scope, precision and reproducibility checks\n";
