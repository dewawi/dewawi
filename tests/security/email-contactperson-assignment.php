<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/email-contactperson-assignment.php (in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());

spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if (count($parts) >= 3) {
		$module = array_shift($parts);
		$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
		$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
		if (is_file($path)) require_once $path;
	}
});

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

function assignmentWhereMatches(string $where, array $row): bool
{
	$where = trim(preg_replace('/^AND /', '', str_replace('`', '', $where)));
	if ($where !== '' && $where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);

	if (preg_match('/^([a-z0-9_]+) IN \(([^)]*)\)$/i', $where, $match)) {
		$values = array_map(static fn($value) => trim($value, " '\""), explode(',', $match[2]));
		return array_key_exists($match[1], $row) && in_array((string)$row[$match[1]], $values, true);
	}

	if (!preg_match('/^([a-z0-9_]+) = (.*)$/i', $where, $match)) {
		throw new RuntimeException('Unsupported fixture predicate: ' . $where);
	}

	return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '\"");
}

class EmailContactpersonAssignmentAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $rows = [];
	public bool $failEmailUpdate = false;
	private int $lastId = 100;
	private ?array $transactionRows = null;
	private ?int $transactionLastId = null;

	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }

	public function beginTransaction()
	{
		$this->transactionRows = $this->rows;
		$this->transactionLastId = $this->lastId;
		return true;
	}

	public function commit()
	{
		$this->transactionRows = null;
		$this->transactionLastId = null;
		return true;
	}

	public function rollBack()
	{
		if ($this->transactionRows !== null) {
			$this->rows = $this->transactionRows;
			$this->lastId = (int)$this->transactionLastId;
		}
		$this->transactionRows = null;
		$this->transactionLastId = null;
		return true;
	}

	public function resetLastId(int $lastId): void
	{
		$this->lastId = $lastId;
	}

	public function describeTable($tableName, $schemaName = null)
	{
		$columns = ['id', 'clientid', 'parentid', 'module', 'controller', 'ordering', 'deleted', 'created', 'createdby', 'modified', 'modifiedby'];
		foreach ($this->rows[$tableName] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
		$metadata = [];
		foreach (array_unique($columns) as $column) {
			$metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
		}
		return $metadata;
	}

	public function query($sql, $bind = [])
	{
		if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
		$sql->assemble();
		$from = $sql->getPart(Zend_Db_Select::FROM);
		$table = reset($from)['tableName'];
		$rows = array_values(array_filter($this->rows[$table] ?? [], static function(array $row) use ($sql): bool {
			foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
				if (!assignmentWhereMatches($where, $row)) return false;
			}
			return true;
		}));

		foreach (array_reverse($sql->getPart(Zend_Db_Select::ORDER)) as $order) {
			$field = trim((string)$order[0], '`');
			$direction = strtoupper((string)$order[1]) === 'DESC' ? -1 : 1;
			usort($rows, static fn($left, $right) => $direction * (($left[$field] ?? null) <=> ($right[$field] ?? null)));
		}

		if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);

		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode) { return $this->rows; }
		};
	}

	public function insert($table, array $bind)
	{
		$bind['id'] = ++$this->lastId;
		$bind += ['deleted' => 0];
		$this->rows[$table][] = $bind;
		return 1;
	}

	public function update($table, array $bind, $where = '')
	{
		if ($table === 'email' && $this->failEmailUpdate) {
			$this->failEmailUpdate = false;
			throw new RuntimeException('Forced email update failure');
		}

		$where = is_array($where) ? $where : preg_split('/\)?\s+AND\s+\(?/i', trim((string)$where, '() '));
		$updated = 0;
		if (!isset($this->rows[$table])) return 0;
		foreach ($this->rows[$table] as &$row) {
			foreach ($where as $condition) {
				if (!assignmentWhereMatches($condition, $row)) continue 2;
			}
			$row = array_replace($row, $bind);
			$updated++;
		}
		unset($row);
		return $updated;
	}

	public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

require_once $root . '/application/modules/contacts/controllers/EmailController.php';
require_once $root . '/application/controllers/helpers/MultiEntityLoader.php';

class EmailContactpersonAssignmentView extends Zend_View
{
	public function translate($message) { return $message; }
}

class EmailContactpersonAssignmentController extends Contacts_EmailController
{
	public function init()
	{
		$this->_user = Zend_Registry::get('User');
		$this->view = new EmailContactpersonAssignmentView();
	}
}

class EmailContactpersonCampaignTestService extends Campaigns_Service_CampaignRecipientService
{
	public function contactpersons(array $contacts): array
	{
		return $this->getContactPersonsByCompany($contacts);
	}
}

class EmailContactpersonExportTestService extends Contacts_Service_ContactExportService
{
	protected function loadRelatedData(array $contacts, int $clientId, array $context): array
	{
		$contactIds = array_column($contacts, 'id');
		$persons = (new Contacts_Model_DbTable_Contactperson())->setClientId($clientId)->getByParentIds($contactIds, 'contacts', 'contact');
		$personIds = array_column($persons, 'id');
		$emails = (new Contacts_Model_DbTable_Email())->setClientId($clientId)->getByParentIds($personIds, 'contacts', 'contactperson');
		$group = static function(array $rows): array {
			$out = [];
			foreach ($rows as $row) $out[(int)$row['parentid']][] = $row;
			return $out;
		};

		return [
			'addresses' => [],
			'phones' => [],
			'emails' => [],
			'internets' => [],
			'contactpersons' => $group($persons),
			'contactpersonEmails' => $group($emails),
			'categories' => [],
			'tags' => [],
			'roles' => [],
		];
	}
}

$adapter = new EmailContactpersonAssignmentAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/contacts/controllers', 'contacts');
Zend_Layout::startMvc();
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;

function assignmentFixtures(): void
{
	global $adapter;
	$adapter->resetLastId(100);
	$adapter->failEmailUpdate = false;
	$adapter->rows = [
		'contact' => [
			['id' => 1, 'contactid' => 39792, 'name1' => 'ADVANTIX S.P.A.', 'clientid' => 7, 'deleted' => 0],
			['id' => 2, 'contactid' => 40000, 'name1' => 'Foreign', 'clientid' => 8, 'deleted' => 0],
			['id' => 3, 'contactid' => 40001, 'name1' => 'Deleted', 'clientid' => 7, 'deleted' => 1],
			['id' => 4, 'contactid' => 40002, 'name1' => 'Other Own Contact', 'clientid' => 7, 'deleted' => 0],
			['id' => 70, 'contactid' => 70000, 'name1' => 'Shared Parent', 'clientid' => 70, 'deleted' => 0],
		],
		'contactperson' => [
			['id' => 10, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'firstname' => 'Erika', 'lastname' => 'Musterfrau', 'salutation' => 'Frau', 'title' => '', 'position' => '', 'department' => ''],
			['id' => 11, 'parentid' => 2, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 8, 'deleted' => 0, 'ordering' => 1, 'firstname' => 'Foreign', 'lastname' => 'Person', 'salutation' => '0', 'title' => '', 'position' => '', 'department' => ''],
			['id' => 12, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 1, 'ordering' => 2, 'firstname' => 'Deleted', 'lastname' => 'Person', 'salutation' => '0', 'title' => '', 'position' => '', 'department' => ''],
			['id' => 14, 'parentid' => 4, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'firstname' => 'Other', 'lastname' => 'Person', 'salutation' => '0', 'title' => '', 'position' => '', 'department' => ''],
			['id' => 71, 'parentid' => 70, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 70, 'deleted' => 0, 'ordering' => 1, 'firstname' => 'Parent', 'lastname' => 'Person', 'salutation' => '0', 'title' => '', 'position' => '', 'department' => ''],
		],
		'email' => [
			['id' => 20, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'david@example.invalid', 'password' => 'secret-hash', 'suppressed' => 0, 'suppressionreason' => null, 'suppresseddate' => null, 'created' => '2025-01-01 12:00:00', 'createdby' => 5],
			['id' => 21, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 2, 'email' => 'blocked@example.invalid', 'password' => 'blocked-secret', 'suppressed' => 1, 'suppressionreason' => 'manual', 'suppresseddate' => '2025-02-03 14:00:00', 'created' => '2025-02-01 12:00:00', 'createdby' => 6],
			['id' => 22, 'parentid' => 2, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 8, 'deleted' => 0, 'ordering' => 1, 'email' => 'foreign@example.invalid', 'password' => 'foreign-secret', 'suppressed' => 0],
			['id' => 23, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 1, 'ordering' => 3, 'email' => 'deleted@example.invalid', 'password' => 'deleted-secret', 'suppressed' => 0],
			['id' => 24, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'assigned@example.invalid', 'password' => 'assigned-secret', 'suppressed' => 0],
			['id' => 25, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 4, 'email' => 'rollback@example.invalid', 'password' => 'rollback-secret', 'suppressed' => 0],
			['id' => 26, 'parentid' => 4, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'other@example.invalid', 'password' => 'other-secret', 'suppressed' => 0],
			['id' => 72, 'parentid' => 70, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 70, 'deleted' => 0, 'ordering' => 1, 'email' => 'parent@example.invalid', 'password' => 'parent-secret', 'suppressed' => 0],
		],
		'emailmessage' => [
			['id' => 90, 'contactid' => 1, 'recipient' => 'david@example.invalid', 'clientid' => 7, 'deleted' => 0, 'subject' => 'History'],
		],
	];
	Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
	Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'modules' => ['contacts' => 7]]);
}

function requestAssignment(array $post): array
{
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = $post;
	$_GET = [];
	$request = new Zend_Controller_Request_Http();
	$request->setModuleName('contacts')->setControllerName('email')->setActionName('assigncontactperson')->setPost($post);
	$response = new Zend_Controller_Response_Http();
	Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);

	ob_start();
	try {
		$controller = new EmailContactpersonAssignmentController($request, $response);
		$controller->assigncontactpersonAction();
	} finally {
		$output = ob_get_clean();
	}

	return [$response->getHttpResponseCode(), json_decode($response->getBody() . $output, true)];
}

function rowById(array $rows, int $id): ?array
{
	foreach ($rows as $row) {
		if ((int)$row['id'] === $id) return $row;
	}
	return null;
}

assignmentFixtures();
$emailBefore = rowById($adapter->rows['email'], 20);
$historyBefore = $adapter->rows['emailmessage'];
$emailCount = count($adapter->rows['email']);
[, $response] = requestAssignment([
	'contactid' => 1,
	'emailid' => 20,
	'assignment_type' => 'new',
	'salutation' => 'Herr',
	'title' => '',
	'firstname' => 'David',
	'lastname' => 'Matani',
	'position' => 'Sales',
	'department' => 'Distribution',
]);
check($response['ok'] === true && (int)$response['emailid'] === 20, 'Existing company email was not assigned to a new contact person: ' . json_encode($response));
$personId = (int)$response['contactpersonid'];
$person = rowById($adapter->rows['contactperson'], $personId);
$emailAfter = rowById($adapter->rows['email'], 20);
check($person && $person['firstname'] === 'David' && $person['lastname'] === 'Matani' && (int)$person['parentid'] === 1, 'New contact person was not created below the company');
check((int)$emailAfter['id'] === 20 && (int)$emailAfter['parentid'] === $personId && $emailAfter['controller'] === 'contactperson', 'Email row was copied or linked to the wrong parent');
foreach (['email', 'password', 'suppressed', 'suppressionreason', 'suppresseddate', 'created', 'createdby', 'clientid'] as $field) {
	check($emailAfter[$field] === $emailBefore[$field], 'Email field changed during assignment: ' . $field);
}
check(count($adapter->rows['email']) === $emailCount, 'Email assignment created or deleted an email row');
check($adapter->rows['emailmessage'] === $historyBefore, 'Email history changed during assignment');
$recipients = (new Contacts_Model_DbTable_Email())->getContactRecipients(1);
$movedRecipient = array_values(array_filter($recipients, static fn(array $row): bool => (int)$row['id'] === 20));
check(count($movedRecipient) === 1 && (int)$movedRecipient[0]['contactpersonid'] === $personId && $movedRecipient[0]['firstname'] === 'David', 'Email recipient selection did not recognize the new contact person');
$campaignPersons = (new EmailContactpersonCampaignTestService())->contactpersons([['id' => 1]]);
$campaignPerson = array_values(array_filter($campaignPersons[1], static fn(array $row): bool => (int)$row['id'] === $personId));
check(count($campaignPerson) === 1 && $campaignPerson[0]['email_list'] === 'david@example.invalid', 'Campaign recipient selection did not recognize the moved email');
$exportRows = (new EmailContactpersonExportTestService())->createList(
	Contacts_Service_ContactExportService::PROFILE_CONTACTPERSONS,
	[['id' => 1, 'clientid' => 7, 'contactid' => 39792, 'name1' => 'ADVANTIX S.P.A.', 'catid' => 0]]
)->getItems();
$exportPerson = array_values(array_filter($exportRows, static fn(array $row): bool => $row['firstname'] === 'David' && $row['lastname'] === 'Matani'));
check(count($exportPerson) === 1 && $exportPerson[0]['email'] === 'david@example.invalid', 'Contact-person export did not include the moved email');

assignmentFixtures();
$suppressedBefore = rowById($adapter->rows['email'], 21);
[, $response] = requestAssignment(['contactid' => 1, 'emailid' => 21, 'assignment_type' => 'existing', 'contactpersonid' => 10]);
$suppressedAfter = rowById($adapter->rows['email'], 21);
check($response['ok'] === true && (int)$suppressedAfter['parentid'] === 10, 'Company email was not assigned to an existing contact person');
foreach (['id', 'email', 'password', 'suppressed', 'suppressionreason', 'suppresseddate', 'clientid'] as $field) {
	check($suppressedAfter[$field] === $suppressedBefore[$field], 'Suppression or identity field changed: ' . $field);
}
check(!array_filter((new Contacts_Model_DbTable_Email())->getContactRecipients(1), static fn(array $row): bool => (int)$row['id'] === 21), 'Suppressed email became eligible after assignment');

foreach ([
	['contactid' => 1, 'emailid' => 22, 'assignment_type' => 'existing', 'contactpersonid' => 10],
	['contactid' => 1, 'emailid' => 23, 'assignment_type' => 'existing', 'contactpersonid' => 10],
	['contactid' => 1, 'emailid' => 24, 'assignment_type' => 'new', 'lastname' => 'Duplicate'],
	['contactid' => 1, 'emailid' => 26, 'assignment_type' => 'existing', 'contactpersonid' => 10],
	['contactid' => 3, 'emailid' => 20, 'assignment_type' => 'existing', 'contactpersonid' => 10],
	['contactid' => 1, 'emailid' => 20, 'assignment_type' => 'existing', 'contactpersonid' => 11],
	['contactid' => 1, 'emailid' => 20, 'assignment_type' => 'existing', 'contactpersonid' => 12],
	['contactid' => 1, 'emailid' => 20, 'assignment_type' => 'existing', 'contactpersonid' => 14],
	['contactid' => 1, 'emailid' => 20, 'assignment_type' => 'new', 'firstname' => 'Missing', 'lastname' => ''],
] as $invalidRequest) {
	assignmentFixtures();
	$before = $adapter->rows;
	[, $response] = requestAssignment($invalidRequest);
	check($response['ok'] === false && $response['message'] === 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID', 'Invalid or foreign assignment was accepted');
	check($adapter->rows === $before, 'Invalid assignment changed data');
}

assignmentFixtures();
$adapter->failEmailUpdate = true;
$before = $adapter->rows;
[, $response] = requestAssignment([
	'contactid' => 1,
	'emailid' => 25,
	'assignment_type' => 'new',
	'salutation' => '0',
	'firstname' => 'Rollback',
	'lastname' => 'Test',
]);
check($response['ok'] === false && $response['message'] === 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_FAILED', 'Forced update error did not return a controlled failure');
check($adapter->rows === $before, 'Failed email update did not roll back the new contact person');

assignmentFixtures();
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
[, $response] = requestAssignment(['contactid' => 70, 'emailid' => 72, 'assignment_type' => 'existing', 'contactpersonid' => 71]);
$parentEmail = rowById($adapter->rows['email'], 72);
check($response['ok'] === true && (int)$parentEmail['parentid'] === 71 && (int)$parentEmail['clientid'] === 70, 'Shared parent contact assignment used the wrong tenant');
$sharedForm = new Contacts_Form_Contact();
(new Application_Controller_Action_Helper_MultiEntityLoader())->populate($sharedForm, 70, 'contacts', 'contact', 70);
$sharedPersons = $sharedForm->getElement('contactperson')['rows'];
check(count($sharedPersons) === 1 && (int)$sharedPersons[0]['id'] === 71, 'Shared parent contact person was not loaded into the contact form');
check(count($sharedPersons[0]['email']) === 1 && (int)$sharedPersons[0]['email'][0]['id'] === 72, 'Moved parent email was not rendered below its contact person');

check((new DEEC_Permission([]))->getPermissionForAction('assigncontactperson') === 'edit', 'Email assignment route is not protected by edit permission');
$emailForm = new Contacts_Form_Email();
$companyHtml = $emailForm->renderMultiItem('email', rowById($adapter->rows['email'], 20), ['module' => 'contacts', 'controller' => 'email']);
$personHtml = $emailForm->renderMultiItem('email', rowById($adapter->rows['email'], 24), ['module' => 'contacts', 'controller' => 'email']);
check(str_contains($companyHtml, 'data-email-contactperson-assign="1"'), 'Company email row has no assignment action');
check(!str_contains($personHtml, 'data-email-contactperson-assign="1"'), 'Assigned contact-person email still offers new assignment');
$contactsJs = file_get_contents($root . '/js/contacts.js');
$editView = file_get_contents($root . '/application/modules/contacts/views/scripts/contact/edit.phtml');
check(str_contains($contactsJs, "Dewawi.url('contacts', 'email', 'assigncontactperson')") && str_contains($contactsJs, "window.location.reload()"), 'Assignment dialog does not submit or refresh the contact view');
check(str_contains($editView, 'data-email-contactperson-new') && str_contains($editView, 'data-email-contactperson-existing'), 'Assignment dialog does not offer both target modes');

echo 'PASS: ' . $checks . " email-to-contact-person assignment cases\n";
