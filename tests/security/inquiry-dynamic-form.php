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
$nested = [1 => [[
	'name' => 'medium', 'type' => 'select', 'options' => ['Wasser', 'Wasser/Glykol'], 'required' => true, 'depends' => [
		'1' => [
			['name' => 'glykol_typ', 'type' => 'select', 'options' => ['EG', 'PG', 'Sonstiges'], 'required' => true, 'depends' => [
				'2' => [['name' => 'glykol_typ_sonstiges', 'type' => 'text', 'required' => true]],
			]],
			['name' => 'glykol_bezugsart', 'type' => 'select', 'options' => ['masse' => 'Masse', 'volumen' => 'Volumen'], 'depends' => [
				'volumen' => [['name' => 'glykol_bezugstemperatur', 'type' => 'number', 'required' => true]],
			]],
		],
	],
]]];
set_error_handler(static function(int $severity, string $message): bool {
	if($severity & (E_WARNING | E_NOTICE)) throw new ErrorException($message, 0, $severity);
	return false;
});
try {
	$nestedForm = new Shops_Form_DynamicForm($nested, 1);
	checkDynamic($nestedForm->isValid(['medium' => '0', 'glykol_typ' => ['forged'], 'glykol_typ_sonstiges' => ['forged'], 'glykol_bezugstemperatur' => ['forged']]), 'Inactive nested fields warned or blocked water');
	checkDynamic(!isset($nestedForm->getFilteredValues()['glykol_typ_sonstiges']), 'Inactive descendant leaked');
	checkDynamic(!$nestedForm->isValid(['medium' => '1', 'glykol_typ' => '2', 'glykol_bezugsart' => 'volumen']), 'Active nested requirements accepted empty');
	checkDynamic(isset($nestedForm->getErrors()['glykol_typ_sonstiges'], $nestedForm->getErrors()['glykol_bezugstemperatur']), 'Nested required errors missing');
	checkDynamic($nestedForm->isValid(['medium' => '1', 'glykol_typ' => '2', 'glykol_typ_sonstiges' => 'Custom fluid', 'glykol_bezugsart' => 'volumen', 'glykol_bezugstemperatur' => '0']), 'Nested zero temperature rejected');
	checkDynamic((string)$nestedForm->getFilteredValues()['glykol_bezugstemperatur'] === '0', 'Zero temperature lost');
	checkDynamic($nestedForm->isValid(['medium' => '1', 'glykol_typ' => '1', 'glykol_typ_sonstiges' => ['forged'], 'glykol_bezugsart' => 'masse', 'glykol_bezugstemperatur' => ['forged']]), 'Inactive second-level fields blocked mass request');
	checkDynamic(!isset($nestedForm->getFilteredValues()['glykol_typ_sonstiges'], $nestedForm->getFilteredValues()['glykol_bezugstemperatur']), 'Changed nested selection retained stale fields');
	checkDynamic($nestedForm->isValid(['medium' => '0']), 'Repeated water validation failed');
	$nestedForm->setValues(['medium' => '1', 'glykol_typ' => '2', 'glykol_typ_sonstiges' => 'Session value', 'glykol_bezugsart' => 'volumen', 'glykol_bezugstemperatur' => '0']);
	checkDynamic($nestedForm->isValid($nestedForm->getValues()), 'Restored session values rejected');
} finally {
	restore_error_handler();
}
$zeroForm = new Shops_Form_DynamicForm([1 => [['name' => 'temperature', 'type' => 'number', 'format' => ['type' => 'decimal']]]], 1);
checkDynamic($zeroForm->isValid(['temperature' => '0,0']) && $zeroForm->getFilteredValues()['temperature'] === 0.0, 'Decimal format turned a valid numeric zero into null');
echo "PASS: $checks dynamic inquiry dependency, defaults and configuration checks\n";

if(($argv[1] ?? '') === 'render') echo '<!--fixture-->'.(new Shops_Form_DynamicForm($fields, 1))->render();
if(($argv[1] ?? '') === 'render-nested') echo '<!--fixture-->'.(new Shops_Form_DynamicForm($nested, 1))->render();
