<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/shop-email-content.php (rendering checks only, no SMTP connection).
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

class Shops_Model_DbTable_Emailtemplate
{
    public function getEmailtemplate(string $module, string $controller): ?array
    {
        return [
            'subject' => 'Test subject',
            'body' => '#USER_DETAILS#|#MESSAGES_MESSAGE#',
        ];
    }
}

require_once $root . '/application/controllers/helpers/Email.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

class ShopEmailContentTestView
{
    public array $formData = [];
    public ?string $downloadUrl = null;
    public ?bool $notificationSent = null;
    public bool $stored = true;
    public bool $duplicate = false;
    public string $title = '';

    public function translate(string $message): string { return $message; }
    public function headTitle(string $title): void {}
    public function Messages(): string { return ''; }
    public function escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

    public function render(string $script, array $values): string
    {
        foreach ($values as $key => $value) $this->$key = $value;
        ob_start();
        include $script;
        return (string)ob_get_clean();
    }
}

function renderSuccess(string $view, ?bool $notificationSent, array $values = []): string
{
    global $root;
    return (new ShopEmailContentTestView())->render(
        $root . '/application/modules/shops/views/scripts/' . $view . '/success.phtml',
        array_replace(['notificationSent' => $notificationSent], $values)
    );
}

function checkNoTechnicalStatus(string $html, string $context): void
{
    check(stripos($html, 'smtp') === false, $context . ' exposes an SMTP term');
    check(stripos($html, 'zur weiteren Übermittlung') === false, $context . ' claims SMTP acceptance');
    check(stripos($html, 'zugestellt') === false, $context . ' claims final delivery');
}

$contactSuccess = renderSuccess('contact', true);
check(str_contains($contactSuccess, 'Vielen Dank für Ihre Kontaktaufnahme!'), 'Contact success heading changed');
check(str_contains($contactSuccess, 'Ihre Anfrage ist bei uns eingegangen. Wir werden uns schnellstmöglich bei Ihnen melden.'), 'Contact success text is missing');
check(!str_contains($contactSuccess, 'E-Mail-Bestätigung'), 'Successful contact displays an additional email status');
checkNoTechnicalStatus($contactSuccess, 'Contact success');

$contactFailure = renderSuccess('contact', false);
$inquiryFailureText = 'Ihre Anfrage wurde gespeichert. Leider konnten wir derzeit keine E-Mail-Bestätigung versenden. Bitte senden Sie Ihre Anfrage nicht erneut.';
check(substr_count($contactFailure, $inquiryFailureText) === 1, 'Contact failure message is missing or duplicated');
checkNoTechnicalStatus($contactFailure, 'Contact failure');

$contactNotStored = renderSuccess('contact', null, ['stored' => false]);
check(!str_contains($contactNotStored, 'Ihre Anfrage ist bei uns eingegangen.'), 'Non-stored contact is described as received');
check(!str_contains($contactNotStored, 'Ihre Anfrage wurde gespeichert.'), 'Non-stored contact is described as stored');
$contactDuplicate = renderSuccess('contact', null, ['duplicate' => true]);
check(str_contains($contactDuplicate, 'Diese Anfrage war bereits gespeichert und wurde nicht erneut angelegt.'), 'Contact duplicate notice changed');

$inquirySuccess = renderSuccess('inquiry', true);
check(str_contains($inquirySuccess, 'Vielen Dank für Ihre Anfrage!'), 'Inquiry success heading changed');
check(str_contains($inquirySuccess, 'Ihre Anfrage wurde gespeichert. Wir werden sie schnellstmöglich bearbeiten.'), 'Inquiry success text is missing');
check(!str_contains($inquirySuccess, 'E-Mail-Bestätigung'), 'Successful inquiry displays an additional email status');
checkNoTechnicalStatus($inquirySuccess, 'Inquiry success');
$inquiryFailure = renderSuccess('inquiry', false);
check(substr_count($inquiryFailure, $inquiryFailureText) === 1, 'Inquiry failure message is missing or duplicated');
checkNoTechnicalStatus($inquiryFailure, 'Inquiry failure');

$checkoutSuccess = renderSuccess('checkout', true);
check(str_contains($checkoutSuccess, 'Vielen Dank für Ihre Bestellung!'), 'Checkout success heading changed');
check(str_contains($checkoutSuccess, 'Wir haben Ihre Bestellung erhalten und werden sie schnellstmöglich bearbeiten.'), 'Checkout success text is missing');
check(!str_contains($checkoutSuccess, 'E-Mail-Bestätigung'), 'Successful checkout displays an additional email status');
checkNoTechnicalStatus($checkoutSuccess, 'Checkout success');
$checkoutFailure = renderSuccess('checkout', false);
$checkoutFailureText = 'Ihre Bestellung wurde gespeichert. Leider konnten wir derzeit keine E-Mail-Bestätigung versenden. Bitte geben Sie Ihre Bestellung nicht erneut auf.';
check(substr_count($checkoutFailure, $checkoutFailureText) === 1, 'Checkout failure message is missing or duplicated');
checkNoTechnicalStatus($checkoutFailure, 'Checkout failure');

Zend_Registry::set('Shop', [
    'title' => 'Example Shop',
    'url' => 'https://shop.example.invalid',
    'emailsender' => 'shop@example.invalid',
]);
$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
$viewRenderer->view = new class {
    public function translate(string $message): string { return $message; }
};
$bodyMethod = new ReflectionMethod(Application_Controller_Action_Helper_Email::class, 'getEmailBody');
$bodyMethod->setAccessible(true);
[$body] = $bodyMethod->invoke(
    new Application_Controller_Action_Helper_Email(),
    'shops',
    'contact',
    [
        'name' => 'Ada Example',
        'firma' => 'Example GmbH',
        'email' => 'ada@example.invalid',
        'telefon' => '+49 30 123456',
        'custom_request' => 'Custom requirement',
        'inquiry_token' => 'technical-inquiry-token',
        'fax_number' => 'honeypot-value',
        'privacy' => 'privacy-value',
    ],
    [
        'name' => 'Ada Example',
        'subject' => 'Question',
        'message' => 'Customer message',
    ],
    null
);
foreach (['technical-inquiry-token', 'honeypot-value', 'privacy-value', 'Inquiry token', 'Fax number', 'Privacy'] as $excluded) {
    check(!str_contains($body, $excluded), 'Technical field leaked into #USER_DETAILS#: ' . $excluded);
}
foreach (['Ada Example', 'Example GmbH', 'ada@example.invalid', '+49 30 123456', 'Custom requirement', 'Customer message'] as $customerValue) {
    check(str_contains($body, $customerValue), 'Customer email content is missing: ' . $customerValue);
}

$helperSource = file_get_contents($root . '/application/controllers/helpers/Email.php');
check(substr_count($helperSource, "if(\$module !== 'shops') {") >= 2 && substr_count($helperSource, "addMessage('MESSAGES_EMAIL_SENT_") === 2, 'Shop flash messages are not suppressed for both send outcomes');
foreach (['ContactController.php', 'InquiryController.php', 'CheckoutController.php'] as $controller) {
    $source = file_get_contents($root . '/application/modules/shops/controllers/' . $controller);
    check(!str_contains($source, "addMessage('MESSAGES_EMAIL_SENT_ERROR')"), $controller . ' still duplicates the shop email failure message');
}
$contactController = file_get_contents($root . '/application/modules/shops/controllers/ContactController.php');
check(str_contains($contactController, "if (!empty(\$data['fax_number']))"), 'Contact honeypot handling changed');
check(str_contains($contactController, '$this->contactDataSession->stored = false;'), 'Honeypot request no longer remains unstored');
check(strpos($contactController, '->saveOnce(') < strpos($contactController, "->sendEmail('shops', 'contact'"), 'Contact email is attempted before persistence');
$inquiryController = file_get_contents($root . '/application/modules/shops/controllers/InquiryController.php');
check(strpos($inquiryController, '->saveOnce(') < strpos($inquiryController, 'sendInquiryNotification('), 'Inquiry email is attempted before persistence');
$checkoutController = file_get_contents($root . '/application/modules/shops/controllers/CheckoutController.php');
check(strpos($checkoutController, '->addOrder([') < strpos($checkoutController, "->sendEmail('shops', 'checkout'"), 'Checkout email is attempted before order persistence');
check(strpos($checkoutController, '->addOrderpos([') < strpos($checkoutController, "->sendEmail('shops', 'checkout'"), 'Checkout email is attempted before position persistence');
$messages = require $root . '/languages/de_DE/default/messages.php';
check($messages['MESSAGES_EMAIL_SENT_SUCCESS'] === 'Die E-Mail wurde zum Versand übergeben.', 'Normal email success message claims more than handoff');

echo "OK ({$checks} checks)\n";
