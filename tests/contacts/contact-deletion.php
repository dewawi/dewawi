<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/contacts/contact-deletion.php (in-memory fixtures, no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
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
require_once $root . '/application/controllers/TrashController.php';

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

function contactDeleteMatches($where, array $row): bool
{
	foreach ((array)$where as $condition) {
		$condition = trim(preg_replace('/^AND /', '', $condition));
		if ($condition === '') continue;
		while ($condition[0] === '(' && substr($condition, -1) === ')') {
			$condition = substr($condition, 1, -1);
		}
		if (strpos($condition, ' OR ') !== false) {
			$matched = false;
			foreach (explode(' OR ', $condition) as $alternative) {
				if (contactDeleteMatches([$alternative], $row)) $matched = true;
			}
			if (!$matched) return false;
			continue;
		}
		if (preg_match('/^([a-z]+) IN \(([0-9, ]+)\)$/', $condition, $match)) {
			$values = array_map('intval', explode(',', $match[2]));
			if (!in_array((int)($row[$match[1]] ?? 0), $values, true)) return false;
			continue;
		}
		if (!preg_match('/^([a-z]+) = (.*)$/', $condition, $match)) {
			throw new RuntimeException('Unsupported fixture predicate: ' . $condition);
		}
		$value = trim($match[2], " '");
		if ((string)($row[$match[1]] ?? '') !== $value) return false;
	}
	return true;
}

class ContactDeleteAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $rows = [];
	public ?string $failTable = null;
	private ?array $snapshot = null;
	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function beginTransaction() { $this->snapshot = $this->rows; return $this; }
	public function commit() { $this->snapshot = null; return $this; }
	public function rollBack() { $this->rows = $this->snapshot; $this->snapshot = null; return $this; }
	public function describeTable($tableName, $schemaName = null)
	{
		$columns = array_keys($this->rows[$tableName][0] ?? $this->emptyRow($tableName));
		$metadata = [];
		foreach ($columns as $column) {
			$metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
		}
		return $metadata;
	}
	private function emptyRow(string $table): array
	{
		if ($table === 'client') return ['id' => 0, 'parentid' => 0, 'deleted' => 0];
		if ($table === 'permission') return ['id' => 0, 'userid' => 0, 'contacts' => '', 'deleted' => 0];
		if ($table === 'contact') return ['id' => 0, 'contactid' => 0, 'clientid' => 0, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0];
		if (in_array($table, ['address', 'phone', 'email', 'internet', 'bankaccount', 'contactperson', 'comment'], true)) {
			return ['id' => 0, 'parentid' => 0, 'module' => '', 'controller' => '', 'clientid' => 0, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0];
		}
		return ['id' => 0, 'contactid' => 0, 'supplierid' => 0, 'clientid' => 0, 'deleted' => 0];
	}
	public function query($sql, $bind = [])
	{
		if (!$sql instanceof Zend_Db_Select || $bind) throw new RuntimeException('Unexpected database access');
		$sql->assemble();
		$from = $sql->getPart(Zend_Db_Select::FROM);
		$table = reset($from)['tableName'];
		if ($table === $this->failTable) throw new RuntimeException('secret database exception');
		$rows = array_values(array_filter($this->rows[$table] ?? [], static fn($row) => contactDeleteMatches($sql->getPart(Zend_Db_Select::WHERE), $row)));
		if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode = null) { return $this->rows; }
			public function fetch($mode = null) { return $this->rows[0] ?? false; }
		};
	}
	public function update($table, array $bind, $where = '')
	{
		$affected = 0;
		foreach ($this->rows[$table] as &$row) {
			if (!contactDeleteMatches($where, $row)) continue;
			$row = array_replace($row, $bind);
			$affected++;
		}
		return $affected;
	}
}

class ContactDeleteFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
	public function getName() { return 'FlashMessenger'; }
	public function addMessage($message) { return $this; }
}

class ContactDeleteRequest extends Zend_Controller_Request_Http
{
	public string $body = '';
	public function getRawBody() { return $this->body; }
}

function trashContacts(array $ids): array
{
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$request = new ContactDeleteRequest();
	$request->setModuleName('default')->setControllerName('trash')->setActionName('add');
	$request->body = json_encode(['module' => 'contacts', 'controller' => 'contact', 'id' => $ids]);
	$response = new Zend_Controller_Response_Http();
	Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
	(new TrashController($request, $response))->addAction();
	return [(array)json_decode($response->getBody(), true), $response->getHttpResponseCode()];
}

$adapter = new ContactDeleteAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 70, 'admin' => 0]);
Zend_Registry::set('Client', ['id' => 70, 'parentid' => 0]);
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->setControllerDirectory([
	'default' => $root . '/application/controllers',
	'contacts' => $root . '/application/modules/contacts/controllers',
]);
Zend_Controller_Action_HelperBroker::addHelper(new ContactDeleteFlashMessenger());
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;

$contact = ['id' => 10, 'contactid' => 10010, 'clientid' => 70, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0];
$adapter->rows = [
	'permission' => [['id' => 1, 'userid' => 42, 'contacts' => '{"contact":["delete"]}', 'deleted' => 0]],
	'client' => [
		['id' => 70, 'parentid' => 0, 'deleted' => 0],
		['id' => 71, 'parentid' => 70, 'deleted' => 0],
		['id' => 72, 'parentid' => 70, 'deleted' => 1],
		['id' => 99, 'parentid' => 0, 'deleted' => 0],
	],
	'contact' => [
		$contact,
		array_replace($contact, ['id' => 20, 'contactid' => 10020]),
		array_replace($contact, ['id' => 30, 'contactid' => 10030]),
		array_replace($contact, ['id' => 40, 'contactid' => 0]),
		array_replace($contact, ['id' => 50, 'contactid' => 10050]),
	],
	'address' => [['id' => 101, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 70, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0]],
	'phone' => [['id' => 102, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 70, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0]],
	'email' => [['id' => 103, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 70, 'deleted' => 0, 'modified' => null, 'modifiedby' => 0]],
	'internet' => [], 'bankaccount' => [], 'contactperson' => [], 'comment' => [],
	'quote' => [
		['id' => 201, 'contactid' => 10020, 'clientid' => 71, 'deleted' => 0],
		['id' => 202, 'contactid' => 10030, 'clientid' => 99, 'deleted' => 0],
	],
];

foreach (['salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder', 'quoterequest', 'purchaseorder', 'process', 'task', 'stockplanning', 'shopinquirydata', 'shoporder', 'emailmessage', 'downloadtracking', 'feedback'] as $table) {
	$adapter->rows[$table] = [];
}

$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId(70);
check($contactDb->canDelete($adapter->rows['contact'][0]), 'Assigned contact number still triggers the document draft rule');
check($contactDb->canDelete($adapter->rows['contact'][3]), 'Unnumbered unused contact is not deletable');
check(!$contactDb->canDelete($adapter->rows['contact'][1]), 'Child-tenant document reference did not block deletion');
check($contactDb->getDeleteBlockReason($adapter->rows['contact'][1]) === 'MESSAGES_CONTACT_DELETE_BLOCKED_DOCUMENTS', 'Document block reason is not stable');
check($contactDb->canDelete($adapter->rows['contact'][2]), 'Unrelated tenant reference blocked deletion');

[$result] = trashContacts([10, 20]);
check($result['ok'] === false && $result['deletedIds'] === [10] && $result['blockedIds'] === [20], 'Mixed contact deletion lost its partial result');
check($result['blockedReasons']['20'] === 'MESSAGES_CONTACT_DELETE_BLOCKED_DOCUMENTS', 'Partial result lost the safe business reason');
check((int)$adapter->rows['contact'][0]['deleted'] === 1 && (int)$adapter->rows['contact'][1]['deleted'] === 0, 'Trash changed an unconfirmed or blocked contact');
check((int)$adapter->rows['address'][0]['deleted'] === 1 && (int)$adapter->rows['phone'][0]['deleted'] === 1 && (int)$adapter->rows['email'][0]['deleted'] === 1, 'Owned contact details were not soft-deleted');

[$result] = trashContacts([30]);
check($result['ok'] === true && $result['deletedIds'] === [30], 'Foreign-tenant reference changed contact deletion');

$adapter->rows['permission'][0]['contacts'] = '{"contact":["view"]}';
[$result, $status] = trashContacts([40]);
check($status === 403 && $result['ok'] === false && (int)$adapter->rows['contact'][3]['deleted'] === 0, 'Missing delete permission changed contact data');

$adapter->rows['permission'][0]['contacts'] = '{"contact":["delete"]}';
$adapter->failTable = 'quote';
[$result, $status] = trashContacts([50]);
check($status === 500 && $result['message'] === 'MESSAGES_CANNOT_BE_DELETED' && $result['deletedIds'] === [], 'Technical deletion failure did not return a safe result');
check(strpos(json_encode($result), 'secret') === false && (int)$adapter->rows['contact'][4]['deleted'] === 0, 'Technical deletion failure leaked details or changed data');
$adapter->failTable = null;

$adapter->rows['task'][] = ['id' => 301, 'contactid' => 0, 'supplierid' => 10040, 'clientid' => 70, 'deleted' => 0];
$adapter->rows['contact'][3]['contactid'] = 10040;
check($contactDb->getDeleteBlockReason($adapter->rows['contact'][3]) === 'MESSAGES_CONTACT_DELETE_BLOCKED_OPERATIONS', 'Supplier workflow did not produce an operation reason');
$adapter->rows['task'] = [];
$adapter->rows['emailmessage'][] = ['id' => 401, 'contactid' => 40, 'clientid' => 70, 'deleted' => 0];
check($contactDb->getDeleteBlockReason($adapter->rows['contact'][3]) === 'MESSAGES_CONTACT_DELETE_BLOCKED_COMMUNICATIONS', 'Communication history did not produce its business reason');

class ContactDeletionDocumentFixture extends DEEC_Model_DbTable_Entity { protected $_name = 'quote'; }
$documentDb = (new ContactDeletionDocumentFixture())->setClientId(70);
check(!$documentDb->canDelete(['id' => 1, 'quoteid' => 10001]) && $documentDb->canDelete(['id' => 2, 'quoteid' => null]), 'Global document deletion rule was relaxed');

echo 'PASS: ' . $checks . " contact deletion, tenant, permission, cascade, reason and partial-result cases\n";
