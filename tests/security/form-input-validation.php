<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/form-input-validation.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

$checks = 0;
function checkFormInput(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if(!$condition) throw new RuntimeException($message);
}

class FormInputValidationFixture extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement(['name' => 'amount', 'type' => 'text', 'format' => ['type' => 'decimal', 'precision' => 4]]);
		$this->addElement(['name' => 'quantity', 'type' => 'text', 'format' => ['type' => 'int']]);
		$this->addElement(['name' => 'day', 'type' => 'text', 'format' => ['type' => 'date', 'pattern' => 'Y-m-d', 'displayPattern' => 'd.m.Y']]);
		$this->addElement(['name' => 'state', 'type' => 'select', 'options' => ['open' => 'Open'], 'format' => ['type' => 'string']]);
	}
}

$form = new FormInputValidationFixture();
$valid = ['amount' => '1.234,5678 €', 'quantity' => '12', 'day' => '31.10.2026', 'state' => 'open'];
checkFormInput($form->isValid($valid), 'Valid localized values were rejected');
$filtered = $form->getFilteredValues();
checkFormInput(abs($filtered['amount'] - 1234.5678) < 0.000001 && $filtered['quantity'] === 12 && $filtered['day'] === '2026-10-31', 'Valid values were filtered incorrectly');
checkFormInput($form->isValidPartial(['amount' => '0']), 'Decimal zero was rejected');
checkFormInput($form->getFilteredValuesPartial(['amount' => '0'])['amount'] === null, 'Existing zero-to-null storage convention changed');

foreach([
	['amount' => '12abc'],
	['amount' => ['12']],
	['quantity' => '1.5'],
	['quantity' => ['1']],
	['day' => '31.02.2026'],
	['day' => ['2026-10-31']],
	['state' => ['open']],
] as $invalid) {
	checkFormInput(!$form->isValidPartial($invalid), 'Malformed partial form value was accepted');
	checkFormInput(isset($form->getErrors()[array_key_first($invalid)]), 'Malformed partial form value has no field error');
}

checkFormInput(!$form->isValid(['amount' => '12abc', 'quantity' => '1.5', 'day' => '31.02.2026', 'state' => ['open']]), 'Malformed full form values were accepted');
checkFormInput(count($form->getErrors()) === 4, 'Full form validation did not report every malformed field');

echo 'PASS: ' . $checks . " full-form, AJAX, decimal, integer and date validation cases\n";
