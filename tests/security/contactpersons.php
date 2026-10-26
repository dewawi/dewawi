<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/contactpersons.php (in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
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

function contactpersonWhereMatches(string $where, array $row): bool
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

class ContactpersonTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    private int $lastId = 100;

    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }

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
                if (!contactpersonWhereMatches($where, $row)) return false;
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
        $where = is_array($where)
            ? $where
            : preg_split('/\)?\s+AND\s+\(?/i', trim((string)$where, '() '));
        $updated = 0;
        if (!isset($this->rows[$table])) return 0;
        foreach ($this->rows[$table] as &$row) {
            foreach ($where as $condition) {
                if (!contactpersonWhereMatches($condition, $row)) continue 2;
            }
            $row = array_replace($row, $bind);
            $updated++;
        }
        unset($row);
        return $updated;
    }

    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

$adapter = new ContactpersonTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'modules' => ['contacts' => 7]]);

$person = [
    'id' => 10,
    'parentid' => 1,
    'module' => 'contacts',
    'controller' => 'contact',
    'clientid' => 7,
    'deleted' => 0,
    'ordering' => 1,
    'name1' => 'Legacy',
    'name2' => 'Name',
    'firstname' => 'Max',
    'lastname' => 'Mustermann',
    'salutation' => 'Herr',
    'title' => 'Dr.',
    'position' => 'Buyer',
    'department' => 'Purchasing',
    'gender' => null,
    'type' => null,
];
$adapter->rows = [
    'contactperson' => [
        $person,
        array_replace($person, ['id' => 11, 'ordering' => 2, 'firstname' => 'Erika', 'lastname' => 'Musterfrau', 'salutation' => 'Frau', 'title' => '', 'position' => 'Manager']),
    ],
    'email' => [
        ['id' => 20, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'company@example.invalid', 'suppressed' => 0],
        ['id' => 21, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'max@example.invalid', 'suppressed' => 0],
        ['id' => 22, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 2, 'email' => 'max.alt@example.invalid', 'suppressed' => 0],
        ['id' => 23, 'parentid' => 11, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'erika@example.invalid', 'suppressed' => 0],
    ],
];

$form = new Contacts_Form_Contactperson();
check(array_keys($form->getElements()) === ['salutation', 'title', 'firstname', 'lastname', 'position', 'department', 'email'], 'Contact-person form fields or order are incorrect');
check($form->isValidPartial(['firstname' => '  Maximilian  ']), 'First-name auto-save validation failed');
check($form->getFilteredValuesPartial(['firstname' => '  Maximilian  '])['firstname'] === 'Maximilian', 'First-name auto-save filtering failed');
check(!$form->isValidPartial(['lastname' => '']), 'Empty last name was accepted');
check(!isset($form->getElements()['name1'], $form->getElements()['name2']), 'Legacy name fields remain editable');

check(DEEC_ContactpersonFormatter::displayName($person) === 'Dr. Max Mustermann', 'Display name is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation($person) === 'Sehr geehrter Herr Dr. Mustermann,', 'Male formal salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['salutation' => 'Frau', 'title' => ''])) === 'Sehr geehrte Frau Mustermann,', 'Female formal salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['salutation' => '0'])) === 'Guten Tag,', 'Neutral salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['lastname' => ''])) === 'Guten Tag,', 'Incomplete name did not use neutral salutation');
check(DEEC_ContactpersonFormatter::recipientLabel($person + ['email' => 'max@example.invalid']) === 'max@example.invalid (Dr. Max Mustermann)', 'Recipient label is incorrect');

$campaignEmail = (new ReflectionClass(DEEC_Email::class))->newInstanceWithoutConstructor();
$personalizeBody = new ReflectionMethod(DEEC_Email::class, 'personalizeBody');
$personalizeBody->setAccessible(true);
check($personalizeBody->invoke($campaignEmail, '[SALUTATION] Willkommen', $person) === 'Sehr geehrter Herr Dr. Mustermann, Willkommen', 'Campaign email personalization is inconsistent');
check($personalizeBody->invoke($campaignEmail, '[SALUTATION] Willkommen', ['salutation' => '0']) === 'Guten Tag, Willkommen', 'Campaign email neutral greeting is inconsistent');

$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
$newId = $contactpersonDb->createForParent(1, 'contacts', 'contact', [
    'firstname' => 'John',
    'lastname' => 'Doe',
    'salutation' => '0',
    'title' => '',
    'position' => 'Engineer',
    'department' => 'Development',
]);
check($newId === 101 && $contactpersonDb->getById($newId)['ordering'] === 3, 'Contact person was not created with the expected ordering');

$edit = ['firstname' => '  Jonathan  ', 'position' => 'Lead Engineer'];
check($form->isValidPartial($edit), 'Contact-person auto-save update was rejected');
$contactpersonDb->updateById($newId, $form->getFilteredValuesPartial($edit));
$updated = $contactpersonDb->getById($newId);
check($updated['firstname'] === 'Jonathan' && $updated['position'] === 'Lead Engineer' && $updated['lastname'] === 'Doe', 'Contact-person auto-save changed the wrong fields');
check(count($contactpersonDb->getByParentId(1, 'contacts', 'contact')) === 3, 'Multiple contact persons were not retained');

$emailDb = new Contacts_Model_DbTable_Email();
$recipients = $emailDb->getContactRecipients(1);
check(count($recipients) === 4, 'Company and contact-person email addresses were not loaded together');
check(count(array_filter($recipients, static fn($row) => (int)($row['contactpersonid'] ?? 0) === 10)) === 2, 'Multiple email addresses for one contact person were lost');
check($recipients[0]['contactid'] === 1 && $recipients[0]['contactpersonid'] === null, 'Company email recipient metadata is incorrect');
check($recipients[1]['contactid'] === 1 && $recipients[1]['firstname'] === 'Max', 'Contact-person recipient metadata is incorrect');

$deletedRow = $contactpersonDb->getById(10);
$contactpersonDb->deleteById(10);
$contactpersonDb->normalizeOrderingByRow($deletedRow);
check($contactpersonDb->getById(10) === null, 'Contact person was not soft-deleted');
check($emailDb->getById(21) === null && $emailDb->getById(22) === null, 'Contact-person email addresses were not soft-deleted');
$remaining = $contactpersonDb->getByParentId(1, 'contacts', 'contact');
check(array_column($remaining, 'ordering') === [1, 2], 'Contact-person ordering was split by the business position field');
check($adapter->rows['email'][0]['deleted'] === 0 && $adapter->rows['email'][3]['deleted'] === 0, 'Deleting one contact person changed unrelated email addresses');

$structure = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
$review = file_get_contents($root . '/install/config/contactperson-name-review.sql');
foreach (['firstname', 'lastname', 'position'] as $field) {
    check(str_contains($structure, '`' . $field . '` varchar(255)'), 'Install schema is missing ' . $field);
    check(str_contains($migration, 'ADD `' . $field . '`'), 'Incremental migration is missing ' . $field);
}
check(!str_contains(strtoupper($migration), 'DROP '), 'Migration removes legacy fields prematurely');
check(str_contains($migration, '`name1`') && str_contains($migration, '`name2`'), 'Migration does not preserve provisional legacy names');
check(str_contains($review, 'verify_name_order') && str_contains($review, 'split_or_verify_single_name') && str_contains($review, 'missing_name'), 'Manual review query does not identify ambiguous names');
check(str_contains($structure, '`gender`') && str_contains($structure, '`type`'), 'Deferred legacy fields were removed prematurely');

foreach (['de_DE', 'en_US'] as $locale) {
    $translations = file_get_contents($root . '/languages/' . $locale . '/contacts/contactperson.php');
    foreach (['FIRSTNAME', 'LASTNAME', 'POSITION', 'SALUTATION_NONE', 'SALUTATION_MR', 'SALUTATION_MS'] as $key) {
        check(str_contains($translations, 'CONTACTS_CONTACT_PERSONS_' . $key), $locale . ' translation is missing ' . $key);
    }
}

echo 'PASS: ' . $checks . " contact-person model, form, migration and email checks\n";
