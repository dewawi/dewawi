<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!isset($adapter, $argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-shop-inquiry-quote-')) {
    echo "SKIP: run python3 tests/security/shop-inquiry-quote.py for isolated catalog integration\n";
    return;
}
require __DIR__.'/fixtures/inquiry-catalog-config.php';
$catalogChecks = 0;
function checkCatalog(bool $condition, string $message): void {
    global $catalogChecks;
    $catalogChecks++;
    if(!$condition) throw new RuntimeException($message);
}
function catalogResult(array $config, array $values): array {
    return (new Shops_Service_InquirySelection())->evaluate(Shops_Model_FormConfig::normalize($config), $config['_settings'], $values, []);
}
function catalogAttribute(int $item, string $title, $value, string $unit = '', ?string $description = null, int $client = 100, int $set = 0, int $deleted = 0): void {
    global $adapter;
    $adapter->insert('itematr', ['parentid' => $item, 'atrsetid' => $set, 'title' => $title, 'value' => $value, 'description' => $description, 'uom' => $unit, 'clientid' => $client, 'deleted' => $deleted]);
}
$originalContext = Zend_Registry::get('SiteContext');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['catalog', 'inquiry']));
foreach([
    [30, 0, 100, 120, 1, 0, 'shop'], [31, 30, 100, 120, 1, 0, 'shop'],
    [32, 0, 100, 120, 0, 0, 'shop'], [33, 0, 100, 120, 1, 1, 'shop'],
    [34, 0, 200, 120, 1, 0, 'shop'], [35, 0, 100, 121, 1, 0, 'shop'],
    [36, 0, 100, 120, 1, 0, 'item'], [40, 0, 100, 120, 1, 0, 'shop'], [50, 0, 100, 120, 1, 0, 'shop'],
] as [$id, $parent, $client, $shop, $active, $deleted, $type]) $adapter->insert('category', ['id' => $id, 'parentid' => $parent, 'title' => 'Synthetic category '.$id, 'type' => $type, 'clientid' => $client, 'shopid' => $shop, 'activated' => $active, 'deleted' => $deleted]);
foreach(range(1000, 1016) as $id) {
    $category = [1001 => 31, 1012 => 32, 1013 => 33, 1014 => 34, 1015 => 35, 1016 => 36][$id] ?? 30;
    $adapter->insert('item', ['id' => $id, 'catid' => 0, 'shopcatid' => $category, 'shopid' => $id === 1011 ? 121 : 120, 'clientid' => $id === 1010 ? 200 : 100, 'sku' => $id < 1002 ? 'DUPLICATE' : 'REJECT-'.$id, 'title' => 'Equipment '.$id, 'shopenabled' => $id === 1009 ? 0 : 1, 'deleted' => $id === 1008 ? 1 : 0]);
    if($id !== 1003) catalogAttribute($id, 'Power', $id === 1004 ? null : ($id === 1005 ? '12 kW' : '12,5'), $id === 1002 ? 'W' : 'kW');
    catalogAttribute($id, 'Fluid', '', '', 'water');
    catalogAttribute($id, 'Grade', 'A');
}
catalogAttribute(1006, 'Power', '99', 'kW');
catalogAttribute(1000, 'Power', '999', 'kW', null, 200);
catalogAttribute(1000, 'Power', '999', 'kW', null, 100, 0, 1);
$adapter->delete('itematr', 'parentid = 1007 AND title = \'Power\'');
catalogAttribute(1007, 'Power', '99', 'kW', null, 100, 9999);
$catalogImage = imagecreatetruecolor(80, 50);
imagefill($catalogImage, 0, 0, imagecolorallocate($catalogImage, 20, 100, 170));
imagepng($catalogImage, BASE_PATH.'/media/1/0/100/images/catalog.png');
imagedestroy($catalogImage);
$adapter->insert('media', ['parentid' => 1000, 'module' => 'items', 'controller' => 'item', 'type' => 'image', 'url' => 'catalog.png', 'title' => 'Public catalog image', 'clientid' => 100]);
$adapter->insert('media', ['parentid' => 1000, 'module' => 'items', 'controller' => 'item', 'type' => 'image', 'url' => 'foreign-image.png', 'title' => 'Foreign media', 'clientid' => 200]);
$adapter->insert('item', ['id' => 1017, 'catid' => 0, 'shopcatid' => 30, 'shopid' => 120, 'clientid' => 100, 'sku' => null, 'title' => 'Missing SKU', 'shopenabled' => 1]);
catalogAttribute(1017, 'Power', '12,5', 'kW');
catalogAttribute(1017, 'Fluid', '', '', 'water');
catalogAttribute(1017, 'Grade', 'A');
$config = inquiryCatalogConfig();
$values = ['demand' => '12,5', 'material' => '0'];
$r = catalogResult($config, $values + ['clientid' => 200, '_selection' => ['eligible' => true], 'email' => 'future@example.test']);
checkCatalog(array_column($r['candidates'], 'itemid') === [1000, 1001], 'Catalog leaked scope, lost duplicate SKU identity or failed numeric commas');
checkCatalog(!$r['eligible'] && $r['technical']['status'] === 'review_required' && $r['commercial']['status'] === 'review_required', 'Catalog approved a quote');
checkCatalog(array_keys($r['inputs']) === ['demand', 'material'], 'Contract includes unvalidated future or forged fields');
checkCatalog($r['candidates'][0]['images'] === [['url' => 'catalog.png', 'title' => 'Public catalog image', 'type' => 'image']], 'Foreign or wrong-type media reached catalog');
foreach(['unit_mismatch', 'missing_attribute', 'invalid_attribute', 'ambiguous_attribute'] as $reason) checkCatalog(!empty($r['summary'][$reason]), 'Missing diagnostic: '.$reason);
checkCatalog($r === catalogResult($config, $values), 'Repeated catalog evaluation is nondeterministic');
$untrusted = (new Shops_Service_InquirySelection())->evaluate(Shops_Model_FormConfig::normalize($config), $config['_settings'], $values, [['itemid' => 1010]]);
checkCatalog($untrusted === $r, 'Catalog trusted legacy/browser suggestion metadata');
$selected = catalogResult($config, $values + ['itemid' => '1001', 'sku' => 'forged']);
checkCatalog($selected['selected']['itemid'] === 1001 && $selected['selected']['sku'] === 'DUPLICATE', 'Duplicate SKU selection lost canonical identity');
foreach(['1010', '1002', '999999', '1000invalid', ['1000'], true] as $badId) checkCatalog(in_array('catalog_selection_invalid', catalogResult($config, $values + ['itemid' => $badId])['reasons'], true), 'Forged selection accepted');
foreach([['demand' => null], ['demand' => 'invalid'], ['material' => 'unknown'], ['material' => ['0']]] as $bad) checkCatalog(!catalogResult($config, array_replace($values, $bad))['candidates'], 'Invalid input yielded catalog matches');
$limited = $config;
$limited['_settings']['selection']['limit'] = 1;
$r = catalogResult($limited, $values);
checkCatalog(count($r['candidates']) === 1 && $r['candidates'][0]['itemid'] === 1000 && $r['has_more'], 'Catalog result limit/order failed');
$exactCategory = $config;
$exactCategory['_settings']['selection']['include_subcategories'] = false;
checkCatalog(array_column(catalogResult($exactCategory, $values)['candidates'], 'itemid') === [1000], 'Category descendant setting ignored');
foreach([32,33,34,35,36] as $category) {
    $bad = $config;
    $bad['_settings']['selection']['categories'] = [$category];
    checkCatalog(!catalogResult($bad, $values)['candidates'], 'Inactive/foreign/internal category allowed');
}
foreach(['eq', 'gte', 'lte'] as $operator) {
    $comparison = $config;
    $comparison['_settings']['selection']['filters'][0]['operator'] = $operator;
    checkCatalog(count(catalogResult($comparison, $values)['candidates']) === 2, 'Numeric comparison failed: '.$operator);
}
$zeroInput = $config;
$zeroInput['requirements'][0]['format'] = ['type' => 'decimal'];
$zeroResult = catalogResult($zeroInput, array_replace($values, ['demand' => 0]));
checkCatalog(count($zeroResult['candidates']) === 2 && $zeroResult['inputs']['demand'] === 0.0, 'Formatted zero input was lost');
foreach(['KW', '', 'kWh'] as $unit) {
    $adapter->update('itematr', ['uom' => $unit], 'parentid = 1000 AND clientid = 100 AND deleted = 0 AND title = \'Power\'');
    checkCatalog(array_column(catalogResult($config, $values)['candidates'], 'itemid') === [1001], 'Inconsistent or missing unit silently matched');
}
$adapter->update('itematr', ['uom' => 'kW'], 'parentid = 1000 AND clientid = 100 AND deleted = 0 AND title = \'Power\'');
$none = catalogResult($config, array_replace($values, ['demand' => '13']));
checkCatalog(!$none['candidates'] && !empty($none['summary']['not_matched']), 'Numeric mismatch silently accepted');
$zero = $config;
$zero['_settings']['selection']['filters'][] = ['attribute' => 'Temperature', 'column' => 'value', 'type' => 'number', 'operator' => 'eq', 'unit' => '°C', 'values' => [0]];
catalogAttribute(1000, 'Temperature', '0', '°C');
checkCatalog(array_column(catalogResult($zero, $values)['candidates'], 'itemid') === [1000], 'Zero temperature treated as null');
$adapter->insert('itematrset', ['id' => 30, 'parentid' => 1000, 'title' => 'Second group', 'clientid' => 100]);
catalogAttribute(1000, 'Power', '12.5', 'kW', null, 100, 30);
checkCatalog(array_column(catalogResult($config, $values)['candidates'], 'itemid') === [1001], 'Same-title attributes across groups are not ambiguous');
$grouped = $config;
$grouped['_settings']['selection']['filters'][0]['set_id'] = 30;
checkCatalog(array_column(catalogResult($grouped, $values)['candidates'], 'itemid') === [1000], 'Explicit attribute group did not resolve ambiguity');
$adapter->delete('itematr', 'parentid = 1000 AND atrsetid = 30');
$conditional = $config;
$conditional['requirements'][0]['depends_on'] = 'material';
$conditional['requirements'][0]['depends_value'] = '1';
checkCatalog(count(catalogResult($conditional, ['material' => '0', 'demand' => ['forged']])['candidates']) >= 2, 'Inactive filter value influenced catalog');

$adapter->insert('item', ['id' => 1100, 'catid' => 0, 'shopcatid' => 40, 'shopid' => 120, 'clientid' => 100, 'sku' => 'BOLT-12', 'title' => 'Synthetic bolt', 'shopenabled' => 1]);
catalogAttribute(1100, 'Diameter', 'not-used', 'mm', '12,5');
catalogAttribute(1100, 'Material', '', '', 'steel');
catalogAttribute(1100, 'Grade', 'B');
$trade = inquiryCatalogConfig('trading');
checkCatalog(array_column(catalogResult($trade, $values)['candidates'], 'itemid') === [1100], 'Different product group or description column failed');
$badText = catalogResult($trade, array_replace($values, ['material' => '1']));
checkCatalog(!$badText['candidates'], 'Mapped exact text mismatch accepted');

Zend_Registry::set('Client', ['id' => 200, 'parentid' => 100, 'modules' => ['items' => 100]]);
Zend_Registry::set('User', ['id' => 77, 'clientid' => 200]);
checkCatalog(count(catalogResult($config, $values)['candidates']) === 2, 'Authorized parent-owned public shop depends on logged-in child user');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 200], [], ['catalog']));
checkCatalog(!catalogResult($config, $values)['candidates'], 'Child-owned shop gained parent catalog through internal sharing metadata');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['inquiry']));
checkCatalog(!catalogResult($config, $values)['candidates'], 'Catalog feature entitlement bypassed');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['catalog', 'inquiry']));

foreach([
    ['column' => 'price'], ['operator' => 'SQL'], ['sql' => '1=1'], ['unit' => 'W'], ['values' => []], ['attribute' => ''], ['value_map' => ['0' => ['nested']]],
] as $change) {
    $bad = $config;
    $bad['_settings']['selection']['filters'][0] = array_replace($bad['_settings']['selection']['filters'][0], $change);
    try { Shops_Model_FormConfig::normalize($bad); throw new RuntimeException('Unsafe filter configuration accepted'); }
    catch(InvalidArgumentException $e) { checkCatalog(true, 'Unsafe filter rejected'); }
}
foreach(range(1200, 1404) as $id) $adapter->insert('item', ['id' => $id, 'catid' => 0, 'shopcatid' => 50, 'shopid' => 120, 'clientid' => 100, 'sku' => 'BULK-'.$id, 'title' => 'Synthetic bulk article', 'shopenabled' => 1]);
$bulk = $config;
$bulk['_settings']['selection']['categories'] = [50];
$bulk['_settings']['selection']['filters'] = [];
$bounded = catalogResult($bulk, $values);
checkCatalog(count($bounded['candidates']) === 4 && $bounded['scanned'] === 200 && $bounded['has_more'], 'Bounded catalog scan failed');

$adapter->beginTransaction();
for($index = 0; $index < 5001; $index++) catalogAttribute(1200, 'Duplicated', '1');
$adapter->commit();
$attributeBound = $bulk;
$attributeBound['_settings']['selection']['filters'] = [['attribute' => 'Duplicated', 'column' => 'value', 'type' => 'number', 'operator' => 'eq', 'unit' => '', 'values' => [1]]];
$excess = catalogResult($attributeBound, $values);
checkCatalog(!$excess['candidates'] && in_array('catalog_attribute_limit', $excess['reasons'], true), 'Attribute scan limit produced partial matches');

// Seed two independent forms for the HTTP runner; production form 865 is untouched.
foreach([900 => $config, 901 => $trade] as $id => $fields) $adapter->insert('shopinquiryform', ['id' => $id, 'shopid' => 120, 'clientid' => 100, 'title' => 'Synthetic catalog form '.$id, 'fields' => json_encode($fields), 'quotetemplateid' => 100]);
$view = new Zend_View();
$view->addScriptPath($root.'/application/modules/shops/views/scripts');
$view->addHelperPath($root.'/application/modules/shops/views/helpers', 'Zend_View_Helper');
$rendered = $view->partial('inquiry/catalog.phtml', ['selection' => catalogResult($config, $values)]);
checkCatalog(str_contains($rendered, 'Katalogvorschläge') && str_contains($rendered, 'data-itemid="1001"') && !str_contains($rendered, 'Berechneter Wert'), 'Generic catalog view depends on legacy calculation fields');
checkCatalog(str_contains($rendered, 'Public catalog image') && !str_contains($rendered, 'foreign-image.png'), 'Published catalog image missing or foreign image rendered');
$escaped = catalogResult($config, $values);
$escaped['candidates'][0]['title'] = '<script>window.catalogInjected=true</script>';
$escapedHtml = $view->partial('inquiry/catalog.phtml', ['selection' => $escaped]);
checkCatalog(!str_contains($escapedHtml, '<script>') && str_contains($escapedHtml, '&lt;script&gt;'), 'Catalog metadata rendered as executable markup');
$artifact = dirname($argv[1]).'/catalog-fixture.html';
file_put_contents($artifact, $rendered);
Zend_Registry::set('SiteContext', $originalContext);
echo "PASS: $catalogChecks generic catalog, scope, attribute, unit, identity and result-bound checks\n";
