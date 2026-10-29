<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/contact-inquiries.php (configuration and rendering checks only).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/';
	$path = $root.$base.strtolower(array_shift($parts)).'s/'.implode('/', $parts).'.php';
	if (is_file($path)) require_once $path;
});

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

function inquiryTenantMatches(array $where, array $row): bool
{
	foreach ($where as $condition) {
		$condition = trim(str_replace(['AND ', '`'], '', $condition));
		if ($condition[0] === '(' && substr($condition, -1) === ')') {
			$condition = substr($condition, 1, -1);
		}
		if (!preg_match('/^([a-z]+) = (\d+)$/D', $condition, $match)) {
			throw new RuntimeException('Unsupported fixture predicate: '.$condition);
		}
		if ((int)($row[$match[1]] ?? 0) !== (int)$match[2]) return false;
	}

	return true;
}

class ContactInquiryAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $rows = [];
	protected function _connect() {}
	public function describeTable($tableName, $schemaName = null)
	{
		$metadata = [];
		foreach (array_keys($this->rows[0]) as $column) {
			$metadata[$column] = [
				'COLUMN_NAME' => $column,
				'DATA_TYPE' => 'varchar',
				'PRIMARY' => $column === 'id',
				'PRIMARY_POSITION' => 1,
				'IDENTITY' => $column === 'id',
			];
		}
		return $metadata;
	}
	public function query($sql, $bind = [])
	{
		check($sql instanceof Zend_Db_Table_Select && !$bind, 'Unexpected inquiry fixture query');
		$sql->assemble();
		$rows = array_values(array_filter(
			$this->rows,
			static fn(array $row): bool => inquiryTenantMatches($sql->getPart(Zend_Db_Select::WHERE), $row)
		));
		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode) { return $this->rows; }
		};
	}
}

$adapter = new ContactInquiryAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
$adapter->rows = [
	['id' => 1, 'formid' => 0, 'shopid' => 11, 'token' => 'own', 'data' => '{}', 'status' => 'new', 'clientid' => 7, 'created' => null, 'createdby' => 0, 'modified' => null, 'modifiedby' => 0, 'deleted' => 0],
	['id' => 2, 'formid' => 0, 'shopid' => 12, 'token' => 'foreign', 'data' => '{}', 'status' => 'new', 'clientid' => 8, 'created' => null, 'createdby' => 0, 'modified' => null, 'modifiedby' => 0, 'deleted' => 0],
];
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 10]);
Zend_Registry::set('Client', ['id' => 10, 'parentid' => 9, 'modules' => ['contacts' => 6, 'shops' => 7]]);
$inquiryDb = new Contacts_Model_DbTable_Inquiry();
check($inquiryDb->getClientId() === 7, 'Inquiry model did not select the authorized shop data owner');
check($inquiryDb->getById(1)['token'] === 'own', 'Authorized shared inquiry was not available');
check($inquiryDb->getById(2) === null, 'Inquiry from another tenant was exposed');

$config = Contacts_Model_Entity_Inquiry::listConfig();
check($config['tableClass'] === 'Contacts_Model_DbTable_Inquiry', 'Inquiry list does not use the managed inquiry table');
check($config['clientModule'] === 'shops', 'Inquiry list does not follow the shop tenant owner');
check(array_keys($config['filters']) === ['shopid', 'inquirytype', 'status'], 'Inquiry filters are incomplete');
check($config['normalizers']['data'] === 'json', 'Inquiry payload is not decoded for list rendering');
$typeSelect = $adapter->select()->from(['i' => 'shopinquirydata']);
Contacts_Model_Entity_Inquiry::applyInquiryTypeFilter($typeSelect, 'technical_inquiry');
check(str_contains($typeSelect->assemble(), 'i.formid > 0'), 'Technical inquiry filter was not applied');

$formConfig = json_encode([
	'details' => [
		[
			'name' => 'application',
			'type' => 'select',
			'label' => 'Application',
			'options' => ['cooling' => 'Cooling'],
			'depends' => [
				'cooling' => [[
					'name' => 'cooling_power',
					'type' => 'text',
					'label' => 'Cooling power',
				]],
			],
		],
	],
]);
$detail = (new Contacts_Service_InquiryView())->build([
	'id' => 17,
	'formid' => 4,
	'form_title' => 'Cooling request',
	'form_fields' => $formConfig,
	'data' => json_encode([
		'firma' => 'Example GmbH',
		'application' => 'cooling',
		'cooling_power' => '42 kW',
		'custom_field' => 'Retained value',
		'origin' => 'technical_inquiry',
		'source_path' => '/request/cooling',
	]),
]);
$fields = array_column($detail['fields'], null, 'name');
check($detail['inquiry_type'] === 'Cooling request', 'Dynamic inquiry form title was lost');
check($detail['source_path'] === '/request/cooling', 'Inquiry origin path was lost');
check($fields['firma']['label'] === 'CONTACT_INQUIRIES_COMPANY', 'Known contact field label was not normalized');
check($fields['application']['value'] === 'Cooling', 'Configured option label was not resolved');
check($fields['cooling_power']['label'] === 'Cooling power', 'Dependent technical field definition was not resolved');
check($fields['custom_field']['value'] === 'Retained value', 'Unknown dynamic field was omitted');
check(!isset($fields['origin'], $fields['source_path']), 'Inquiry metadata was duplicated among form fields');

$list = new Contacts_Model_List_Inquiries();
$item = (object)[
	'data' => [
		'firma' => 'Example GmbH',
		'email' => 'mail@example.test',
		'source_path' => '/contact',
	],
	'form_title' => 'Technical request',
];
check($list->renderName($item) === 'Example GmbH', 'Inquiry list did not extract the company name');
check($list->renderEmail($item) === 'mail@example.test', 'Inquiry list did not extract the email address');
check($list->renderSubject($item) === 'Technical request', 'Inquiry list did not use the form title as subject fallback');
check($list->renderOrigin($item) === '/contact', 'Inquiry list did not extract the origin path');

$form = new Contacts_Form_Inquiry();
check($form->isValid(['status' => 'in_progress']), 'Supported inquiry status was rejected');
check(!$form->isValid(['status' => 'invalid']), 'Unsupported inquiry status was accepted');

$migration = file_get_contents($root.'/install/config/update-1.1.7-1.1.8.sql');
$structure = file_get_contents($root.'/install/config/structure.sql');
check(str_contains($migration, "ADD `status` varchar(32) NOT NULL DEFAULT 'new'"), 'Inquiry status migration is missing');
check(str_contains($structure, "`status` varchar(32) NOT NULL DEFAULT 'new'"), 'Inquiry status install schema is missing');
check(str_contains($structure, 'clientid_status_shopid_formid_deleted'), 'Inquiry management lookup index is missing');

echo "OK ({$checks} checks)\n";
