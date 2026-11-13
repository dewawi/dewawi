<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/inquiry-dynamic-form.php';
require __DIR__.'/fixtures/inquiry-pilot-config.php';
require $root.'/application/modules/shops/controllers/InquiryController.php';

function pilotForm(array $config, int $step): Shops_Form_DynamicForm
{
	$form = new Shops_Form_DynamicForm($config, $step);
	foreach($form->getElements() as $name => $element) {
		if($element['source'] === 'country') $form->addOptions($name, ['' => 'Bitte wählen', 'DE' => 'Deutschland', 'CH' => 'Schweiz'], 'replace');
	}
	return $form;
}

$pilotPath = '/tmp/dewawi-inquiry-pilot-865-2026-11-13/form-865.pilot.fields.json';
$configs = ['synthetic' => inquiryPilotConfig()];
if(is_file($pilotPath)) $configs['reference'] = Shops_Model_FormConfig::decode(file_get_contents($pilotPath));
$before = $checks;
foreach($configs as $case => $config) {
	$normalized = Shops_Model_FormConfig::normalize($config);
	$settings = Shops_Service_InquirySelection::settings(['fields' => json_encode($config)]);
	checkDynamic(!(new Shops_Service_InquirySelection())->evaluate($normalized, $settings, [], [])['eligible'], 'Manual pilot allowed quotes');
	$form = pilotForm($config, 1);
	$values = ['einsatzbereich' => '0', 'aufstellung' => 'unbekannt', 'medium' => '0'];
	checkDynamic($form->isValid($values), 'Unknown manual inputs rejected');
	checkDynamic(!$form->isValid($values + ['kuehlleistung' => 0]), 'Zero cooling demand accepted');
	checkDynamic(!$form->isValid($values + ['kuehlleistung' => -1]), 'Negative cooling demand accepted');
	checkDynamic(!$form->isValid($values + ['kuehlleistung' => '1e999']), 'Nonfinite cooling demand accepted');
	checkDynamic($form->isValid($values + ['kuehlleistung' => '2,5', 'vorlauf_temp' => '0']), 'Decimal comma or zero temperature rejected');
	checkDynamic((string)$form->getFilteredValues()['vorlauf_temp'] === '0' && (string)$form->getFilteredValues()['kuehlleistung'] === '2.5', 'Numeric normalization lost values');
	foreach(['0', '1', '2', '3'] as $application) {
		$input = array_replace($values, ['einsatzbereich' => $application, 'kuehlleistung' => 12, 'heizleistung' => 24]);
		checkDynamic($form->isValid($input), 'Application branch rejected');
		$result = $form->getFilteredValues();
		checkDynamic(in_array($application, ['0', '1'], true) ? isset($result['kuehlleistung']) && !isset($result['heizleistung']) : isset($result['heizleistung']) && !isset($result['kuehlleistung']), 'Heating/cooling requirements mixed');
	}
	$glycol = array_replace($values, ['medium' => '1', 'glykol_typ' => '2', 'glykol_typ_sonstiges' => 'Other fluid', 'glykol_anteil' => 30, 'glykol_bezugsart' => 'volumen', 'glykol_bezugstemperatur' => 0]);
	checkDynamic($form->isValid($glycol), 'Nested pilot glycol rejected');
	checkDynamic((string)$form->getFilteredValues()['glykol_bezugstemperatur'] === '0', 'Nested zero reference lost');
	checkDynamic($form->isValid(array_replace($glycol, ['glykol_typ' => '1', 'glykol_typ_sonstiges' => ['forged'], 'glykol_bezugsart' => 'masse', 'glykol_bezugstemperatur' => ['forged']])), 'Inactive nested forged fields rejected mass selection');
	checkDynamic(!isset($form->getFilteredValues()['glykol_bezugstemperatur'], $form->getFilteredValues()['glykol_typ_sonstiges']), 'Inactive nested input survived');
	checkDynamic($form->isValid($values + ['glykol_typ' => ['forged']]), 'Water rejected inactive glycol');
	$equipment = pilotForm($config, 2);
	$equipmentValues = ['pumpe' => 'unbekannt', 'tank' => 'unbekannt', 'elektrische_versorgung' => 'bekannt', 'netzspannung' => 400, 'netzfrequenz' => 50];
	checkDynamic($equipment->isValid($equipmentValues), 'Manual equipment without SKU rejected');
	$html = $equipment->render();
	checkDynamic(str_contains($html, '<details') && !str_contains($html, 'class="inquiry-details" open'), 'Advanced inputs not initially collapsed');
	checkDynamic((float)$equipment->getFilteredValues()['netzspannung'] === 400.0, 'Collapsed active input lost');
	checkDynamic(!$equipment->isValid(array_replace($equipmentValues, ['netzfrequenz' => 0])), 'Zero frequency accepted');
	checkDynamic(str_contains($equipment->render(), 'class="inquiry-details" open'), 'Invalid advanced section stayed closed');
	checkDynamic(!$equipment->isValid(array_replace($equipmentValues, ['netzspannung' => 0])), 'Zero voltage accepted');
	$contact = pilotForm($config, 3);
	$address = ['firma' => 'Test GmbH', 'ansprechpartner' => 'Test Person', 'adresse' => 'Billing Street', 'plz' => '12345', 'stadt' => 'Billing City', 'country' => 'DE', 'email' => 'test@example.test', 'privacy' => 1, 'lieferadresse_abweichend' => 'ja', 'lieferempfaenger' => 'Delivery GmbH', 'lieferstrasse' => 'Delivery Street', 'lieferplz' => '98765', 'lieferstadt' => 'Delivery City', 'lieferland' => 'CH'];
	checkDynamic($contact->isValid($address), 'Structured separate delivery address rejected');
	checkDynamic(!$contact->isValid(array_replace($address, ['lieferland' => 'XX'])), 'Unknown country accepted');
	checkDynamic(!$contact->isValid(array_replace($address, ['lieferstrasse' => ''])), 'Missing active delivery street accepted');
	checkDynamic($contact->isValid(array_replace($address, ['lieferadresse_abweichend' => 'nein', 'lieferstrasse' => ['forged'], 'lieferland' => ['forged']])), 'Inactive forged delivery address blocked billing');
	checkDynamic(!isset($contact->getFilteredValues()['lieferstrasse'], $contact->getFilteredValues()['lieferland']), 'Inactive delivery values survived');
}

// A different product group uses the same layout and multi-value dependency contract.
$other = [1 => [
	['name' => 'product', 'type' => 'select', 'options' => ['filter' => 'Filter', 'motor' => 'Motor', 'drive' => 'Drive']],
	['name' => 'power', 'type' => 'number', 'depends_on' => 'product', 'depends_value' => ['motor', 'drive'], 'section' => 'Details', 'tab' => 'advanced'],
]];
$otherForm = pilotForm($other, 1);
checkDynamic($otherForm->isValid(['product' => 'motor', 'power' => 3]) && isset($otherForm->getFilteredValues()['power']), 'Other product group lost active power');
checkDynamic($otherForm->isValid(['product' => 'filter', 'power' => ['forged']]) && !isset($otherForm->getFilteredValues()['power']), 'Other product group retained inactive power');
foreach([
	[1 => [['name' => 'country', 'type' => 'select', 'source' => 'private_data', 'options' => ['' => 'Choose']]]],
	[1 => [['name' => 'voltage', 'type' => 'number', 'attribs' => ['data-min-exclusive' => 'invalid']]]],
	[1 => [['name' => 'parent', 'type' => 'select', 'options' => ['a' => 'A']], ['name' => 'child', 'type' => 'text', 'depends_on' => 'parent', 'depends_value' => ['a', 'missing']]]],
] as $invalid) {
	try { Shops_Model_FormConfig::normalize($invalid); throw new RuntimeException('Invalid pilot configuration accepted'); }
	catch(InvalidArgumentException $e) { checkDynamic(true, 'Invalid pilot configuration rejected'); }
}

class PilotSnapshotController extends Shops_InquiryController
{
	public function init() {}
	public function snapshot(array $data): array { return $this->buildInquiryAddressSnapshot($data); }
}
$controller = new PilotSnapshotController(new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase());
$snapshot = $controller->snapshot($address);
checkDynamic($snapshot['billingcountry'] === 'DE' && $snapshot['shippingcountry'] === 'CH' && $snapshot['shippingname1'] === 'Delivery GmbH' && $snapshot['shippingstreet'] === 'Delivery Street', 'Snapshot mixed billing and delivery');
$legacy = $address;
unset($legacy['lieferadresse_abweichend']);
checkDynamic($controller->snapshot($legacy)['shippingstreet'] === 'Billing Street', 'Legacy billing snapshot changed');
try { $controller->snapshot(array_replace($address, ['lieferadresse_abweichend' => 'unbekannt'])); throw new RuntimeException('Unknown delivery address offered'); }
catch(UnexpectedValueException $e) { checkDynamic(true, 'Unknown delivery address requires review'); }
echo 'PASS: '.($checks - $before).' pilot, layout, numeric, country and snapshot checks'.PHP_EOL;

if(preg_match('/^render-([123])$/D', $argv[1] ?? '', $match)) echo '<!--fixture-->'.pilotForm($configs['reference'] ?? $configs['synthetic'], (int)$match[1])->render();
