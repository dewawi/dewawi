<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/quote-email.php (in-memory database and mail transport).
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
require_once $root . '/application/controllers/helpers/Email.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function emailWhereMatches(string $where, array $row): bool
{
    $where = trim(preg_replace('/^AND /', '', $where));
    if ($where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);
    if (!preg_match('/^([a-z0-9_]+) = (.*)$/', $where, $match)) throw new RuntimeException('Unsupported fixture predicate: ' . $where);
    return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '");
}

// Keep the real models, permission resolver, SQL builder and email helper; replace database I/O.
class QuoteEmailTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $writes = [];
    private int $lastId = 1000;
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'clientid', 'deleted', 'ordering'];
        foreach ($this->rows as $rows) {
            foreach ($rows as $row) $columns = array_merge($columns, array_keys($row));
        }
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
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (!emailWhereMatches($where, $row)) return false;
            }
            return true;
        }));
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
    public function insert($table, array $bind)
    {
        $bind['id'] = ++$this->lastId;
        $this->rows[$table][] = $bind + ['deleted' => 0];
        $this->writes[] = [$table, $bind];
        return 1;
    }
    public function update($table, array $bind, $where = '')
    {
        $this->writes[] = [$table, $bind];
        return 1;
    }
    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

class Contacts_Form_Contact
{
    public function isValid($data) { return true; }
    public function getValues() { return []; }
}
class DEEC_Email
{
    public static array $sent = [];
    public static function buildSmtpConfig(array $config): array { return ['host' => 'unused']; }
    public static function prepareMessageData(array $message): array { return $message; }
    public static function sendMessage(array $smtp, array $message): array
    {
        self::$sent[] = $message;
        return ['sent' => true, 'error' => ''];
    }
}
class DEEC_Feedback
{
    public const TYPE_QUOTE = 'quote';
    public static array $requests = [];
    public function prepareRequest(...$args): array
    {
        self::$requests[] = $args;
        return ['request' => ['id' => 900, 'clientid' => $args[3]], 'created' => true];
    }
    public function buildEmailBlock(...$args): string { return '<feedback>'; }
    public function replaceEmailBlock(string $body, string $block): string { return $body . $block; }
    public function attachEmailMessage(...$args): void {}
}
class QuoteEmailTestFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}
class QuoteEmailTestDirectory extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Directory'; }
    public function getUrl($id) { return 'unused'; }
}

class QuoteEmailTestCampaignRecipientService extends Campaigns_Service_CampaignRecipientService
{
    public function contactPersons(array $contacts): array
    {
        return $this->getContactPersonsByCompany($contacts);
    }
}

$adapter = new QuoteEmailTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Controller_Action_HelperBroker::addHelper(new QuoteEmailTestFlashMessenger());
Zend_Controller_Action_HelperBroker::addHelper(new QuoteEmailTestDirectory());

function setupQuoteEmail(bool $shared = false): void
{
    global $adapter;
    $contactClientId = $shared ? 70 : 7;
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 7, 'email' => 'sender@example.invalid', 'emailsender' => 'Sender', 'emailsignature' => 'Signature']);
    Zend_Registry::set('Client', ['id' => $contactClientId, 'parentid' => $shared ? 70 : 0, 'modules' => ['contacts' => $contactClientId, 'sales' => 7]]);
    Zend_Registry::set('Config', []);
    $quote = ['id' => 10, 'quoteid' => 1001, 'clientid' => 7, 'contactid' => 500, 'language' => 'de_DE', 'state' => 105, 'deleted' => 0];
    $contact = ['id' => 100, 'contactid' => 500, 'clientid' => $contactClientId, 'deleted' => 0];
    $person = ['id' => 200, 'parentid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => $contactClientId, 'deleted' => 0, 'name1' => 'Legacy First', 'name2' => 'Legacy Last', 'firstname' => 'Jane', 'lastname' => 'Doe', 'salutation' => 'Frau', 'title' => 'Dr.', 'position' => 'Buyer', 'department' => 'Purchasing'];
    $secondPerson = array_replace($person, ['id' => 204, 'firstname' => 'John', 'lastname' => 'Smith', 'salutation' => 'Herr', 'title' => '', 'position' => 'Manager', 'department' => 'Sales']);
    $email = ['id' => 300, 'parentid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => $contactClientId, 'deleted' => 0, 'email' => 'customer@example.invalid', 'suppressed' => 0];
    $message = ['id' => 400, 'documentid' => 10, 'contactid' => 100, 'campaignid' => null, 'module' => 'sales', 'controller' => 'quote', 'recipient' => $email['email'], 'clientid' => $contactClientId, 'deleted' => 0, 'subject' => 'Quote', 'body' => '[SALUTATION] [SIGNATURE]'];
    $adapter->rows = [
        'permission' => [['id' => 1, 'userid' => 42, 'clientid' => 7, 'deleted' => 0, 'sales' => '{"quote":["view","add"]}']],
        'quote' => [$quote, array_replace($quote, ['id' => 11, 'clientid' => 8]), array_replace($quote, ['id' => 12, 'clientid' => 70]), array_replace($quote, ['id' => 13, 'deleted' => 1])],
        'contact' => [$contact, array_replace($contact, ['id' => 101, 'contactid' => 501]), array_replace($contact, ['id' => 102, 'clientid' => 8])],
        'contactperson' => [$person, $secondPerson, array_replace($person, ['id' => 201, 'parentid' => 101]), array_replace($person, ['id' => 202, 'clientid' => 8]), array_replace($person, ['id' => 203, 'deleted' => 1])],
        'email' => [
            $email,
            array_replace($email, ['id' => 301, 'parentid' => 200, 'controller' => 'contactperson', 'email' => 'person@example.invalid']),
            array_replace($email, ['id' => 302, 'parentid' => 101]),
            array_replace($email, ['id' => 303, 'clientid' => 8]),
            array_replace($email, ['id' => 304, 'parentid' => 202, 'controller' => 'contactperson']),
            array_replace($email, ['id' => 305, 'deleted' => 1]),
            array_replace($email, ['id' => 306, 'suppressed' => 1]),
            array_replace($email, ['id' => 307, 'parentid' => 203, 'controller' => 'contactperson']),
            array_replace($email, ['id' => 308, 'module' => 'items']),
            array_replace($email, ['id' => 309, 'controller' => 'other']),
            array_replace($email, ['id' => 310, 'email' => 'invalid']),
            array_replace($email, ['id' => 311, 'parentid' => 204, 'controller' => 'contactperson', 'email' => 'john@example.invalid']),
            array_replace($email, ['id' => 312, 'parentid' => 204, 'controller' => 'contactperson', 'email' => 'john.alt@example.invalid']),
        ],
        'emailmessage' => [$message, array_replace($message, ['id' => 401, 'documentid' => 11]), array_replace($message, ['id' => 402, 'recipient' => 'unrelated@example.invalid']), array_replace($message, ['id' => 403, 'deleted' => 1]), array_replace($message, ['id' => 404, 'clientid' => 8]), array_replace($message, ['id' => 405, 'documentid' => 12]), array_replace($message, ['id' => 406, 'recipient' => 'person@example.invalid'])],
    ];
}

function sendQuoteEmail(array $post = [], array $params = [], string $method = 'POST'): bool
{
    global $adapter;
    $adapter->writes = [];
    DEEC_Email::$sent = [];
    DEEC_Feedback::$requests = [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('contacts')->setControllerName('email')->setActionName('send')
        ->setParams(array_replace(['documentid' => 10, 'contactid' => 500], $params))
        ->setPost(array_replace(['module' => 'sales', 'controller' => 'quote', 'recipient' => 300, 'subject' => 'Quote', 'body' => '[SALUTATION] [SIGNATURE]', 'files' => []], $post));
    Zend_Controller_Front::getInstance()->setRequest($request);
    try {
        $result = (new Application_Controller_Action_Helper_Email())->sendEmail('contacts', 'contact');
        return $result === null || !empty($result['ok']);
    } catch (Exception $e) {
        $expected = ['Email message is not available', 'Email recipient is not available', 'Invalid email context', 'Quote email client context is missing', 'Quote email permission denied', 'Invalid quote email context', 'Quote is not available', 'Quote contact is not available', 'Invalid quote email recipient', 'Email recipient does not belong to the quote contact'];
        if (!in_array($e->getMessage(), $expected, true)) throw $e;
        return false;
    }
}

function deniedQuoteEmail(array $post = [], array $params = [], string $method = 'POST'): void
{
    global $adapter;
    check(!sendQuoteEmail($post, $params, $method), 'Unauthorized email was accepted');
    check($adapter->writes === [] && DEEC_Email::$sent === [] && DEEC_Feedback::$requests === [], 'Denied request caused writes, feedback or SMTP activity');
}

foreach ([false, true] as $shared) {
    setupQuoteEmail($shared);
    foreach ([300 => 'customer@example.invalid', 301 => 'person@example.invalid', 311 => 'john@example.invalid', 312 => 'john.alt@example.invalid'] as $id => $address) {
        check(sendQuoteEmail(['recipient' => $id]), 'Allowed contact or contact-person email was rejected');
        check(count(DEEC_Email::$sent) === 1 && DEEC_Email::$sent[0]['to'] === [$address], 'Wrong recipient or duplicate send');
        check(DEEC_Feedback::$requests[0][2] === 10 && DEEC_Feedback::$requests[0][3] === 7, 'Feedback used contact tenant instead of quote tenant');
        check($adapter->writes[0][1]['clientid'] === ($shared ? 70 : 7), 'Existing email log tenant changed');
        check($adapter->writes[0][1]['contactid'] === 100, 'Email history was not assigned to the company contact');
    }
    check(sendQuoteEmail(['recipient' => 301]) && str_contains(DEEC_Email::$sent[0]['body'], 'Sehr geehrte Frau Dr. Doe,'), 'Female contact-person salutation was not personalized');
    check(sendQuoteEmail(['recipient' => 311]) && str_contains(DEEC_Email::$sent[0]['body'], 'Sehr geehrter Herr Smith,'), 'Male contact-person salutation was not personalized');
    check(sendQuoteEmail(['recipient' => 300]) && str_contains(DEEC_Email::$sent[0]['body'], 'Guten Tag,'), 'Company email did not receive a neutral greeting');
    foreach ([302, 303, 304, 305, 306, 307, 308, 309, 310, 999, 0, -1, [], '300 OR 1=1'] as $id) deniedQuoteEmail(['recipient' => $id]);
    foreach ([11, 12, 13, 999, 0, -1] as $documentId) deniedQuoteEmail([], ['documentid' => $documentId]);
    deniedQuoteEmail([], ['campaignid' => 999]);
    deniedQuoteEmail([], [], 'GET');

    // Stored metadata, not posted overrides, selects the source quote and recipient on resend.
    foreach ([400, 406] as $messageId) {
        check(sendQuoteEmail(['module' => 'contacts', 'controller' => 'contact', 'recipient' => 999], ['messageid' => $messageId, 'documentid' => 11]), 'Allowed resend failed');
        check(count(DEEC_Email::$sent) === 1 && DEEC_Feedback::$requests[0][2] === 10, 'Resend used posted source instead of stored source');
    }
    foreach ([401, 402, 403, 404, 405, 999] as $messageId) deniedQuoteEmail([], ['messageid' => $messageId]);

    foreach ([null, '', '{invalid', '{}', '{"quote":["view"]}', '{"quote":["add"]}', '{"invoice":["view","add"]}'] as $permissions) {
        $adapter->rows['permission'][0]['sales'] = $permissions;
        deniedQuoteEmail();
        deniedQuoteEmail(['module' => ' SALES ', 'controller' => 'Quote']);
        deniedQuoteEmail(['module' => 'contacts', 'controller' => 'contact'], ['messageid' => 400]);
    }

    setupQuoteEmail($shared);
    foreach ([['module' => []], ['controller' => []], ['module' => 'sáles'], ['controller' => 'quóte']] as $post) deniedQuoteEmail($post);
    $adapter->rows['permission'] = [];
    deniedQuoteEmail();
    setupQuoteEmail($shared);
    $user = Zend_Registry::get('User');
    $user['clientid'] = 0;
    Zend_Registry::set('User', $user);
    deniedQuoteEmail();
    setupQuoteEmail($shared);
    $adapter->rows['contact'][0]['deleted'] = 1;
    deniedQuoteEmail();
    setupQuoteEmail($shared);
    $adapter->rows['contactperson'][0]['deleted'] = 1;
    deniedQuoteEmail(['recipient' => 301]);
    deniedQuoteEmail([], ['messageid' => 406]);
    setupQuoteEmail($shared);
    $adapter->rows['email'][0]['email'] = 'changed@example.invalid';
    deniedQuoteEmail([], ['messageid' => 400]);
    setupQuoteEmail($shared);
    $adapter->rows['email'][0]['suppressed'] = 1;
    deniedQuoteEmail([], ['messageid' => 400]);

    setupQuoteEmail($shared);
    $emailDb = new Contacts_Model_DbTable_Email();
    check($emailDb->getById(300) !== null && $emailDb->getById(303) === null && $emailDb->getById(305) === null, 'Scoped recipient lookup ignored tenant or deletion');
    deniedQuoteEmail(['module' => 'contacts', 'controller' => 'contact', 'recipient' => 303]);
    deniedQuoteEmail(['module' => 'contacts', 'controller' => 'contact', 'recipient' => 305]);
    $form = (new Sales_Service_EmailFormFactory())->build($adapter->rows['quote'][0], $adapter->rows['contact'][0], 'quote');
    $options = $form->getElement('recipient')['options'];
    check($options[300] === 'customer@example.invalid' && $options[301] === 'person@example.invalid (Dr. Jane Doe)', 'Recipient option labels changed');
    check($options[311] === 'john@example.invalid (John Smith)' && $options[312] === 'john.alt@example.invalid (John Smith)', 'Multiple contact-person addresses were not listed');
    check(array_intersect(array_keys($options), [302, 303, 304, 305, 306, 307, 308, 309, 310]) === [], 'Unrelated, deleted, suppressed or invalid recipient appeared in quote form');

    foreach ([
        new Purchases_Service_EmailFormFactory(),
        new Processes_Service_EmailFormFactory(),
    ] as $factory) {
        $factoryOptions = $factory->build($adapter->rows['quote'][0], $adapter->rows['contact'][0], 'quote')->getElement('recipient')['options'];
        check($factoryOptions === $options, 'Document email factories do not share the contact-person recipient model');
    }

    $purchaseDocument = array_replace($adapter->rows['quote'][0], ['contactpersonid' => 204]);
    $purchaseEmailForm = (new Purchases_Service_EmailFormFactory())->build($purchaseDocument, $adapter->rows['contact'][0], 'purchaseorder');
    check($purchaseEmailForm->getValue('recipient') === '311', 'Selected purchase contact person was not preselected for email');
    check(isset($purchaseEmailForm->getElement('recipient')['options'][311], $purchaseEmailForm->getElement('recipient')['options'][312]), 'Email selection lost additional addresses of the selected contact person');

    $campaignPersons = (new QuoteEmailTestCampaignRecipientService())->contactPersons([$adapter->rows['contact'][0]]);
    check($campaignPersons[100][0]['display_name'] === 'Dr. Jane Doe', 'Campaign contact-person name is inconsistent');
    check($campaignPersons[100][0]['formal_salutation'] === 'Sehr geehrte Frau Dr. Doe,', 'Campaign salutation is inconsistent');
    check($campaignPersons[100][1]['email_list'] === 'john@example.invalid,john.alt@example.invalid', 'Campaign lost multiple contact-person email addresses');
}

setupQuoteEmail(true);
$user = Zend_Registry::get('User');
$user['clientid'] = 8;
Zend_Registry::set('User', $user);
deniedQuoteEmail([], ['messageid' => 400]);
check(sendQuoteEmail([], ['documentid' => 11]), 'Selected sibling tenant could not use its own quote with shared contacts');
check(DEEC_Feedback::$requests[0][3] === 8, 'Feedback did not follow selected tenant');

// Form generation can run in the sales tenant while recipients belong to the shared contact tenant.
setupQuoteEmail(true);
$client = Zend_Registry::get('Client');
$client['id'] = 7;
Zend_Registry::set('Client', $client);
$form = (new Sales_Service_EmailFormFactory())->build($adapter->rows['quote'][0], $adapter->rows['contact'][0], 'quote');
$options = $form->getElement('recipient')['options'];
check(isset($options[300], $options[301]), 'Sales form lost shared contact or contact-person recipients');
check(Zend_Registry::get('Client')['id'] === 7, 'Recipient lookup changed the global tenant');

setupQuoteEmail();
$adapter->rows['permission'] = [];
check(sendQuoteEmail(['module' => 'contacts', 'controller' => 'contact']), 'Ordinary contact email unexpectedly needs quote permission');
check(DEEC_Feedback::$requests === [], 'Ordinary contact email created quote feedback');
check(sendQuoteEmail(['module' => 'contacts', 'controller' => 'contact', 'recipient' => 301]), 'Ordinary contact-person email failed');
check($adapter->writes[0][1]['contactid'] === 100, 'Ordinary contact-person email history used the person ID');
check(str_contains(DEEC_Email::$sent[0]['body'], 'Sehr geehrte Frau Dr. Doe,'), 'Ordinary contact-person email was not personalized');

// Public unsubscribe links still resolve an email before validating its token and tenant.
Zend_Registry::getInstance()->offsetUnset('User');
Zend_Registry::getInstance()->offsetUnset('Client');
check((new Contacts_Model_DbTable_Email())->getEmail(300)['id'] === 300, 'Public unsubscribe email lookup now requires a logged-in tenant');

echo 'PASS: ' . $checks . " quote email permission, recipient, resend and tenant-sharing checks\n";
