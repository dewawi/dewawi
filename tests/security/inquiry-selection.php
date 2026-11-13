<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/inquiry-dynamic-form.php';
$service = new Shops_Service_InquirySelection();
$config = [1 => [['name' => 'load', 'type' => 'number', 'calculate' => ['target' => 'supply / ambient']]], 2 => [
	['name' => 'medium', 'type' => 'select', 'options' => ['Wasser', 'Wasser/Glykol']],
	['name' => 'pumpe', 'type' => 'select', 'options' => ['Ja', 'Nein', 'Extern vorgesehen']],
	['name' => 'geraeuschreduktion', 'type' => 'select', 'options' => ['Nein', 'Leise']],
	['name' => 'notes', 'type' => 'textarea'],
]];
$settings = ['selection' => ['method' => 'form_calc', 'automatic_quote' => true, 'accepted_values' => ['medium' => ['0'], 'pumpe' => ['1'], 'geraeuschreduktion' => ['0']]], 'quote' => ['tax_country' => 'DE']];
$values = ['load' => '9.8', 'supply' => 20, 'ambient' => 30, 'sku' => 'DEVICE', 'country' => 'DE', 'medium' => '0', 'pumpe' => '1', 'geraeuschreduktion' => '0'];
$suggestions = [['description' => 'DEVICE', 'value' => 9.87, 'capacity_raw' => 9.87]];
checkDynamic($service->evaluate($config, $settings, $values, $suggestions)['eligible'], 'Complete reviewed selection was blocked');
checkDynamic(!$service->evaluate($config, [], $values, $suggestions)['eligible'], 'Legacy form auto-approved without opt-in');
foreach([
	array_replace_recursive($settings, ['selection' => ['method' => 'manual']]),
	array_replace_recursive($settings, ['selection' => ['method' => 'unknown']]),
	array_replace_recursive($settings, ['selection' => ['automatic_quote' => 'true']]),
] as $invalid) checkDynamic(!$service->evaluate($config, $invalid, $values, $suggestions)['eligible'], 'Unsupported selection approved');
foreach([
	['quantity' => -1], ['load' => 10], ['load' => 'invalid'], ['sku' => 'OTHER'], ['country' => 'FR'], ['country' => ''],
	['medium' => '1'], ['glykol_anteil' => 30], ['pumpe' => '0'], ['geraeuschreduktion' => '1'], ['notes' => 'Please include a pump'], ['unknown_requirement' => 'yes'],
] as $change) checkDynamic(!$service->evaluate($config, $settings, array_replace($values, $change), $suggestions)['eligible'], 'Unverified request approved: '.json_encode($change));
checkDynamic(!$service->evaluate($config, $settings, $values, [['description' => 'DEVICE', 'value' => 9.8, 'capacity_raw' => 9.799]])['eligible'], 'Rounding approved an undersized device');
$unverifiedInput = $config;
$unverifiedInput[1][0]['calculate']['target'] .= ' / pumpe';
checkDynamic(!$service->evaluate($unverifiedInput, $settings, array_replace($values, ['pumpe' => '0']), $suggestions)['eligible'], 'Calculation input bypassed option approval');
$mapped = $settings;
$mapped['selection']['option_fields'] = ['pumpe' => ['0' => ['itemoptid' => 123, 'selection_verified' => true]]];
$result = $service->evaluate($config, $mapped, array_replace($values, ['pumpe' => '0']), $suggestions);
checkDynamic($result['eligible'] && $result['options'] === [123], 'Explicit reviewed option mapping was lost');
$mapped['selection']['option_fields']['pumpe']['0']['itemoptid'] = '123invalid';
checkDynamic(!$service->evaluate($config, $mapped, array_replace($values, ['pumpe' => '0']), $suggestions)['eligible'], 'Invalid option ID was coerced into an existing ID');
$mapped['selection']['option_fields']['pumpe']['0']['itemoptid'] = 123;
$mapped['selection']['option_fields']['pumpe']['0']['selection_verified'] = false;
checkDynamic(!$service->evaluate($config, $mapped, array_replace($values, ['pumpe' => '0']), $suggestions)['eligible'], 'Price mapping without selection approval accepted');
if(is_file('/tmp/dewawi-inquiry-referenzen/form-865.fields.json')) {
	$reference = Shops_Model_FormConfig::normalize(Shops_Model_FormConfig::decode(file_get_contents('/tmp/dewawi-inquiry-referenzen/form-865.fields.json')));
	checkDynamic(!$service->evaluate($reference, [], ['sku' => 'DEVICE', 'kuehlleistung' => 20, 'medium' => '1', 'glykol_anteil' => 30, 'pumpe' => '0'], [])['eligible'], 'Real reference was automatically approved without technical data');
}
echo "PASS: $checks cumulative inquiry form and selection approval checks\n";
