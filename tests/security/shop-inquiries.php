<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/shop-inquiries.php (in-memory fixtures, no database required).
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
require_once $root . '/application/modules/shops/controllers/InquiryController.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function inquiryMatches($where, array $row): bool
{
    foreach ((array)$where as $condition) {
        $condition = trim(preg_replace('/^AND /', '', $condition));
        if ($condition[0] === '(' && substr($condition, -1) === ')') $condition = substr($condition, 1, -1);
        if (!preg_match('/^(?:`?[a-z]+`?\.)?`?([a-z]+)`? = (.+)$/i', $condition, $match)) {
            throw new RuntimeException('Unsupported fixture predicate: ' . $condition);
        }
        $value = trim($match[2], " '");
        if ((string)($row[$match[1]] ?? '') !== $value) return false;
    }
    return true;
}

class ShopInquiryTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    private int $lastId = 100;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $metadata = [];
        foreach (array_keys($this->rows[$tableName][0]) as $column) {
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
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter(
            $this->rows[$table],
            static fn($row) => inquiryMatches($sql->getPart(Zend_Db_Select::WHERE), $row)
        ));
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function insert($table, array $bind)
    {
        if ($table === 'shopinquirydata') {
            foreach ($this->rows[$table] as $row) {
                if ((int)$row['clientid'] === (int)$bind['clientid']
                    && (int)$row['shopid'] === (int)$bind['shopid']
                    && substr((string)$row['token'], 0, 64) === substr((string)$bind['token'], 0, 64)) {
                    throw new Zend_Db_Statement_Exception('Duplicate inquiry token');
                }
            }
        }
        $this->rows[$table][] = $bind + ['id' => ++$this->lastId, 'deleted' => 0];
        return 1;
    }
    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class ShopInquiryTestController extends Shops_InquiryController
{
    public function init() {}
    public function validateCsrf(string $expected, string $submitted): bool
    {
        $this->csrfToken = $expected;
        return $this->isValidCsrf(['csrf_token' => $submitted]);
    }
}

$adapter = new ShopInquiryTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
$formFields = json_encode([
    'details' => [[
        'name' => 'firma',
        'type' => 'text',
        'required' => true,
        'format' => ['type' => 'string'],
    ]],
    'contact' => [
        [
            'name' => 'email',
            'type' => 'email',
            'required' => true,
            'format' => ['type' => 'email'],
        ],
        [
            'name' => 'privacy',
            'type' => 'checkbox',
            'required' => true,
        ],
    ],
]);
$inquiryDataTemplate = [
    'id' => 1,
    'formid' => 11,
    'shopid' => 41,
    'token' => str_repeat('f', 32),
    'data' => '{}',
    'clientid' => 7,
    'created' => null,
    'createdby' => 0,
    'modified' => null,
    'modifiedby' => 0,
    'deleted' => 1,
];
$formTemplate = [
    'id' => 11,
    'shopid' => 41,
    'title' => 'Technical inquiry',
    'description' => '',
    'fields' => $formFields,
    'language' => 'de',
    'quoteheader' => '',
    'quotefooter' => '',
    'quotetemplateid' => 0,
    'clientid' => 7,
    'created' => null,
    'createdby' => 0,
    'modified' => null,
    'modifiedby' => 0,
    'deleted' => 0,
];
$adapter->rows = [
    'shopinquirydata' => [$inquiryDataTemplate],
    'shopinquiryform' => [
        $formTemplate,
        array_replace($formTemplate, ['id' => 12, 'shopid' => 42, 'title' => 'Other shop']),
        array_replace($formTemplate, ['id' => 13, 'clientid' => 8, 'title' => 'Other client']),
        array_replace($formTemplate, ['id' => 14, 'fields' => '{invalid']),
        array_replace($formTemplate, ['id' => 15, 'fields' => '{"details":[{"name":"","type":"text"}]}']),
        array_replace($formTemplate, ['id' => 16, 'fields' => '{"details":[{"name":"csrf_token","type":"text"}]}']),
    ],
];
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
Zend_Registry::set('User', ['id' => 0]);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 41, 'clientid' => 7]));

// Contact form: render once to initialize Zend's CSRF session, then validate a real POST form.
$sessionDir = sys_get_temp_dir() . '/dewawi-shop-inquiry-session-' . getmypid();
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700)) throw new RuntimeException('Could not create session directory');
Zend_Session::setOptions(['save_path' => $sessionDir, 'use_cookies' => false]);
$view = new Zend_View();
$contactGet = new Shops_Form_Contact(['view' => $view]);
$contactGet->render($view);
$contactValues = [
    'csrf_token' => $contactGet->getElement('csrf_token')->getHash(),
    'inquiry_token' => $contactGet->getElement('inquiry_token')->getValue(),
    'fax_number' => '',
    'subject' => 'Question',
    'name' => 'Ada Example',
    'email' => 'ada@example.com',
    'phone' => '',
    'message' => 'Please contact me.',
    'module' => 'shops',
    'controller' => 'contact',
    'privacy' => '1',
];
$contactPost = new Shops_Form_Contact(['view' => $view]);
check($contactPost->isValid($contactValues), 'Valid contact form was rejected: ' . json_encode($contactPost->getMessages()));
$invalidContact = $contactValues;
$invalidContact['email'] = 'invalid';
$invalidContact['privacy'] = '0';
check(!(new Shops_Form_Contact(['view' => $view]))->isValid($invalidContact), 'Invalid email or privacy consent was accepted');
$invalidCsrf = $contactValues;
$invalidCsrf['csrf_token'] = str_repeat('0', 32);
check(!(new Shops_Form_Contact(['view' => $view]))->isValid($invalidCsrf), 'Invalid contact CSRF token was accepted');
$contactRow = (new Shops_Model_DbTable_Inquirydata())->saveOnce(0, $contactValues['inquiry_token'], [
    'name' => $contactValues['name'],
    'email' => $contactValues['email'],
    'message' => $contactValues['message'],
    'origin' => 'contact',
    'source_path' => '/',
]);
check($contactRow['inserted'] === true, 'Valid contact inquiry was not inserted');
$storedContact = (new Shops_Model_DbTable_Inquirydata())->getByToken($contactValues['inquiry_token']);
check((int)$storedContact['formid'] === 0 && json_decode($storedContact['data'], true)['origin'] === 'contact', 'Contact inquiry origin was not stored');
check((new Shops_Model_DbTable_Inquirydata())->saveOnce(0, $contactValues['inquiry_token'], [])['inserted'] === false, 'Duplicate contact inquiry was inserted');

// The requested form is loaded through the site-aware table, never as a global first row.
$config = new Shops_Model_FormConfig();
check(array_keys($config->getConfig(11)) === ['details', 'contact'], 'Target inquiry form was not loaded by ID');
check($config->getConfig(12) === [], 'Inquiry form from another shop was loaded');
check($config->getConfig(13) === [], 'Inquiry form from another client was loaded');
check($config->getConfig(14) === [], 'Malformed inquiry form configuration was accepted');
check($config->getConfig(15) === [], 'Inquiry form without valid fields was accepted');
check($config->getConfig(16) === [], 'Inquiry form was allowed to replace a reserved control field');

// Existing DEEC_Form validation remains active across multiple configured steps.
$detailsForm = new Shops_Form_DynamicForm($config->getConfig(11), 'details', 'csrf-value', str_repeat('a', 32));
check($detailsForm->isValid(['csrf_token' => 'csrf-value', 'inquiry_token' => str_repeat('a', 32), 'firma' => 'Example GmbH']), 'Valid first inquiry step was rejected');
check(!$detailsForm->isValid(['csrf_token' => 'csrf-value', 'inquiry_token' => str_repeat('a', 32), 'firma' => '']), 'Missing required inquiry value was accepted');
$contactForm = new Shops_Form_DynamicForm($config->getConfig(11), 'contact', 'csrf-value', str_repeat('a', 32));
check($contactForm->isValid(['csrf_token' => 'csrf-value', 'inquiry_token' => str_repeat('a', 32), 'email' => 'ada@example.com', 'privacy' => '1']), 'Valid final inquiry step was rejected');
check(!$contactForm->isValid(['csrf_token' => 'csrf-value', 'inquiry_token' => str_repeat('a', 32), 'email' => 'invalid', 'privacy' => '0']), 'Invalid final inquiry step was accepted');
$controller = new ShopInquiryTestController(new Zend_Controller_Request_Simple(), new Zend_Controller_Response_Http());
check($controller->validateCsrf('csrf-value', 'csrf-value'), 'Valid inquiry CSRF token was rejected');
check(!$controller->validateCsrf('csrf-value', 'other-value'), 'Invalid inquiry CSRF token was accepted');

// Persistence includes origin data and site/client ownership, and is idempotent per token.
$token = str_repeat('a', 32);
$inquiryDb = new Shops_Model_DbTable_Inquirydata();
$saved = $inquiryDb->saveOnce(11, $token, [
    'email' => 'ada@example.com',
    'origin' => 'technical_inquiry',
    'source_path' => '/technical-request',
]);
check($saved['inserted'] === true, 'Valid inquiry was not inserted');
$stored = $inquiryDb->getByToken($token);
$storedData = json_decode($stored['data'], true);
check((int)$stored['formid'] === 11 && (int)$stored['shopid'] === 41 && (int)$stored['clientid'] === 7, 'Inquiry ownership or form assignment was not stored');
check(!empty($stored['created']) && !empty($stored['modified']), 'Inquiry audit timestamps were not stored');
check($storedData['origin'] === 'technical_inquiry' && $storedData['source_path'] === '/technical-request', 'Inquiry origin was not stored');
$duplicate = $inquiryDb->saveOnce(11, $token, ['email' => 'changed@example.test']);
check($duplicate['inserted'] === false && $duplicate['id'] === $saved['id'], 'Duplicate inquiry created a second record');
check(count($adapter->rows['shopinquirydata']) === 3, 'Duplicate inquiry changed persistence state');

// A different shop/client cannot see or deduplicate against this inquiry.
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 42, 'clientid' => 7]));
check((new Shops_Model_DbTable_Inquirydata())->getByToken($token) === null, 'Inquiry leaked to another shop');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 41, 'clientid' => 8]));
check((new Shops_Model_DbTable_Inquirydata())->getByToken($token) === null, 'Inquiry leaked to another client');

// Version 1.1.8 owns the complete inquiry migration and the install schema matches it.
$migrationPath = $root . '/install/config/update-1.1.7-1.1.8.sql';
$migration = file_get_contents($migrationPath);
$structure = file_get_contents($root . '/install/config/structure.sql');
check(!is_file($root . '/install/config/update-1.1.8-1.1.9.sql'), 'Inquiry migration still introduces an unsupported version 1.1.9');
check(substr_count($migration, 'ADD UNIQUE INDEX `clientid_shopid_token`') === 1, 'Inquiry token unique index is missing or duplicated in the 1.1.8 migration');
check(substr_count($migration, 'ADD INDEX `clientid_shopid_deleted_id`') === 1, 'Inquiry form lookup index is missing or duplicated in the 1.1.8 migration');
check(substr_count($migration, 'ADD INDEX `clientid_shopid_formid_deleted`') === 1, 'Inquiry data lookup index is missing or duplicated in the 1.1.8 migration');
$deduplicateAt = strpos($migration, 'UPDATE `shopinquirydata` AS duplicate');
$uniqueIndexAt = strpos($migration, 'ADD UNIQUE INDEX `clientid_shopid_token`');
check($deduplicateAt !== false && $uniqueIndexAt !== false && $deduplicateAt < $uniqueIndexAt, 'Inquiry duplicates are not normalized before the unique index is added');
check(str_contains($migration, "SHA2(CONCAT(duplicate.`token`, ':', duplicate.`id`), 256)"), 'Duplicate inquiry tokens are not replaced deterministically');
check(str_contains($structure, 'UNIQUE KEY `clientid_shopid_token` (`clientid`, `shopid`, `token`(64))'), 'Install schema lacks the inquiry token unique index');
check(str_contains($structure, 'KEY `clientid_shopid_deleted_id` (`clientid`, `shopid`, `deleted`, `id`)'), 'Install schema lacks the inquiry form lookup index');
check(str_contains($structure, 'KEY `clientid_shopid_formid_deleted` (`clientid`, `shopid`, `formid`, `deleted`)'), 'Install schema lacks the inquiry data lookup index');

echo "OK ({$checks} checks)\n";
