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
    public function url(array $params): string {
        return '/fixture/' . $params['module'] . '/' . $params['controller'] . '/' . $params['action'] . '/id/' . (int)($params['id'] ?? 100)
            . (isset($params['target']) ? '/target/' . $params['target'] : '');
    }
    public function getValue(string $name) { return $name === 'id' ? 100 : ''; }
    public function renderElement(string $name): string { return '<input name="' . $this->escape($name) . '">'; }
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

// Module helpers have identical ZF1 class names, so render each module in its own process.
$documentModule = $argv[1] ?? null;
if ($documentModule !== null) {
    if (!in_array($documentModule, ['sales', 'purchases'], true)) throw new InvalidArgumentException('Invalid fixture module');
    require_once $root . '/application/modules/' . $documentModule . '/views/helpers/Finish.php';
    require_once $root . '/application/modules/' . $documentModule . '/views/helpers/Actions.php';
    foreach (['de_DE', 'en_US'] as $locale) {
        $view = new NotificationTestView();
        $view->catalog = require $root . '/languages/' . $locale . '/default/document.php';
        $view->form = $view;
        $view->module = $documentModule;
        $view->documentValidation = ['status' => 'success'];
        $controllers = $documentModule === 'sales' ? ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote'] : ['quoterequest', 'purchaseorder'];
        foreach ($controllers as $controller) {
            $view->controller = $controller;
            $finish = (new Zend_View_Helper_Finish())->setView($view)->finish();
            check(str_contains($finish, 'onclick="previewPdf(this)"'), 'Explicit preview button missing in ' . $documentModule);
            check(str_contains($finish, 'data-document-finalize') && str_contains($finish, '/' . $controller . '/save/id/100'), 'Finalization route changed');
            check(str_contains($finish, 'data-document-download') && str_contains($finish, 'target="_blank"') && str_contains($finish, '/' . $controller . '/download/id/100'), 'Native PDF download changed');
            check(str_contains($finish, 'id="output" class="dw-finish__preview"') && str_contains($finish, 'dw-document-validation'), 'Existing preview or validation container missing');
            ob_start();
            (new Zend_View_Helper_Actions())->setView($view)->Actions();
            $actions = ob_get_clean();
            $links = substr_count($actions, '<a ');
            check($links > 0 && substr_count($actions, 'data-document-generate') === $links, 'A generation link lacks click protection');
            check(substr_count($actions, '/' . $controller . '/generate/id/100/target/') === $links && !str_contains($actions, 'target="_blank"'), 'Generation route or navigation target changed');
        }
    }
    echo 'PASS: ' . $checks . ' ' . $documentModule . " document helper rendering checks\n";
    exit;
}

foreach (['de_DE', 'en_US'] as $locale) {
    $view = new NotificationTestView();
    $view->catalog = array_replace(require $root . '/languages/' . $locale . '/default/messages.php', require $root . '/languages/' . $locale . '/default/document.php');
    $translations = (new Zend_View_Helper_Messages())->setView($view)->getTranslations();
    check(count($translations) === 31 && !in_array('', $translations, true), $locale . ': incomplete browser translations');
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

    $view->messages = ['MESSAGES_CREATED'];
    ob_start();
    $view->Messages();
    $createdHtml = ob_get_clean();
    check(str_contains($createdHtml, 'dw-alert--success') && str_contains($createdHtml, $view->catalog['MESSAGES_CREATED']), $locale . ': creation flash has no translated success state');
    $view->messages = [];

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

foreach (['sales', 'purchases'] as $documentModule) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($documentModule), $status);
    if ($status !== 0) throw new RuntimeException('Document helper fixture failed: ' . $documentModule);
}
