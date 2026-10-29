<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/notifications.php (rendered views only, no database or SMTP).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/View.php';
require_once 'Zend/View/Helper/Abstract.php';
require_once $root . '/application/views/helpers/Messages.php';

class NotificationTestView extends Zend_View
{
    public array $catalog = [];
    public function translate(string $key): string { return $this->catalog[$key] ?? $key; }
    public function url(array $params): string { return '/fixture/contact/' . (int)$params['id']; }
    public function baseUrl(): string { return '/fixture'; }
    public function Messages() { (new Zend_View_Helper_Messages())->setView($this)->Messages(); }
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

foreach (['de_DE', 'en_US'] as $locale) {
    $view = new NotificationTestView();
    $view->catalog = require $root . '/languages/' . $locale . '/default/messages.php';
    $translations = (new Zend_View_Helper_Messages())->setView($view)->getTranslations();
    check(count($translations) === 14 && !in_array('', $translations, true), $locale . ': incomplete browser translations');
    foreach ($translations as $key => $text) check($key !== $text, $locale . ': untranslated browser key ' . $key);
    $view->messages = [
        'MESSAGES_EMAIL_SENT_SUCCESS', 'MESSAGES_EMAIL_SENT_SUCCESS',
        'MESSAGES_EMAIL_SENT_ERROR', ['type' => 'warning', 'message' => 'Warning fixture'],
        ['type' => 'info', 'message' => 'Information fixture'],
        ['type' => '"><script>alert(1)</script>', 'message' => '<img src=x onerror="alert(1)">'],
    ];
    ob_start();
    $view->Messages();
    $html = ob_get_clean();
    check(substr_count($html, 'id="messages"') === 1, $locale . ': duplicate message IDs');
    check(substr_count($html, $view->catalog['MESSAGES_EMAIL_SENT_SUCCESS']) === 1, $locale . ': duplicate flash text');
    foreach (['success', 'danger', 'warning', 'info'] as $type) check(str_contains($html, 'dw-alert--' . $type), $locale . ': missing severity ' . $type);
    check(!str_contains($html, '<img') && !str_contains($html, '<script>') && str_contains($html, '&lt;img'), $locale . ': unsafe flash content');
    check(substr_count($html, 'role="alert"') === 1 && substr_count($html, 'role="status"') === 3, $locale . ': incorrect announcement roles');
    check(substr_count($html, 'aria-live="assertive"') === 1 && substr_count($html, 'aria-live="polite"') === 3, $locale . ': incorrect live regions');
    check(substr_count($html, 'class="dw-alert__close"') === 4 && str_contains($html, $view->catalog['MESSAGES_DISMISS']), $locale . ': missing translated dismissal');
    $view->messages = [];
    ob_start();
    $view->Messages();
    check(ob_get_clean() === '', $locale . ': empty flash rendered a container');

    // Render the real email history view: transport details stay out of the user interface.
    $view->setScriptPath($root . '/application/modules/contacts/views/scripts');
    $view->controller = 'email';
    $view->module = 'contacts';
    $view->users = [];
    $view->emailmessages = [[
        'id' => 400, 'contactid' => 100, 'recipient' => 'fixture@example.invalid', 'subject' => 'Fixture',
        'body' => 'Fixture body', 'attachment' => '', 'messagesent' => '', 'messagesentby' => 42,
        'accesstime' => '', 'response' => 'SMTP fixture transport detail',
    ]];
    $html = $view->render('email/index.phtml');
    check(!str_contains($html, 'SMTP fixture transport detail') && str_contains($html, $view->catalog['MESSAGES_EMAIL_SENT_ERROR']), $locale . ': transport detail exposed or failure text missing');
    check(str_contains($html, 'resendMessage(400, this)') && str_contains($html, $view->catalog['MESSAGES_EMAIL_RESEND']), $locale . ': resend button lacks context or translation');
}

echo 'PASS: ' . $checks . " notification rendering, flash, translation and email history checks\n";
