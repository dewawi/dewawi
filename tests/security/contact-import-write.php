<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/contact-import-write.php (in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
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

function importWriteMatches(string $where, array $row): bool
{
	$where = trim(preg_replace('/^AND /', '', $where));
	if ($where !== '' && $where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);

	if (preg_match('/^(?:[a-z0-9_]+\.)?([a-z0-9_]+) IN \(([^)]*)\)$/i', $where, $match)) {
		$values = array_map(static fn($value) => trim($value, " '\""), explode(',', $match[2]));
		return array_key_exists($match[1], $row) && in_array((string)$row[$match[1]], $values, true);
	}

	if (!preg_match('/^(?:[a-z0-9_]+\.)?([a-z0-9_]+) = (.*)$/i', $where, $match)) {
		throw new RuntimeException('Unsupported fixture predicate: ' . $where);
	}

	return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '\"");
}

class ContactImportWriteConnection
{
	public function __construct(private ContactImportWriteAdapter $adapter) {}
	public function inTransaction(): bool { return $this->adapter->inTransaction; }
}

class ContactImportWriteAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public int $lastId = 1000;
	public bool $inTransaction = false;
	public ?array $snapshot = null;

	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function getConnection() { return new ContactImportWriteConnection($this); }
	public function beginTransaction() { $this->snapshot = Zend_Db_Table_Abstract::$rows; $this->inTransaction = true; return true; }
	public function commit() { $this->snapshot = null; $this->inTransaction = false; return true; }
	public function rollBack() { if ($this->snapshot !== null) Zend_Db_Table_Abstract::$rows = $this->snapshot; $this->snapshot = null; $this->inTransaction = false; return true; }
	public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class ContactImportWriteSelect extends Zend_Db_Select
{
	public function setIntegrityCheck($value) { return $this; }
}

class ContactImportWriteRow extends ArrayObject
{
	public function __construct(array $data) { parent::__construct($data, ArrayObject::ARRAY_AS_PROPS); }
	public function toArray(): array { return $this->getArrayCopy(); }
}

class ContactImportWriteRows extends ArrayObject
{
	public function toArray(): array { return array_map(static fn($row): array => $row->toArray(), $this->getArrayCopy()); }
}

class Zend_Db_Table_Abstract
{
	public const NAME = 'name';
	public const COLS = 'cols';
	public static ContactImportWriteAdapter $adapter;
	public static array $rows = [];

	public function __construct() { if (method_exists($this, 'init')) $this->init(); }
	public function getAdapter() { return self::$adapter; }
	public function select() { return new ContactImportWriteSelect($this->getAdapter()); }
	public function info($key = null)
	{
		if ($key === self::NAME || $key === 'name') return $this->_name;
		if ($key === self::COLS || $key === 'cols') {
			$columns = [];
			foreach (self::$rows[$this->_name] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
			return array_values(array_unique($columns));
		}
		return [];
	}
	public function fetchRow($select) { return $this->fetchAll($select)[0] ?? null; }
	public function fetchAll($select)
	{
		if (is_array($select)) {
			$conditions = $select;
			$select = $this->select();
			foreach ($conditions as $condition) $select->where($condition);
		}
		if (!$select->getPart(Zend_Db_Select::FROM)) $select->from($this->_name);
		$rows = [];
		foreach (self::$rows[$this->_name] ?? [] as $row) {
			foreach ($select->getPart(Zend_Db_Select::WHERE) as $condition) {
				if (!importWriteMatches($condition, $row)) continue 2;
			}
			$rows[] = new ContactImportWriteRow($row);
		}
		$limit = $select->getPart(Zend_Db_Select::LIMIT_COUNT);
		if ($limit) $rows = array_slice($rows, 0, $limit);
		return new ContactImportWriteRows($rows);
	}
	public function insert(array $data)
	{
		$id = ++self::$adapter->lastId;
		$data += ['id' => $id, 'deleted' => 0];
		self::$rows[$this->_name][$id] = $data;
		return 1;
	}
	public function update(array $data, $where)
	{
		$conditions = is_array($where) ? $where : [$where];
		$updated = 0;
		foreach (self::$rows[$this->_name] ?? [] as $id => $row) {
			foreach ($conditions as $condition) {
				if (!importWriteMatches($condition, $row)) continue 2;
			}
			self::$rows[$this->_name][$id] = array_replace($row, $data);
			$updated++;
		}
		return $updated;
	}
}

require_once $root . '/application/modules/contacts/controllers/ContactController.php';

class ContactImportWriteController extends Contacts_ContactController
{
	public function init() {}
	public function executePreview(array $preview, int $tagId = 0): array { return $this->executeContactImport($preview, $tagId, 7); }
}

Zend_Db_Table_Abstract::$adapter = new ContactImportWriteAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'country' => 'DE']);
Zend_Db_Table_Abstract::$rows = [
	'contact' => [
		1 => ['id' => 1, 'contactid' => 100, 'catid' => 3, 'name1' => 'Old Company', 'name2' => '', 'department' => 'Keep', 'clientid' => 7, 'deleted' => 0],
		2 => ['id' => 2, 'contactid' => 200, 'catid' => 3, 'name1' => 'Foreign', 'name2' => '', 'department' => '', 'clientid' => 8, 'deleted' => 0],
	],
	'address' => [
		10 => ['id' => 10, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'Old Street', 'postcode' => '11111', 'city' => 'Old City', 'country' => 'DE', 'ordering' => 1, 'clientid' => 7, 'deleted' => 0],
	],
	'phone' => [
		20 => ['id' => 20, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'phone', 'phone' => '+49 1', 'ordering' => 1, 'clientid' => 7, 'deleted' => 0],
	],
	'email' => [
		30 => ['id' => 30, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'old@example.test', 'password' => 'old', 'ordering' => 1, 'clientid' => 7, 'deleted' => 0],
	],
	'internet' => [
		40 => ['id' => 40, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'internet' => 'https://old.test', 'ordering' => 1, 'clientid' => 7, 'deleted' => 0],
	],
	'increment' => [
		1 => ['id' => 1, 'contactid' => 500, 'clientid' => 7, 'deleted' => 0],
	],
	'tag' => [
		50 => ['id' => 50, 'shopid' => 0, 'module' => 'contacts', 'controller' => 'contact', 'title' => 'Gilventa', 'clientid' => 7, 'deleted' => 0],
	],
	'tagentity' => [],
];

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('import');
$controller = new ContactImportWriteController($request, new Zend_Controller_Response_Http());
$preview = [
	'errors' => [],
	'counts' => ['new' => 1, 'update' => 1, 'duplicate' => 0, 'error' => 1],
	'items' => [
		[
			'status' => 'update', 'existingId' => 1, 'contactid' => '100',
			'contactData' => ['name1' => 'Updated Company'],
			'addressData' => ['street' => 'New Street'],
			'phones' => ['+49 1', '+49 2'], 'emails' => ['old@example.test', 'new@example.test'], 'internets' => ['https://old.test'],
		],
		[
			'status' => 'new', 'existingId' => 0, 'contactid' => '600',
			'contactData' => ['name1' => 'New Company', 'catid' => 4],
			'addressData' => ['street' => 'Brand Street', 'postcode' => '22222', 'city' => 'New City'],
			'phones' => ['+49 3'], 'emails' => ['company@example.test'], 'internets' => ['https://company.test'],
		],
		[
			'status' => 'error', 'existingId' => 0, 'contactid' => '',
			'contactData' => [], 'addressData' => [], 'phones' => [], 'emails' => [], 'internets' => [],
		],
	],
];

$result = $controller->executePreview($preview, 50);
check($result === ['created' => 1, 'updated' => 1, 'skipped' => 1, 'processed' => 3], 'Import result counts are incorrect');
check(Zend_Db_Table_Abstract::$rows['contact'][1]['name1'] === 'Updated Company', 'Existing contact was not updated');
check(Zend_Db_Table_Abstract::$rows['contact'][1]['department'] === 'Keep', 'Missing contact field was cleared');
check((int)Zend_Db_Table_Abstract::$rows['contact'][1]['catid'] === 3, 'Missing category column changed the existing category');
check(Zend_Db_Table_Abstract::$rows['address'][10]['street'] === 'New Street' && Zend_Db_Table_Abstract::$rows['address'][10]['postcode'] === '11111', 'Billing address update cleared missing fields');

$createdContacts = array_values(array_filter(Zend_Db_Table_Abstract::$rows['contact'], static fn(array $row): bool => (int)$row['clientid'] === 7 && (int)$row['contactid'] === 600));
check(count($createdContacts) === 1 && $createdContacts[0]['name1'] === 'New Company', 'New numbered contact was not created');
$createdId = (int)$createdContacts[0]['id'];
check((int)Zend_Db_Table_Abstract::$rows['increment'][1]['contactid'] === 601, 'Contact number increment was not advanced safely');

$ownPhones = array_filter(Zend_Db_Table_Abstract::$rows['phone'], static fn(array $row): bool => (int)$row['parentid'] === 1 && (int)$row['clientid'] === 7);
$ownEmails = array_filter(Zend_Db_Table_Abstract::$rows['email'], static fn(array $row): bool => (int)$row['parentid'] === 1 && (int)$row['clientid'] === 7);
check(count($ownPhones) === 2, 'Existing phone was duplicated or new phone was not added');
check(count($ownEmails) === 2, 'Existing email was duplicated or new email was not added');
check(count(array_filter(Zend_Db_Table_Abstract::$rows['address'], static fn(array $row): bool => (int)$row['parentid'] === $createdId)) === 1, 'New contact billing address was not created');
check(count(array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static fn(array $row): bool => (int)$row['tagid'] === 50 && (int)$row['deleted'] === 0)) === 2, 'Gilventa tag was not assigned to imported contacts');

$repeat = $preview;
$repeat['counts'] = ['new' => 0, 'update' => 2, 'duplicate' => 0, 'error' => 1];
$repeat['items'][1]['status'] = 'update';
$repeat['items'][1]['existingId'] = $createdId;
$repeatResult = $controller->executePreview($repeat, 50);
check($repeatResult['created'] === 0 && $repeatResult['updated'] === 2, 'Repeated numbered import did not update existing contacts');
check(count(array_filter(Zend_Db_Table_Abstract::$rows['phone'], static fn(array $row): bool => (int)$row['parentid'] === 1)) === 2, 'Repeated import duplicated phone values');
check(count(array_filter(Zend_Db_Table_Abstract::$rows['email'], static fn(array $row): bool => (int)$row['parentid'] === 1)) === 2, 'Repeated import duplicated email values');
check(count(array_filter(Zend_Db_Table_Abstract::$rows['tagentity'], static fn(array $row): bool => (int)$row['tagid'] === 50 && (int)$row['deleted'] === 0)) === 2, 'Repeated import duplicated tag assignments');
check(Zend_Db_Table_Abstract::$rows['contact'][2]['name1'] === 'Foreign', 'Import modified a foreign tenant contact');

$before = Zend_Db_Table_Abstract::$rows;
try {
	$controller->executePreview($preview, 999);
	throw new RuntimeException('Invalid import tag was accepted');
} catch (InvalidArgumentException $expected) {
	check(Zend_Db_Table_Abstract::$rows === $before, 'Invalid import tag changed data');
}

echo 'PASS: ' . $checks . " contact import write cases\n";
