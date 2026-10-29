<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/smtp-routing.php (configuration checks only, no SMTP connection).
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

function emailConfig(string $module): array
{
    static $method;
    if (!$method) {
        $method = new ReflectionMethod(Application_Controller_Action_Helper_Email::class, 'getEmailConfig');
        $method->setAccessible(true);
    }
    return $method->invoke(new Application_Controller_Action_Helper_Email(), $module);
}

function expectConfigError(string $module, string $message): void
{
    try {
        emailConfig($module);
    } catch (RuntimeException $e) {
        check($e->getMessage() === $message, 'Unexpected SMTP configuration error: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Missing SMTP configuration was accepted');
}

$central = [
    'smtphost' => 'email-smtp.central.example.invalid',
    'smtpport' => 587,
    'smtpauth' => 1,
    'smtpsecure' => 'tls',
    'smtpuser' => 'central-user',
    'smtppass' => 'central-password',
];
Zend_Registry::set('Config', $central);
Zend_Registry::set('Shop', [
    'id' => 41,
    'clientid' => 7,
    'title' => 'Example Shop',
    'emailsender' => 'shop@example.invalid',
    'smtphost' => 'smtp.shop.example.invalid',
    'smtpuser' => 'shop-user',
    'smtppass' => 'shop-password',
]);
Zend_Registry::set('User', [
    'id' => 42,
    'clientid' => 7,
    'email' => 'user@example.invalid',
    'emailsender' => 'Example User',
    'smtphost' => 'smtp.user.example.invalid',
    'smtpuser' => 'smtp-user@example.invalid',
    'smtppass' => 'user-password',
]);

[$smtp, $fromEmail, $fromName] = emailConfig('shops');
check($smtp === [
    'host' => 'smtp.shop.example.invalid',
    'auth' => true,
    'username' => 'shop-user',
    'password' => 'shop-password',
    'secure' => 'ssl',
    'port' => 465,
], 'Shop email did not use the shop SMTP configuration');
check($fromEmail === 'shop@example.invalid' && $fromName === 'Example Shop', 'Shop sender changed');

[$smtp, $fromEmail, $fromName] = emailConfig('contacts');
check($smtp['host'] === 'smtp.user.example.invalid' && $smtp['username'] === 'smtp-user@example.invalid', 'Normal email did not use the user SMTP configuration');
check($smtp['host'] !== $central['smtphost'], 'Normal email used the central SMTP configuration');
check($fromEmail === 'smtp-user@example.invalid' && $fromName === 'Example User', 'Normal email sender changed unexpectedly');

$shop = Zend_Registry::get('Shop');
$shop['smtphost'] = '';
Zend_Registry::set('Shop', $shop);
expectConfigError('shops', 'Shop SMTP host is missing');
$shop['smtphost'] = "smtp.shop.example.invalid\ninvalid";
Zend_Registry::set('Shop', $shop);
expectConfigError('shops', 'Shop SMTP host is invalid');
$shop['smtphost'] = 'smtp.shop.example.invalid';
$shop['smtppass'] = '';
Zend_Registry::set('Shop', $shop);
expectConfigError('shops', 'Shop SMTP password is missing');
$shop['smtppass'] = 'shop-password';
$shop['emailsender'] = 'invalid';
Zend_Registry::set('Shop', $shop);
expectConfigError('shops', 'Shop sender email is missing or invalid');

$user = Zend_Registry::get('User');
$user['smtpuser'] = '';
Zend_Registry::set('User', $user);
expectConfigError('contacts', 'User SMTP username is missing');
$user['smtpuser'] = 'invalid';
Zend_Registry::set('User', $user);
expectConfigError('contacts', 'User SMTP username must be a valid sender email address');

$campaignSmtp = DEEC_Email::buildSmtpConfig($central);
check($campaignSmtp['host'] === $central['smtphost'] && $campaignSmtp['port'] === 587 && $campaignSmtp['secure'] === 'tls', 'Central campaign SMTP configuration changed');
$emailSource = file_get_contents($root . '/library/DEEC/Email.php');
check(str_contains($emailSource, '$smtp = $this->getSmtpConfig($campaign[\'clientid\']);'), 'Campaign no longer loads SMTP by campaign client');
check(str_contains($emailSource, '$smtpConfig = $smtp ? self::buildSmtpConfig($smtp) : null;'), 'Campaign no longer builds the central SMTP configuration');
$helperSource = file_get_contents($root . '/application/controllers/helpers/Email.php');
check(!str_contains($helperSource, 'Application_Model_DbTable_Config') && !str_contains($helperSource, "Zend_Registry::get('Config')"), 'Direct email helper still reads the central SMTP configuration');

echo "OK ({$checks} checks)\n";
