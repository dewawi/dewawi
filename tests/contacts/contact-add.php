<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/contacts/contact-add.php (in-memory database).
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

function contactAddMatches(string $where, array $row): bool
{
	$where = trim(preg_replace('/^AND /', '', $where));
	if ($where !== '' && $where[0] === '(' && substr($where, -1) === ')') {
		$where = substr($where, 1, -1);
	}

	if (!preg_match('/^`?([a-z0-9_]+)`? = (.*)$/i', $where, $match)) {
		throw new RuntimeException('Unsupported fixture predicate: ' . $where);
	}

	return array_key_exists($match[1], $row)
		&& (string)$row[$match[1]] === trim($match[2], " '\"");
}

class ContactAddTestConnection
{
	public function __construct(private ContactAddTestAdapter $adapter) {}
	public function inTransaction(): bool { return $this->adapter->inTransaction; }
}

class ContactAddTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public int $lastId = 100;
	public bool $inTransaction = false;
	public ?array $snapshot = null;
	public ?string $failTable = null;

	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function getConnection() { return new ContactAddTestConnection($this); }
	public function beginTransaction()
	{
		$this->snapshot = [Zend_Db_Table_Abstract::$rows, $this->lastId];
		$this->inTransaction = true;
		return true;
	}
	public function commit()
	{
		$this->snapshot = null;
		$this->inTransaction = false;
		return true;
	}
	public function rollBack()
	{
		if ($this->snapshot !== null) {
			[Zend_Db_Table_Abstract::$rows, $this->lastId] = $this->snapshot;
		}
		$this->snapshot = null;
		$this->inTransaction = false;
		return true;
	}
	public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class ContactAddTestSelect extends Zend_Db_Select
{
	public function setIntegrityCheck($value) { return $this; }
	public function forUpdate($flag = true) { return $this; }
}

class ContactAddTestRow extends ArrayObject
{
	public function __construct(array $data) { parent::__construct($data, ArrayObject::ARRAY_AS_PROPS); }
	public function toArray(): array { return $this->getArrayCopy(); }
}

class ContactAddTestRows extends ArrayObject
{
	public function toArray(): array
	{
		return array_map(static fn(ContactAddTestRow $row): array => $row->toArray(), $this->getArrayCopy());
	}
}

class Zend_Db_Table_Abstract
{
	public const NAME = 'name';
	public const COLS = 'cols';
	public static ContactAddTestAdapter $adapter;
	public static array $rows = [];

	public function __construct() { if (method_exists($this, 'init')) $this->init(); }
	public function getAdapter() { return self::$adapter; }
	public function select()
	{
		$select = new ContactAddTestSelect($this->getAdapter());
		return $select->from($this->_name);
	}
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
	public function fetchRow($select)
	{
		$rows = $this->fetchAll($select);
		return $rows[0] ?? false;
	}
	public function fetchAll($select, $order = null, $count = null, $offset = null)
	{
		$rows = [];
		foreach (self::$rows[$this->_name] ?? [] as $row) {
			foreach ($select->getPart(Zend_Db_Select::WHERE) as $condition) {
				if (!contactAddMatches($condition, $row)) continue 2;
			}
			$rows[] = new ContactAddTestRow($row);
		}

		if ($select->getPart(Zend_Db_Select::ORDER)) {
			usort($rows, static function(ContactAddTestRow $left, ContactAddTestRow $right): int {
				return ((int)($right['ordering'] ?? 0)) <=> ((int)($left['ordering'] ?? 0));
			});
		}

		$limit = $select->getPart(Zend_Db_Select::LIMIT_COUNT);
		if ($limit) $rows = array_slice($rows, 0, $limit);

		return new ContactAddTestRows($rows);
	}
	public function insert(array $data)
	{
		if (self::$adapter->failTable === $this->_name) {
			throw new RuntimeException('Injected ' . $this->_name . ' insert failure');
		}

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
				if (!contactAddMatches($condition, $row)) continue 2;
			}
			self::$rows[$this->_name][$id] = array_replace($row, $data);
			$updated++;
		}

		return $updated;
	}
}

require_once $root . '/application/modules/contacts/controllers/ContactController.php';

class ContactAddTestController extends Contacts_ContactController
{
	public function init() {}
}

class ContactAddTest_Redirector extends Zend_Controller_Action_Helper_Redirector
{
	public function gotoSimple($action, $controller = null, $module = null, array $params = [])
	{
		$module = $module ?: $this->getRequest()->getModuleName();
		$controller = $controller ?: $this->getRequest()->getControllerName();
		$url = '/' . $module . '/' . $controller . '/' . $action;
		if (isset($params['id'])) $url .= '/id/' . (int)$params['id'];
		$this->getResponse()->setHeader('Location', $url, true);
		return $this;
	}
}

function contactAddFixtures(): void
{
	Zend_Db_Table_Abstract::$adapter->lastId = 100;
	Zend_Db_Table_Abstract::$adapter->failTable = null;
	Zend_Db_Table_Abstract::$rows = [
		'contact' => [],
		'address' => [],
		'phone' => [],
		'email' => [],
		'internet' => [],
		'increment' => [
			1 => ['id' => 1, 'contactid' => 500, 'clientid' => 7, 'deleted' => 0],
		],
	];
}

function createContact($catid): array
{
	$request = new Zend_Controller_Request_Http();
	$request->setModuleName('contacts')->setControllerName('contact')->setActionName('add');
	if ($catid !== null) $request->setParam('catid', $catid);
	$response = new Zend_Controller_Response_Http();
	$controller = new ContactAddTestController($request, $response);
	$controller->addAction();
	$contacts = array_values(Zend_Db_Table_Abstract::$rows['contact']);

	return [$contacts[0] ?? null, $response];
}

Zend_Db_Table_Abstract::$adapter = new ContactAddTestAdapter([
	'host' => 'unused',
	'username' => 'unused',
	'password' => 'unused',
	'dbname' => 'unused',
]);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'country' => 'DE']);
Zend_Controller_Action_HelperBroker::addHelper(new ContactAddTest_Redirector());

$addMethod = new ReflectionMethod(Contacts_ContactController::class, 'addAction');
check($addMethod->getDeclaringClass()->getName() === DEEC_Controller_Action::class, 'Contact creation bypasses the shared transactional workflow');

contactAddFixtures();
[$contact] = createContact(null);
check($contact !== null && (int)$contact['catid'] === 0, 'Contact creation without a category did not use category zero');

contactAddFixtures();
[$contact, $response] = createContact('all');
check($contact !== null && (int)$contact['catid'] === 0, 'The all-category filter was not normalized to an uncategorized contact');
check((int)$contact['contactid'] === 500, 'The contact number was not assigned');
check((int)Zend_Db_Table_Abstract::$rows['increment'][1]['contactid'] === 501, 'The contact number increment was not advanced');

$contactId = (int)$contact['id'];
$address = array_values(Zend_Db_Table_Abstract::$rows['address']);
$phone = array_values(Zend_Db_Table_Abstract::$rows['phone']);
$email = array_values(Zend_Db_Table_Abstract::$rows['email']);
$internet = array_values(Zend_Db_Table_Abstract::$rows['internet']);
check(count($address) === 1 && (int)$address[0]['parentid'] === $contactId && $address[0]['type'] === 'billing' && $address[0]['country'] === 'DE', 'The default billing address is incomplete');
check(count($phone) === 1 && (int)$phone[0]['parentid'] === $contactId && $phone[0]['type'] === 'phone', 'The default phone is incomplete');
check(count($email) === 1 && (int)$email[0]['parentid'] === $contactId && password_get_info($email[0]['password'])['algo'] !== null, 'The default email or generated password is missing');
check(count($internet) === 1 && (int)$internet[0]['parentid'] === $contactId, 'The default internet row is missing');
check((int)$address[0]['clientid'] === 7 && (int)$phone[0]['clientid'] === 7 && (int)$email[0]['clientid'] === 7 && (int)$internet[0]['clientid'] === 7, 'A default child row has the wrong tenant');
$location = array_values(array_filter($response->getHeaders(), static fn(array $header): bool => $header['name'] === 'Location'));
check(isset($location[0]) && str_contains($location[0]['value'], '/contacts/contact/edit/id/' . $contactId), 'Contact creation did not redirect to edit');

contactAddFixtures();
[$contact] = createContact('23');
check($contact !== null && (int)$contact['catid'] === 23, 'A valid category was not preserved');

contactAddFixtures();
$before = Zend_Db_Table_Abstract::$rows;
Zend_Db_Table_Abstract::$adapter->failTable = 'email';
try {
	createContact('23');
	throw new RuntimeException('Injected child failure did not abort contact creation');
} catch (RuntimeException $e) {
	check($e->getMessage() === 'Injected email insert failure', 'Unexpected exception during rollback test');
}
check(Zend_Db_Table_Abstract::$rows === $before, 'A failed child insert left an incomplete contact or consumed its number');

Zend_Db_Table_Abstract::$adapter->failTable = null;
[$contact] = createContact('23');
check($contact !== null && (int)$contact['contactid'] === 500, 'Retry after rollback did not reuse the unconsumed contact number');
check(count(Zend_Db_Table_Abstract::$rows['contact']) === 1, 'Retry after rollback created duplicate contacts');
check(count(Zend_Db_Table_Abstract::$rows['address']) === 1 && count(Zend_Db_Table_Abstract::$rows['phone']) === 1 && count(Zend_Db_Table_Abstract::$rows['email']) === 1 && count(Zend_Db_Table_Abstract::$rows['internet']) === 1, 'Retry after rollback duplicated or omitted child rows');

echo 'PASS: ' . $checks . " contact add cases\n";
