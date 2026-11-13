<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if(count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/';
	$path = $root.$base.strtolower(array_shift($parts)).'s/'.implode('/', $parts).'.php';
	if(is_file($path)) require_once $path;
});
$checks = 0;
function checkDynamic(bool $value, string $message): void {
	global $checks;
	$checks++;
	if(!$value) throw new RuntimeException($message);
}
$fields = [1 => [
	['name' => 'medium', 'type' => 'select', 'required' => true, 'options' => ['Wasser', 'Wasser/Glykol'], 'depends' => [
		'Wasser/Glykol' => [
			['name' => 'glykol_typ', 'type' => 'select', 'label' => 'Glykoltyp', 'required' => true, 'options' => ['EG' => 'Ethylenglykol', 'PG' => 'Propylenglykol']],
			['name' => 'glykol_anteil', 'type' => 'number', 'label' => 'Glykolanteil', 'required' => true, 'attribs' => ['min' => 0, 'max' => 60]],
		],
	]],
	['name' => 'umgebung_temp_max', 'type' => 'number', 'description' => 'Standard 35 °C'],
	['name' => 'pumpe', 'type' => 'select', 'options' => ['none' => 'Keine', 'pump' => 'Pumpe']],
	['name' => 'geraeuschreduktion', 'type' => 'checkbox'],
]];
$form = new Shops_Form_DynamicForm($fields, 1);
checkDynamic($form->isValid(['medium' => '0', 'glykol_typ' => ['tampered'], 'glykol_anteil' => -100]), 'Inactive fields blocked validation');
$values = $form->getFilteredValues();
checkDynamic(!isset($values['glykol_typ'], $values['glykol_anteil']), 'Inactive inputs reached filtered values');
checkDynamic(!isset($values['umgebung_temp_max']) || $values['umgebung_temp_max'] === null || $values['umgebung_temp_max'] === '', 'Description invented a default');
checkDynamic(!$form->isValid(['medium' => '1']), 'Missing active dependent requirements accepted');
checkDynamic(isset($form->getErrors()['glykol_typ'], $form->getErrors()['glykol_anteil']), 'Dependent required errors missing');
checkDynamic($form->isValid(['medium' => '1', 'glykol_typ' => 'PG', 'glykol_anteil' => '30']), 'Numeric option did not activate dependencies');
checkDynamic($form->getFilteredValues()['glykol_typ'] === 'PG', 'Active value was dropped');
checkDynamic(!$form->isValid(['medium' => 'other']), 'Unknown selection accepted');
checkDynamic(!$form->isValid(['medium' => ['1']]), 'Array trigger accepted');
checkDynamic(!$form->isValid(['medium' => '1', 'glykol_typ' => 'unknown', 'glykol_anteil' => '30']), 'Unknown dependent selection accepted');
checkDynamic($form->isValid(['medium' => '0']), 'Deactivation retained old required state');
checkDynamic(!isset($form->getFilteredValues()['glykol_typ']), 'Deactivated stale value survived repeat validation');
$html = $form->render();
checkDynamic((bool)preg_match('/<div[^>]*class="[^"]*dependent-field[^>]*data-depends-on="medium"[^>]*data-depends-value="1"[^>]*>\s*<label/s', $html), 'Dependency did not wrap label and field');
$explicit = $fields;
$explicit[1][0]['options'] = ['water' => 'Wasser', 'glycol' => 'Wasser/Glykol'];
$explicit[1][0]['depends'] = ['glycol' => $fields[1][0]['depends']['Wasser/Glykol']];
$form = new Shops_Form_DynamicForm($explicit, '1');
checkDynamic($form->isValid(['medium' => 'glycol', 'glykol_typ' => 'EG', 'glykol_anteil' => '20']), 'Explicit dependency value failed');
$explicit[1][1]['default'] = 35;
$form = new Shops_Form_DynamicForm($explicit, '1');
checkDynamic($form->isValid(['medium' => 'water']) && (float)$form->getFilteredValues()['umgebung_temp_max'] === 35.0, 'Explicit configured default not applied');
foreach([
	[1 => [['name' => 'same', 'type' => 'text']], 2 => [['name' => 'same', 'type' => 'text']]],
	[1 => [['name' => 'a', 'type' => 'text', 'depends_on' => 'missing', 'depends_value' => '1']]],
	[1 => [['name' => 'csrf_token', 'type' => 'hidden']]],
	[1 => [['name' => 'x', 'type' => 'select', 'options' => ['a' => 'A'], 'depends' => ['missing' => [['name' => 'y', 'type' => 'text']]]]]],
	[1 => [['name' => 'x', 'type' => 'select', 'options' => ['a' => 'A'], 'depends_on' => 'y', 'depends_value' => 'b'], ['name' => 'y', 'type' => 'select', 'options' => ['b' => 'B'], 'depends_on' => 'x', 'depends_value' => 'a']]],
] as $invalid) {
	try { Shops_Model_FormConfig::normalize($invalid); throw new RuntimeException('Invalid configuration accepted'); }
	catch(InvalidArgumentException $e) { checkDynamic(true, 'Invalid config'); }
}
$referencePath = '/tmp/dewawi-inquiry-referenzen/form-865.fields.json';
if(is_file($referencePath)) {
	$reference = Shops_Model_FormConfig::decode(file_get_contents($referencePath));
	$normalized = Shops_Model_FormConfig::normalize($reference);
	checkDynamic(array_keys($normalized) === [1, 2, 3], 'Reference steps changed');
	$referenceForm = new Shops_Form_DynamicForm($reference, 1);
	checkDynamic($referenceForm->isValid(['einsatzbereich' => '0', 'kuehlleistung' => 20, 'vorlauf_temp' => 10, 'medium' => '1', 'glykol_typ' => '1']), 'Real water/glycol reference rejected');
	checkDynamic((int)$referenceForm->getFilteredValues()['glykol_anteil'] === 30, 'Real glycol default was lost');
	checkDynamic($referenceForm->isValid(['einsatzbereich' => '0', 'kuehlleistung' => 20, 'vorlauf_temp' => 10, 'medium' => '0', 'glykol_anteil' => 60]), 'Real water reference rejected');
	checkDynamic(!isset($referenceForm->getFilteredValues()['glykol_anteil']), 'Real hidden glycol default leaked');
	checkDynamic(empty($referenceForm->getFilteredValues()['umgebung_temp_max']), 'Real description silently supplied 35 degrees');
}
echo "PASS: $checks dynamic inquiry dependency, defaults and configuration checks\n";

if(($argv[1] ?? '') === 'render') echo '<!--fixture-->'.(new Shops_Form_DynamicForm($fields, 1))->render();
