<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/shop-inquiry-calculation.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});

require_once $root . '/application/modules/shops/controllers/InquiryController.php';

$checks = 0;
function checkInquiryCalculation(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

function inquiryCalculationMatches(array $where, array $row): bool
{
	foreach ($where as $condition) {
		$condition = trim((string)preg_replace('/^AND /', '', $condition));
		if ($condition[0] === '(' && substr($condition, -1) === ')') $condition = substr($condition, 1, -1);
		if (!preg_match('/^([a-z]+) = (.*)$/', $condition, $match)) throw new RuntimeException('Unsupported calculation predicate: ' . $condition);
		$value = trim($match[2], " '");
		if ((string)($row[$match[1]] ?? '') !== $value) return false;
	}

	return true;
}

class ShopInquiryCalculationAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $rows = [];
	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }

	public function describeTable($tableName, $schemaName = null)
	{
		$metadata = [];
		foreach (array_keys($this->rows[$tableName][0]) as $column) {
			$metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
		}
		return $metadata;
	}

	public function query($sql, $bind = [])
	{
		if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected calculation database access');
		$sql->assemble();
		$from = $sql->getPart(Zend_Db_Select::FROM);
		$table = reset($from)['tableName'];
		$rows = array_values(array_filter($this->rows[$table], static fn(array $row): bool => inquiryCalculationMatches($sql->getPart(Zend_Db_Select::WHERE), $row)));
		if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode) { return $this->rows; }
		};
	}
}

class ShopInquiryCalculationController extends Shops_InquiryController
{
	public function init() {}

	public function decimal($value): ?float
	{
		return self::parseDecimal($value);
	}

	public function matches(array $references, float $target, float $required, float $tolerance): array
	{
		return $this->findMatchingReferences($references, $target, $required, $tolerance);
	}

	public function suggestions(array $fields, array $values): array
	{
		return $this->calculateSuggestions($fields, $values);
	}

	public function steps(array $config): array
	{
		$this->formConfig = $config;
		return $this->getStepOrder();
	}

	public function discard(array $steps, int $index, array $data, array $suggestions): array
	{
		$this->formDataSession = new stdClass();
		$this->formDataSession->formData = $data;
		$this->formDataSession->suggestions = $suggestions;
		$this->formDataSession->requestValues = ['kuehlleistung' => 9.8];
		$this->formDataSession->suggestionStep = '1';
		$this->discardFollowingSteps($steps, $index);
		return (array)$this->formDataSession;
	}

	public function validSku(string $sku, array $suggestions): bool
	{
		$this->formDataSession = new stdClass();
		$this->formDataSession->suggestions = $suggestions;
		return $this->isValidSuggestedSku($sku);
	}
}

class ShopInquiryNumericPostController extends Shops_InquiryController
{
	public function init()
	{
		$this->_siteContext = Zend_Registry::get('SiteContext');
		$this->_site = ['id' => 120, 'clientid' => 100];
		$this->formRow = ['id' => 865, 'title' => 'Numeric steps', 'description' => ''];
		$this->formConfig = [
			1 => [['name' => 'project', 'type' => 'text', 'required' => true]],
			2 => [['name' => 'email', 'type' => 'email', 'required' => true]],
		];
		$this->formDataSession = new stdClass();
		$this->formDataSession->formData = [];
		$this->formDataSession->inquiryToken = str_repeat('a', 32);
		$this->formDataSession->lastInquiryToken = '';
		$this->csrfToken = 'csrf-value';
		$this->inquiryToken = str_repeat('a', 32);
		$this->view = new Zend_View();
		$this->view->setScriptPath(dirname(__DIR__, 2) . '/application/modules/shops/views/scripts');
	}
}

$adapter = new ShopInquiryCalculationAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
$attributeTemplate = [
	'id' => 1, 'parentid' => 1, 'atrsetid' => 0, 'sku' => 'FORM_CALC', 'title' => '', 'value' => '', 'description' => '',
	'ordering' => 1, 'clientid' => 100, 'deleted' => 0,
];
$adapter->rows = [
	'shopinquirydata' => [[
		'id' => 1, 'formid' => 865, 'shopid' => 120, 'token' => str_repeat('f', 32), 'data' => '{}', 'status' => 'new',
		'contactid' => null, 'quoteid' => null, 'clientid' => 100, 'deleted' => 1,
	]],
	'itematr' => [
		array_replace($attributeTemplate, ['id' => 1, 'title' => 'temperaturfaktor_20 / 30', 'value' => '1,41']),
		array_replace($attributeTemplate, ['id' => 2, 'title' => 'referenzleistung', 'value' => '7', 'description' => 'ZCE110']),
		array_replace($attributeTemplate, ['id' => 3, 'sku' => 'OTHER', 'title' => 'referenzleistung', 'value' => '100', 'description' => 'FOREIGN']),
	],
	'item' => [[
		'id' => 10, 'shopid' => 120, 'shopcatid' => 20, 'sku' => 'ZCE110', 'title' => 'ZCE 110', 'description' => 'Chiller',
		'price' => '1000.00', 'specialprice' => '900.00', 'taxid' => 1, 'clientid' => 100, 'deleted' => 0,
	]],
	'category' => [[
		'id' => 20, 'shopid' => 120, 'type' => 'shop', 'activated' => 1, 'clientid' => 100, 'deleted' => 0,
	]],
];
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100]));

$controller = new ShopInquiryCalculationController(new Zend_Controller_Request_Simple(), new Zend_Controller_Response_Http());
checkInquiryCalculation($controller->decimal('0,69') === 0.69, 'Decimal comma below one was parsed incorrectly');
checkInquiryCalculation($controller->decimal('1,41') === 1.41, 'Decimal comma factor was parsed incorrectly');
checkInquiryCalculation($controller->decimal('4.6') === 4.6 && $controller->decimal('9,8') === 9.8, 'Supported decimal formats differ');
checkInquiryCalculation($controller->decimal('0') === 0.0 && $controller->decimal('-5,5') === -5.5, 'Zero or negative temperature was lost');
checkInquiryCalculation($controller->decimal('1,2invalid') === null && $controller->decimal('1.2.3') === null, 'Invalid numeric input was rewritten into a number');
checkInquiryCalculation($controller->steps([1 => [], 2 => []]) === ['1', '2'], 'Numeric JSON step keys were not normalized to strings');

$references = [
	['title' => 'referenzleistung', 'value' => '7', 'description' => 'ZCE110'],
	['title' => 'referenzleistung', 'value' => 'invalid', 'description' => 'INVALID'],
];
$matches = $controller->matches($references, 1.41, 9.8, 0.1);
checkInquiryCalculation(count($matches) === 1 && $matches[0]['description'] === 'ZCE110' && $matches[0]['value'] === 9.87, 'Known 1.41 x 7 cooling result is incorrect');
checkInquiryCalculation($controller->matches([
	['title' => 'referenzleistung', 'value' => '7', 'description' => 'DUPLICATE'],
	['title' => 'referenzleistung', 'value' => '8', 'description' => 'DUPLICATE'],
], 1.41, 9.8, 0.2) === [], 'Ambiguous calculation references were accepted');

$fields = [[
	'name' => 'temperaturfaktor',
	'type' => 'text',
	'calculate' => ['target' => 'vorlauf_temp / umgebung_temp_max', 'reference' => 'referenzleistung', 'tolerance' => '0,1'],
]];
$suggestions = $controller->suggestions($fields, ['vorlauf_temp' => '20', 'umgebung_temp_max' => '30', 'kuehlleistung' => '9,8']);
checkInquiryCalculation(count($suggestions) === 1 && $suggestions[0]['description'] === 'ZCE110', 'FORM_CALC suggestion did not resolve to the public item');
checkInquiryCalculation($controller->suggestions($fields, ['vorlauf_temp' => '20x', 'umgebung_temp_max' => '30', 'kuehlleistung' => '9,8']) === [], 'Invalid calculation input produced a suggestion');
checkInquiryCalculation($controller->suggestions(array_merge($fields, $fields), ['vorlauf_temp' => '20', 'umgebung_temp_max' => '30', 'kuehlleistung' => '9,8']) === [], 'Ambiguous configured calculations produced suggestions');

$session = $controller->discard(['1', '2', '3'], 0, ['1' => ['a' => 1], '2' => ['sku' => 'ZCE110'], '3' => ['email' => 'a@example.test']], $suggestions);
checkInquiryCalculation(array_keys($session['formData']) === [1] && !isset($session['suggestions'], $session['requestValues'], $session['suggestionStep']), 'Changing an earlier step retained dependent state');
checkInquiryCalculation($controller->validSku('ZCE110', $suggestions), 'Current public suggested SKU was rejected');
checkInquiryCalculation(!$controller->validSku('MANIPULATED', $suggestions), 'SKU outside the current suggestions was accepted');
$adapter->rows['category'][0]['activated'] = 0;
checkInquiryCalculation(!$controller->validSku('ZCE110', $suggestions), 'Unavailable suggested item was accepted');

Zend_Controller_Action_HelperBroker::addPath($root . '/library/Zend/Layout/Controller/Action/Helper', 'Zend_Layout_Controller_Action_Helper');
Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts']);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$postRequest = new Zend_Controller_Request_HttpTestCase();
$postRequest->setMethod('POST')->setPost([
	'step' => '1',
	'csrf_token' => 'csrf-value',
	'inquiry_token' => str_repeat('a', 32),
	'project' => 'Cooling project',
]);
$postResponse = new Zend_Controller_Response_HttpTestCase();
Zend_Controller_Front::getInstance()->setResponse($postResponse);
$postController = new ShopInquiryNumericPostController($postRequest, $postResponse);
$postController->indexAction();
$postPayload = json_decode($postResponse->getBody(), true);
checkInquiryCalculation(($postPayload['status'] ?? '') === 'ok' && str_contains((string)($postPayload['formHtml'] ?? ''), 'name="email"'), 'Controller POST rejected a numeric JSON step key');

echo "PASS: $checks inquiry step, decimal, FORM_CALC, state and SKU validation cases\n";
