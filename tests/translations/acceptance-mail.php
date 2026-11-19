<?php

// Real template expansion and PHPMailer transport, connected only to the runner's loopback sink.
if (PHP_SAPI !== 'cli' || !preg_match('/^[0-9]{1,5}$/D', (string)getenv('DEWAWI_T3_SMTP_PORT'))) { exit(2); }
ob_start(); require dirname(__DIR__) . '/documents/pdf.php'; ob_end_clean();
mkdir(BASE_PATH . '/library/PHPMailer', 0700, true);
foreach (['Exception', 'PHPMailer', 'SMTP'] as $name) copy($root . '/library/PHPMailer/' . $name . '.php', BASE_PATH . '/library/PHPMailer/' . $name . '.php');
class Shops_Model_DbTable_Emailtemplate
{
    public function getEmailtemplate(string $module, string $controller): array
    {
        return ['subject' => 't#tMESSAGES_THANK_YOU_CONTACTING_USt#t', 'body' => '#HELLO_NAME# | t#tSHOPS_CONTACT_FORMt#t'];
    }
}
require_once $root . '/application/controllers/helpers/Email.php';
$method = new ReflectionMethod(Application_Controller_Action_Helper_Email::class, 'getEmailBody');
$results = [];
foreach (['de_DE', 'en_US'] as $locale) {
    $ui = new DEEC_Translate($locale === 'de_DE' ? 'en_US' : 'de_DE'); $ui->loadModule('sales');
    Zend_Registry::set('DEEC_Translate', $ui);
    Zend_Registry::set('Shop', ['language' => $locale, 'title' => 'Synthetic Shop', 'url' => 'https://example.invalid', 'emailsender' => 'fixture@example.invalid']);
    [$body, $subject] = $method->invoke(new Application_Controller_Action_Helper_Email(), 'shops', 'contact', [], ['name' => 'Jörg', 'subject' => 'Fixture', 'message' => 'Fixture'], []);
    $tr = new DEEC_Translate($locale); $tr->loadModule('sales');
    $pdf = new TCPDF(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage(); $pdf->SetFont('freesans', '', 10);
    $pdf->Write(0, $tr->t('DOCUMENTS_QUOTE_ID'));
    $attachment = BASE_PATH . '/fixture-' . $locale . '.pdf'; $pdf->Output($attachment, 'F');
    $smtp = DEEC_Email::sendMessage(['host' => '127.0.0.1', 'port' => (int)getenv('DEWAWI_T3_SMTP_PORT'), 'auth' => false, 'secure' => '', 'timeout' => 5],
        ['fromEmail' => 'fixture@example.invalid', 'to' => ['sink@example.invalid'], 'subject' => $subject, 'body' => $body, 'attachments' => [$attachment]]);
    $results[] = ['locale' => $locale, 'smtp_accepted' => $smtp['sent'], 'subject' => $subject, 'body' => $body,
        'attachment_name' => basename($attachment), 'attachment_sha256' => hash_file('sha256', $attachment), 'ui_context_preserved' => Zend_Registry::get('DEEC_Translate') === $ui];
}
echo json_encode(['scope' => 'synthetic template acquisition, actual helper expansion, TCPDF attachment and loopback-only PHPMailer SMTP', 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
