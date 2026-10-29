<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/shop-email-recipients.php (in-memory fixtures, no SMTP connection).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
if (!defined('BASE_PATH')) define('BASE_PATH', $root);
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

class Shops_Form_Contact
{
    public static array $values = [];
    public function isValid($data): bool { return true; }
    public function getValues(): array { return self::$values; }
}

class Shops_Form_Checkout extends Shops_Form_Contact {}

class Shops_Model_DbTable_Emailtemplate
{
    public function getEmailtemplate(string $module, string $controller): ?array
    {
        return ['subject' => 'Shop message', 'body' => '#MESSAGES_MESSAGE# #USER_DETAILS#'];
    }
}

class Shops_Model_DbTable_Emailmessage
{
    public static array $inserted = [];
    public static array $rows = [];
    private static int $nextId = 1000;

    public function addEmailmessage(array $data): int
    {
        $id = ++self::$nextId;
        $data['clientid'] = (int)Zend_Registry::get('Shop')['clientid'];
        self::$inserted[$id] = $data;
        self::$rows[$id] = $data;
        return $id;
    }

    public function updateEmailmessage(int $id, array $data): void
    {
        self::$rows[$id] = array_replace(self::$rows[$id], $data);
    }

    public static function reset(): void
    {
        self::$inserted = [];
        self::$rows = [];
    }
}

class DEEC_Email
{
    public static array $behaviors = [];
    public static array $smtp = [];
    public static array $messages = [];

    public static function prepareMessageData(array $message): array
    {
        return [
            'contactid' => (int)($message['contactid'] ?? 0),
            'documentid' => (int)($message['documentid'] ?? 0),
            'campaignid' => !empty($message['campaignid']) ? (int)$message['campaignid'] : null,
            'feedbackid' => !empty($message['feedbackid']) ? (int)$message['feedbackid'] : null,
            'module' => (string)($message['module'] ?? ''),
            'controller' => (string)($message['controller'] ?? ''),
            'sender' => trim((string)($message['sender'] ?? '')),
            'recipient' => trim((string)($message['recipient'] ?? '')),
            'cc' => self::addresses($message['cc'] ?? ''),
            'bcc' => self::addresses($message['bcc'] ?? ''),
            'replyto' => trim((string)($message['replyto'] ?? '')) ?: null,
            'subject' => (string)($message['subject'] ?? ''),
            'body' => (string)($message['body'] ?? ''),
            'attachment' => implode(',', (array)($message['attachments'] ?? [])),
            'response' => 'pending',
        ];
    }

    public static function sendMessage(array $smtp, array $message): array
    {
        self::$smtp[] = $smtp;
        self::$messages[] = $message;
        $behavior = array_shift(self::$behaviors);
        if ($behavior instanceof Exception) throw $behavior;
        if ($behavior === true) return ['sent' => true, 'error' => ''];
        if (is_array($behavior)) return $behavior;
        return ['sent' => false, 'error' => 'Fixture SMTP rejection'];
    }

    private static function addresses($addresses): ?string
    {
        $values = is_array($addresses) ? $addresses : explode(',', (string)$addresses);
        $values = array_values(array_unique(array_filter(array_map('trim', $values))));
        return $values ? implode(',', $values) : null;
    }
}

class ShopEmailRecipientFlashMessenger extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { throw new RuntimeException('Shop mail created a flash message'); }
}

require_once $root . '/application/controllers/helpers/Email.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

Zend_Controller_Action_HelperBroker::addHelper(new ShopEmailRecipientFlashMessenger());
$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
$viewRenderer->view = new class {
    public function translate(string $message): string { return $message; }
};

function sendShopEmail(string $customerEmail, string $internalEmail, array $behaviors): array
{
    Zend_Registry::set('Shop', [
        'id' => 41,
        'clientid' => 7,
        'title' => 'Example Shop',
        'url' => 'https://shop.example.invalid',
        'logo' => 'logo.png',
        'emailsender' => $internalEmail,
        'smtphost' => 'smtp.shop.example.invalid',
        'smtpuser' => 'shop-smtp-user',
        'smtppass' => 'shop-smtp-password',
    ]);
    $post = [
        'email' => $customerEmail,
        'name' => 'Ada Example',
        'subject' => 'Question',
        'message' => 'Please contact me.',
        'cc' => 'copy-one@example.invalid, copy-two@example.invalid',
        'bcc' => 'blind@example.invalid',
        'replyto' => 'reply@example.invalid',
        '__attach_paths' => [__FILE__],
    ];
    Shops_Form_Contact::$values = $post;
    Shops_Model_DbTable_Emailmessage::reset();
    DEEC_Email::$behaviors = $behaviors;
    DEEC_Email::$smtp = [];
    DEEC_Email::$messages = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('shops')->setControllerName('contact')->setActionName('send')->setPost($post);
    Zend_Controller_Front::getInstance()->setRequest($request);

    return (new Application_Controller_Action_Helper_Email())->sendEmail('shops', 'contact');
}

$result = sendShopEmail('customer@example.invalid', 'shop@example.invalid', [true, true]);
check($result === ['ok' => true, 'sent' => 2, 'failed' => 0, 'customerAccepted' => true, 'internalAccepted' => true], 'Two successful shop sends returned the wrong result');
check(count(Shops_Model_DbTable_Emailmessage::$inserted) === 2 && count(DEEC_Email::$messages) === 2, 'Different recipients did not create two attempts');
$inserted = array_values(Shops_Model_DbTable_Emailmessage::$inserted);
$rows = array_values(Shops_Model_DbTable_Emailmessage::$rows);
check(array_column($inserted, 'recipient') === ['customer@example.invalid', 'shop@example.invalid'], 'Email history does not contain each actual recipient');
check(array_column($inserted, 'response') === ['pending', 'pending'], 'Email attempts were not initially stored as pending');
check(array_column($rows, 'response') === ['sent', 'sent'], 'Successful recipient attempts were not marked sent');
check(array_column($inserted, 'clientid') === [7, 7], 'Shop email history lost tenant ownership');
check(DEEC_Email::$messages[0]['to'] === ['customer@example.invalid'] && DEEC_Email::$messages[1]['to'] === ['shop@example.invalid'], 'Recipients still share an SMTP envelope');
check(DEEC_Email::$messages[0]['cc'] === 'copy-one@example.invalid,copy-two@example.invalid' && DEEC_Email::$messages[1]['cc'] === null, 'CC recipients were lost or duplicated');
check(DEEC_Email::$messages[0]['bcc'] === 'blind@example.invalid' && DEEC_Email::$messages[1]['bcc'] === null, 'BCC recipients were lost or duplicated');
check(DEEC_Email::$messages[0]['attachments'] === [__FILE__] && DEEC_Email::$messages[1]['attachments'] === [__FILE__], 'Attachments were not retained for both recipients');
check(DEEC_Email::$messages[0]['fromEmail'] === 'shop@example.invalid' && DEEC_Email::$messages[1]['fromEmail'] === 'shop@example.invalid', 'Shop sender changed between attempts');
check(DEEC_Email::$messages[0]['replyTo'] === 'reply@example.invalid' && DEEC_Email::$messages[1]['replyTo'] === 'reply@example.invalid', 'Reply-To changed between attempts');
check(DEEC_Email::$messages[0]['subject'] === DEEC_Email::$messages[1]['subject'] && DEEC_Email::$messages[0]['body'] === DEEC_Email::$messages[1]['body'], 'Existing template content changed between recipients');
check(DEEC_Email::$smtp[0]['host'] === 'smtp.shop.example.invalid' && DEEC_Email::$smtp[1]['host'] === 'smtp.shop.example.invalid', 'An attempt did not use shop SMTP');
check(DEEC_Email::$messages[0]['emailmessageId'] !== DEEC_Email::$messages[1]['emailmessageId'], 'Attempts did not receive separate message IDs');

$result = sendShopEmail('SHOP@example.invalid', 'shop@example.invalid', [true]);
check($result['customerAccepted'] === true && $result['internalAccepted'] === true && $result['sent'] === 1, 'Identical recipients did not share their accepted result');
check(count(Shops_Model_DbTable_Emailmessage::$inserted) === 1 && count(DEEC_Email::$messages) === 1, 'Identical recipients created duplicate attempts');

$result = sendShopEmail('customer@example.invalid', 'shop@example.invalid', [true, false]);
check($result['customerAccepted'] === true && $result['internalAccepted'] === false, 'Internal rejection changed the customer result');
check($result['ok'] === false && $result['sent'] === 1 && $result['failed'] === 1, 'Mixed recipient result counters are wrong');
check(array_column(Shops_Model_DbTable_Emailmessage::$rows, 'response') === ['sent', 'Fixture SMTP rejection'], 'Internal rejection was not stored separately');

$result = sendShopEmail('customer@example.invalid', 'shop@example.invalid', [false, true]);
check($result['customerAccepted'] === false && $result['internalAccepted'] === true, 'Customer rejection changed the internal result');
check(array_column(Shops_Model_DbTable_Emailmessage::$rows, 'response') === ['Fixture SMTP rejection', 'sent'], 'Customer rejection was not stored separately');

$result = sendShopEmail('customer@example.invalid', 'shop@example.invalid', [false, false]);
check($result === ['ok' => false, 'sent' => 0, 'failed' => 2, 'customerAccepted' => false, 'internalAccepted' => false], 'Two rejected recipients returned the wrong result');

$result = sendShopEmail('customer@example.invalid', 'shop@example.invalid', [new RuntimeException('Fixture transport exception'), true]);
check($result['customerAccepted'] === false && $result['internalAccepted'] === true, 'First transport exception prevented or changed the second attempt');
check(count(DEEC_Email::$messages) === 2 && array_column(Shops_Model_DbTable_Emailmessage::$rows, 'response') === ['Fixture transport exception', 'sent'], 'Transport exception was not isolated and stored');

$result = sendShopEmail('invalid-recipient', 'shop@example.invalid', [true]);
check($result['customerAccepted'] === false && $result['internalAccepted'] === true, 'Invalid customer recipient was silently ignored or stopped the internal attempt');
check(count(Shops_Model_DbTable_Emailmessage::$inserted) === 2 && count(DEEC_Email::$messages) === 1, 'Invalid recipient did not receive a tracked failed attempt');
check(array_column(Shops_Model_DbTable_Emailmessage::$rows, 'response') === ['Invalid shop email recipient', 'sent'], 'Invalid recipient error was not stored');

$contactSource = file_get_contents($root . '/application/modules/shops/controllers/ContactController.php');
$inquirySource = file_get_contents($root . '/application/modules/shops/controllers/InquiryController.php');
$checkoutSource = file_get_contents($root . '/application/modules/shops/controllers/CheckoutController.php');
check(str_contains($contactSource, "notificationSent = !empty(\$emailResult['customerAccepted'])"), 'Contact success does not follow customer acceptance');
check(substr_count($inquirySource, "return !empty(\$result['customerAccepted'])") === 2, 'Inquiry or offer success does not follow customer acceptance');
check(str_contains($checkoutSource, "notificationSent = !empty(\$emailResult['customerAccepted'])"), 'Checkout success does not follow customer acceptance');
check(str_contains($contactSource . $inquirySource . $checkoutSource, 'internal notification was not accepted by SMTP'), 'Internal notification failures are not logged');

echo "OK ({$checks} checks)\n";
