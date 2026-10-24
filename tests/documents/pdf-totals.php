<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/documents/pdf-totals.php (real TCPDF rendering, temporary files only).
ob_start();
require __DIR__ . '/pdf.php';
ob_end_clean();
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));

class TotalsTestPdf extends TCPDF
{
    public array $texts = [];
    public function MultiCell(...$args)
    {
        $this->texts[] = $args[2];
        return parent::MultiCell(...$args);
    }
}

$renderer = new DEEC_Pdf();
$method = new ReflectionMethod(DEEC_Pdf::class, 'totalBox');
$method->setAccessible(true);
$heightMethod = new ReflectionMethod(DEEC_Pdf::class, 'getTotalBoxHeight');
$heightMethod->setAccessible(true);
foreach ([
    ['taxgroups' => [19 => '19,00 €', 7 => '7,00 €', '7.7' => '7,70 €'], 'taxrate' => null, 'taxes' => '33,70 €', 'expected' => ['DOCUMENTS_TAXES (19,00%)', '19,00 €', 'DOCUMENTS_TAXES (7,00%)', '7,00 €', 'DOCUMENTS_TAXES (7,70%)', '7,70 €']],
    ['taxgroups' => [0 => '0,00 €'], 'taxrate' => '0,00', 'taxes' => '0,00 €', 'expected' => ['DOCUMENTS_TAXES (0,00%)', '0,00 €']],
    ['taxgroups' => [], 'taxrate' => '19,00', 'taxes' => '19,00 €', 'expected' => ['DOCUMENTS_TAXES (19,00%)', '19,00 €']],
    ['taxgroups' => [], 'taxrate' => null, 'taxes' => '33,70 €', 'expected' => ['DOCUMENTS_TAXES', '33,70 €']],
] as $values) {
    $pdf = new TotalsTestPdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $document = $values + ['subtotal' => '300,00 €', 'total' => '333,70 €', 'prepayment_raw' => 100, 'prepayment' => '100,00 €', 'balance' => '233,70 €'];
    $method->invoke($renderer, $pdf, $document);
    foreach (array_merge($values['expected'], ['300,00 €', '333,70 €', '- 100,00 €', '233,70 €']) as $text) {
        check(in_array($text, $pdf->texts, true), 'PDF omitted total box text: ' . $text);
    }
    check($heightMethod->invoke($renderer, $document) >= 30 + max(0, count($values['taxgroups']) - 1) * 6, 'Total box did not reserve room for tax groups');
    check(str_starts_with($pdf->Output('', 'S'), '%PDF-'), 'Rendering totals did not produce a PDF');
}
echo 'PASS: ' . $checks . " PDF checks including mixed-rate total boxes, exemption and balance\n";
