<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!isset($adapter, $argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-shop-inquiry-quote-')) {
	echo "SKIP: run python3 tests/security/shop-inquiry-quote.py for isolated variant integration\n";
	return;
}
require __DIR__.'/fixtures/inquiry-variant-config.php';
$variantChecks = 0;
function checkVariant(bool $condition, string $message): void {
	global $variantChecks;
	$variantChecks++;
	if(!$condition) throw new RuntimeException($message);
}
function variantResult(array $config, array $values): array {
	return (new Shops_Service_InquirySelection())->evaluate(Shops_Model_FormConfig::normalize($config), $config['_settings'], $values, [['itemid' => 999, 'price' => 0]]);
}
$variantContext = Zend_Registry::get('SiteContext');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['catalog', 'inquiry']));
$adapter->insert('taxrate', ['id' => 200, 'name' => 'Variant fixture tax', 'rate' => 19, 'ordering' => 20, 'clientid' => 100]);
foreach([200 => 2000, 201 => 2000, 202 => 2000, 210 => 2100, 212 => 2100] as $id => $parent) $adapter->insert('itemoptset', ['id' => $id, 'parentid' => $parent, 'title' => 'Fixture group '.$id, 'clientid' => 100]);
foreach([
	[2000, 0, 30, 1000], [2001, 2000, 30, 1125.125], [2002, 2000, 30, 1300],
	[2100, 0, 40, 10], [2101, 2100, 40, 12.5], [2102, 2100, 40, 15], [2200, 0, 40, 50],
] as [$id, $parent, $category, $price]) $adapter->insert('item', ['id' => $id, 'parentid' => $parent, 'catid' => 0, 'shopcatid' => $category, 'shopid' => 120, 'clientid' => 100, 'sku' => $parent ? 'DUPLICATE-VARIANT' : 'BASE-'.$id, 'title' => 'Synthetic variant '.$id, 'shopenabled' => 1, 'price' => $price, 'currency' => 'EUR', 'taxid' => 200, 'uomid' => 3]);
foreach([
	[201, 2000, 200, 125.125], [202, 2000, 200, 300], [203, 2000, 201, 0], [204, 2000, 202, 50.25],
	[211, 2100, 210, 2.5], [212, 2100, 210, 5], [214, 2100, 212, 1.25],
] as [$id, $parent, $group, $price]) $adapter->insert('itemopt', ['id' => $id, 'parentid' => $parent, 'optsetid' => $group, 'itemid' => $id === 204 ? 2200 : null, 'sku' => 'OPTION-'.$id, 'title' => $id === 203 ? 'Kostenlose Grundausstattung' : 'Fixture option '.$id, 'price' => $price, 'quantity' => $id === 204 ? 2 : 1, 'taxrate' => 19, 'currency' => 'EUR', 'uom' => 'Stück', 'clientid' => 100]);
foreach([2001 => [201,203], 2002 => [202,203], 2101 => [211], 2102 => [212]] as $item => $ids) foreach($ids as $id) $adapter->insert('itemvariantopt', ['itemid' => $item, 'itemoptid' => $id, 'clientid' => 100]);
$config = inquiryVariantConfig();
$values = ['family' => 'equipment', 'execution' => 'small', 'kit' => 'yes', 'quantity' => '2,5'];
$before = [];
foreach(['item', 'itemopt', 'itemoptset', 'itemvariantopt', 'quote', 'quotepos'] as $table) $before[$table] = $adapter->fetchAll('SELECT * FROM '.$table.' ORDER BY id');
$r = variantResult($config, $values);
checkVariant(array_column($r['candidates'], 'itemid') === [2001,2002] && $r['selected']['itemid'] === 2001, 'Existing variants or exact option match failed');
checkVariant(!$r['eligible'] && $r['technical']['status'] === 'review_required' && $r['commercial']['status'] === 'review_required', 'Variant configuration enabled automatic release');
checkVariant($r['selected']['sku'] === 'DUPLICATE-VARIANT' && $r['selected']['parentid'] === 2000, 'Variant lost canonical identity');
checkVariant(array_column($r['options'], 'itemoptid') === [201,203,204], 'Real included/extra identities missing');
checkVariant($r['options'][0]['included'] && $r['options'][0]['price'] === 0.0 && $r['options'][0]['catalog_price'] === 125.125, 'Included surcharge counted twice');
checkVariant($r['options'][1]['included'] && $r['options'][1]['catalog_price'] === 0.0, 'Free included option lost');
checkVariant(!$r['options'][2]['included'] && $r['options'][2]['itemid'] === 2200 && $r['options'][2]['quantity'] === 5.0, 'Accessory identity/multiplier missing');
checkVariant($r['commercial']['pricing_status'] === 'complete' && $r['commercial']['positions'][0]['price'] === 1125.125, 'Saved variant price was recomputed');
checkVariant($r['commercial']['totals']['subtotal'] === 3064.06 && $r['commercial']['totals']['total'] === 3646.23, 'Position rounding/taxes or quantity differs from Calculate');
checkVariant($r === variantResult($config, $values), 'Repeated read-only evaluation is not deterministic');
$huge = variantResult($config, array_replace($values, ['quantity' => str_repeat('9', 308)]));
checkVariant(($huge['commercial']['pricing_status'] ?? '') === 'review_required' && !isset($huge['commercial']['totals']) && json_encode($huge, JSON_THROW_ON_ERROR) !== '', 'Out-of-range quantities produced nonfinite or confirmed prices');
$adapter->beginTransaction();
$adapter->update('currency', ['deleted' => 1], 'clientid = 100');
$adapter->update('item', ['currency' => ''], 'id = 2001');
checkVariant(variantResult($config, $values)['commercial']['pricing_status'] === 'review_required', 'Missing primary currency yielded a confirmed price');
$adapter->rollBack();
$adapter->insert('itemvariantopt', ['itemid' => 2001, 'itemoptid' => 204, 'clientid' => 100]);
$includedExtra = variantResult($config, $values);
checkVariant($includedExtra['options'][2]['included'] && $includedExtra['options'][2]['price'] === 0.0 && $includedExtra['commercial']['totals']['subtotal'] === 2812.81, 'An additional choice already in the variant price was charged twice');
$adapter->delete('itemvariantopt', 'itemid = 2001 AND itemoptid = 204');
$trade = variantResult($config, array_replace($values, ['family' => 'trading']));
checkVariant($trade['selected']['itemid'] === 2101 && array_column($trade['options'], 'itemoptid') === [211,214], 'Second family reused first family option IDs');
$large = variantResult($config, array_replace($values, ['execution' => 'large', 'variantid' => '2002']));
checkVariant($large['selected']['itemid'] === 2002, 'Duplicate SKU variants collapsed');
foreach(['2101', '999999', '2002', '2001invalid', ['2001'], true] as $id) checkVariant(variantResult($config, $values + ['variantid' => $id])['invalid'], 'Manipulated/wrong-configuration variant accepted');
foreach([['execution' => '201'], ['kit' => '214'], ['execution' => ['small']], ['family' => ['equipment']], ['quantity' => 0], ['quantity' => -1], ['quantity' => 'NaN']] as $change) checkVariant(variantResult($config, array_replace($values, $change))['selected'] === null, 'Invalid input selected a variant');
$unavailable = $config;
$unavailable['_settings']['selection']['bases']['equipment']['option_fields']['execution']['choices']['small'] = 211;
checkVariant(variantResult($unavailable, $values)['invalid'], 'Option/group of another base accepted');

$adapter->insert('item', ['id' => 2003, 'parentid' => 2000, 'catid' => 0, 'shopcatid' => 30, 'shopid' => 120, 'clientid' => 100, 'sku' => 'DUPLICATE-VARIANT', 'title' => 'Ambiguous saved combination', 'shopenabled' => 1]);
foreach([201,203] as $id) $adapter->insert('itemvariantopt', ['itemid' => 2003, 'itemoptid' => $id, 'clientid' => 100]);
checkVariant(in_array('variant_ambiguous', variantResult($config, $values + ['variantid' => '2001'])['reasons'], true) && variantResult($config, $values)['selected'] === null, 'Explicit ID concealed ambiguous combination');
$adapter->update('item', ['deleted' => 1], 'id = 2003');
$adapter->update('itemvariantopt', ['deleted' => 1], 'itemid = 2001 AND itemoptid = 201');
checkVariant(in_array('variant_no_match', variantResult($config, $values)['reasons'], true), 'Missing saved combination was guessed');
checkVariant(variantResult($config, $values + ['variantid' => '2001'])['invalid'], 'Stale variant survived changed relations');
$adapter->update('itemvariantopt', ['deleted' => 0], 'itemid = 2001 AND itemoptid = 201');

$conflict = $config;
$conflict['configuration'][] = ['name' => 'conflicting', 'type' => 'select', 'options' => ['yes' => 'Conflict'], 'required' => true];
$conflict['_settings']['selection']['bases']['equipment']['option_fields']['conflicting'] = ['group_id' => 200, 'role' => 'additional', 'exclusivity' => 'single', 'compatibility_verified' => true, 'choices' => ['yes' => 202]];
checkVariant(in_array('variant_group_conflict', variantResult($conflict, $values + ['conflicting' => 'yes'])['reasons'], true) && variantResult($conflict, $values + ['conflicting' => 'yes'])['selected'] === null, 'Exclusive group conflict accepted');
$unknown = $config;
unset($unknown['_settings']['selection']['bases']['equipment']['option_fields']['execution']['exclusivity']);
checkVariant(in_array('variant_group_rules_unknown', variantResult($unknown, $values)['reasons'], true), 'Missing exclusivity was invented');
unset($unknown['_settings']['selection']['bases']['equipment']['option_fields']['kit']['exclusivity']);
checkVariant(variantResult($unknown, $values)['commercial']['pricing_status'] === 'review_required', 'Missing accessory group rules yielded confirmed total');
$unverified = $config;
unset($unverified['_settings']['selection']['bases']['equipment']['option_fields']['kit']['compatibility_verified']);
checkVariant(variantResult($unverified, $values)['commercial']['pricing_status'] === 'review_required', 'Unproven accessory charged as confirmed');

foreach([['price' => -1], ['price' => -2], ['price' => null], ['currency' => 'USD'], ['quantity' => 0], ['taxrate' => -1], ['uom' => 'unknown'], ['masterid' => 99], ['itemid' => 999999], ['priceruleaction' => 'SQL']] as $change) {
	$adapter->update('itemopt', $change, 'id = 204');
	$pricing = variantResult($config, $values);
	checkVariant($pricing['commercial']['pricing_status'] === 'review_required' && !isset($pricing['commercial']['totals']), 'Unreliable option priced: '.json_encode($change));
	if(array_key_exists('price', $change)) checkVariant($pricing['options'][2]['status'] === ($change['price'] === -2 ? 'unavailable' : ($change['price'] === -1 ? 'on_request' : 'review_required')), 'Missing option price invented an availability state');
	if(isset($change['currency'])) checkVariant($pricing['options'][2]['currency'] === 'USD', 'Unresolved option lost its actual source currency');
	$adapter->update('itemopt', ['price' => 50.25, 'currency' => 'EUR', 'quantity' => 2, 'taxrate' => 19, 'uom' => 'Stück', 'masterid' => null, 'itemid' => 2200, 'priceruleaction' => null], 'id = 204');
}
$adapter->update('itemopt', ['priceruleaction' => 'bypercent', 'priceruleamount' => 10], 'id = 204');
checkVariant(abs(variantResult($config, $values)['options'][2]['price'] - 45.225) < 0.00001, 'Existing PriceRule semantics not reused');
$adapter->update('itemopt', ['priceruleaction' => null, 'priceruleamount' => null], 'id = 204');
foreach([['price' => 1200], ['specialprice' => 900.125], ['currency' => 'USD'], ['taxid' => 999], ['uomid' => 999], ['specialprice' => -1]] as $change) {
	$adapter->update('item', $change, 'id = 2001');
	$pricing = variantResult($config, $values);
	if(isset($change['price'])) checkVariant($pricing['commercial']['positions'][0]['price'] === 1200.0, 'Changed saved price was cached');
	elseif(($change['specialprice'] ?? 0) > 0) checkVariant($pricing['commercial']['positions'][0]['price'] === 900.125 && $pricing['commercial']['positions'][0]['price_source'] === 'item.specialprice', 'Stored special price ignored');
	else checkVariant($pricing['commercial']['pricing_status'] === 'review_required', 'Invalid variant commercial data accepted');
	$adapter->update('item', ['price' => 1125.125, 'specialprice' => null, 'currency' => 'EUR', 'taxid' => 200, 'uomid' => 3], 'id = 2001');
}
foreach([['shopenabled' => 0], ['deleted' => 1], ['clientid' => 200], ['shopid' => 121], ['shopcatid' => 32]] as $change) {
	foreach([2000,2001] as $id) {
		$adapter->update('item', $change, 'id = '.$id);
		checkVariant(variantResult($config, $values)['selected'] === null, 'Unavailable/foreign base or variant selected');
		$adapter->update('item', ['shopenabled' => 1, 'deleted' => 0, 'clientid' => 100, 'shopid' => 120, 'shopcatid' => 30], 'id = '.$id);
	}
}
foreach(['itemopt', 'itemoptset', 'itemvariantopt'] as $table) {
	$where = ['itemopt' => 'id = 201', 'itemoptset' => 'id = 200', 'itemvariantopt' => 'itemid = 2001 AND itemoptid = 201'][$table];
	foreach([['deleted' => 1], ['clientid' => 200]] as $change) {
		$adapter->update($table, $change, $where);
		checkVariant(variantResult($config, $values)['selected'] === null, 'Foreign/deleted relation, group or option selected');
		$adapter->update($table, ['deleted' => 0, 'clientid' => 100], $where);
	}
}
Zend_Registry::set('Client', ['id' => 200, 'parentid' => 100, 'modules' => ['items' => 100]]);
checkVariant(variantResult($config, $values)['selected']['itemid'] === 2001, 'Parent-owned public shop broken for authorized child user');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 200], [], ['catalog','inquiry']));
checkVariant(variantResult($config, $values)['selected'] === null, 'Internal sharing bypassed public shop ownership');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['inquiry']));
checkVariant(!variantResult($config, $values)['candidates'], 'Catalog entitlement bypass');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['catalog','inquiry']));
$countryConfig = $config;
$countryConfig['base'][] = ['name' => 'country', 'type' => 'select', 'source' => 'country', 'required' => true, 'options' => ['' => 'Bitte wählen']];
checkVariant(variantResult($countryConfig, $values + ['country' => 'DE'])['selected']['itemid'] === 2001 && variantResult($countryConfig, $values + ['country' => 'XX'])['selected'] === null, 'Scoped country source failed in variant input validation');
$differentFields = $config;
$differentFields['configuration'][] = ['name' => 'family_only', 'type' => 'select', 'required' => true, 'options' => ['none' => 'No further choice']];
$differentFields['_settings']['selection']['bases']['equipment']['option_fields']['family_only'] = ['group_id' => 202, 'role' => 'additional', 'exclusivity' => 'single', 'choices' => ['none' => null]];
$hydrated = (new Shops_Service_InquirySelection())->variantFormConfig(Shops_Model_FormConfig::normalize($differentFields), $differentFields['_settings']['selection'], ['family' => 'trading']);
checkVariant(!in_array('family_only', array_column($hydrated['configuration'], 'name'), true) && variantResult($differentFields, array_replace($values, ['family' => 'trading']))['selected']['itemid'] === 2101, 'Another family retained stale required fields');
checkVariant(variantResult($differentFields, array_replace($values, ['family' => 'trading', 'family_only' => 'none']))['invalid'], 'Direct evaluation accepted stale family-specific selection');
foreach(['sql' => 'SELECT 1', 'bases' => [], 'variant_field' => 'email', 'step' => 'contact', 'quantity_field' => 'email'] as $key => $value) {
	$bad = $config;
	$bad['_settings']['selection'][$key] = $value;
	try { Shops_Model_FormConfig::normalize($bad); throw new RuntimeException('Invalid variant settings accepted'); }
	catch(InvalidArgumentException $e) { checkVariant(true, 'Invalid configuration rejected'); }
}
$adapter->beginTransaction();
foreach(range(3000,3099) as $id) $adapter->insert('item', ['id' => $id, 'parentid' => 2000, 'catid' => 0, 'shopcatid' => 30, 'shopid' => 120, 'clientid' => 100, 'sku' => 'BOUNDED-'.$id, 'shopenabled' => 1]);
checkVariant(!variantResult($config, $values)['candidates'] && in_array('variant_scan_limit', variantResult($config, $values)['reasons'], true), 'Variant overflow returned partial matches');
$adapter->rollBack();
foreach(['itemopt', 'itemoptset'] as $table) {
	$adapter->beginTransaction();
	foreach(range(4000,4200) as $id) {
		$row = ['id' => $id, 'parentid' => 2000, 'clientid' => 100];
		if($table === 'itemopt') $row['optsetid'] = 202;
		$adapter->insert($table, $row);
	}
	checkVariant(!variantResult($config, $values)['candidates'] && in_array('variant_scan_limit', variantResult($config, $values)['reasons'], true), 'Option/group overflow returned partial matches');
	$adapter->rollBack();
}
$adapter->beginTransaction();
foreach(range(3300,3324) as $id) $adapter->insert('itemopt', ['id' => $id, 'parentid' => 2000, 'optsetid' => 202, 'clientid' => 100]);
foreach(range(3000,3097) as $id) {
	$adapter->insert('item', ['id' => $id, 'parentid' => 2000, 'catid' => 0, 'shopcatid' => 30, 'shopid' => 120, 'clientid' => 100, 'sku' => 'RELATION-BOUND-'.$id, 'shopenabled' => 1]);
	foreach(range(3300,3324) as $option) $adapter->insert('itemvariantopt', ['itemid' => $id, 'itemoptid' => $option, 'clientid' => 100]);
}
checkVariant(!variantResult($config, $values)['candidates'] && in_array('variant_scan_limit', variantResult($config, $values)['reasons'], true), 'Relation overflow returned partial matches');
$adapter->rollBack();
$variantView = new Zend_View();
$variantView->addScriptPath($root.'/application/modules/shops/views/scripts');
$variantView->addHelperPath($root.'/application/views/helpers', 'Zend_View_Helper');
$escapedVariant = variantResult($config, $values) + ['editable' => true];
$escapedVariant['candidates'][0]['title'] = '<script>window.variantInjected=true</script>';
$escapedVariant['options'][0]['title'] = '<img src=x onerror=alert(1)>';
$variantHtml = $variantView->partial('inquiry/variants.phtml', ['selection' => $escapedVariant]);
checkVariant(!str_contains($variantHtml, '<script>') && !str_contains($variantHtml, '<img src=x') && str_contains($variantHtml, '&lt;script&gt;'), 'Variant metadata produced executable markup');
// Remove only our deliberate ambiguity fixture before comparing the read-only business tables.
$adapter->delete('itemvariantopt', 'itemid = 2003');
$adapter->delete('item', 'id = 2003');
foreach($before as $table => $rows) checkVariant($adapter->fetchAll('SELECT * FROM '.$table.' ORDER BY id') === $rows, 'Public evaluation mutated '.$table);
$adapter->insert('shopinquiryform', ['id' => 910, 'shopid' => 120, 'clientid' => 100, 'title' => 'Synthetic variant configuration', 'fields' => json_encode($config), 'quotetemplateid' => 100]);
Zend_Registry::set('SiteContext', $variantContext);
echo "PASS: $variantChecks variant identity, option mapping, prices, review gates, tenant scope and read-only checks\n";
