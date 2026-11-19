<?php

// Reuse the archive/tenant fixture; render actual TCPDF documents into temporary files only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require dirname(__DIR__) . '/documents/pdf.php';
$archiveResult = trim(ob_get_clean());
ini_set('display_errors', '0');

class TranslationAcceptancePdf extends DEEC_Pdf
{
    public function renderDocument(array $document, string $controller): string
    {
        $payload = $this->buildDocumentPayload(['document' => $document, 'meta' => ['module' => 'sales', 'controller' => $controller, 'templateid' => null]]);
        $pdf = (new ReflectionMethod(DEEC_Pdf::class, 'initPdf'))->invoke($this, $document['title'], $document, []);
        $this->renderPayload($pdf, $payload);
        return $pdf->Output('', 'S');
    }
    protected function resolveDocumentPayloadData(string $module, string $controller, array $document, array $options = []): array
    {
        return ['document' => $document, 'settings' => ['showTotals' => true, 'showPrices' => true]];
    }
}

$results = [];
foreach (['de_DE', 'en_US'] as $uiLocale) {
    $ui = new DEEC_Translate($uiLocale); $ui->loadModule('sales');
    Zend_Registry::set('DEEC_Translate', $ui);
    Zend_Registry::set('Zend_Locale', new Zend_Locale($uiLocale));
    Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', $uiLocale));
    foreach (['de_DE', 'en_US'] as $documentLocale) foreach (['quote', 'salesorder', 'invoice'] as $type) {
        $expected = new DEEC_Translate($documentLocale); $expected->loadModule('sales');
        $document = ['id' => 123, 'clientid' => 7, 'contactid' => 12, 'language' => $documentLocale, 'title' => 'Synthetic document',
            $type . 'id' => 123, $type . 'date' => '2026-11-19', 'billingname1' => 'Synthetic recipient',
            'subtotal' => '100.00 EUR', 'total' => '119.50 EUR', 'taxes' => '19.50 EUR', 'taxgroups' => ['19.5' => '19.50 EUR']];
        $generator = new TranslationAcceptancePdf();
        $bytes = $generator->renderDocument($document, $type);
        $pipes = [];
        $process = proc_open(['pdftotext', '-', '-'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $bytes); fclose($pipes[0]);
        $text = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        $expectedRate = Zend_Locale_Format::toNumber(19.5, ['precision' => 2, 'locale' => $documentLocale]) . '%';
        $checks = ['valid_pdf_and_text' => str_starts_with($bytes, '%PDF-') && $exitCode === 0,
            'document_labels' => str_contains($text, $expected->t('DOCUMENTS_' . strtoupper($type) . '_ID')) && str_contains($text, $expected->t('DOCUMENTS_SUBTOTAL')),
            'document_number_format' => str_contains($text, $expectedRate),
            'ui_context_preserved' => Zend_Registry::get('DEEC_Translate') === $ui && (string)Zend_Registry::get('Zend_Locale') === $uiLocale,
            'no_unresolved_keys' => !preg_match('/\bDOCUMENTS_[A-Z_]+\b/', $text),
            'stored_amounts_preserved' => str_contains($text, '119.50 EUR')];
        $results[] = ['ui_locale' => $uiLocale, 'document_locale' => $documentLocale, 'type' => $type,
            'checks' => $checks, 'expected_tax_rate' => $expectedRate, 'text' => $text, 'issues' => $generator->getTranslator()->getIssues(), 'stderr' => $stderr];
    }
}
$counts = ['passed' => 0, 'failed' => 0];
foreach ($results as $result) foreach ($result['checks'] as $ok) $counts[$ok ? 'passed' : 'failed']++;
echo json_encode(['scope' => 'Real compact-header/totals TCPDF renderer; synthetic payload, no DB/template loading and no mail dispatch', 'archive_fixture' => $archiveResult, 'results' => $results, 'counts' => $counts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($counts['failed'] ? 1 : 0);
