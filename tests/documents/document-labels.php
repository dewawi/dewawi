<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void { if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path; });
require_once $root . '/library/Tcpdf/tcpdf.php';
class LabelFixturePdf extends DEEC_Pdf { public function mark(TCPDF $pdf, string $key): void { $this->markCopy($pdf, $key); } }
$checks = 0;
foreach (['de_DE' => ['MUSTER', 'KOPIE'], 'en_US' => ['SAMPLE', 'COPY']] as $locale => $marks) {
	Zend_Registry::set('Zend_Locale', $locale);
	foreach (['DOCUMENTS_DRAFT_MARK', 'DOCUMENTS_RECONSTRUCTED_MARK'] as $index => $key) {
		$pdf = new TCPDF(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetCompression(false); $pdf->SetMargins(15, 30, 15);
		foreach ([1, 2, 3] as $page) { $pdf->AddPage(); $pdf->SetFont('dejavusans', '', 10); $pdf->Write(5, 'Synthetic body ' . $page); }
		(new LabelFixturePdf())->mark($pdf, $key); $bytes = $pdf->Output('', 'S');
		preg_match_all('/([0-9.]+) ([0-9.]+) ([0-9.]+) (?:rg|RG)\b/', $bytes, $colors, PREG_SET_ORDER);
		foreach ($colors as $color) { if ($color[1] !== $color[2] || $color[2] !== $color[3]) throw new RuntimeException('Non-neutral PDF label color'); $checks++; }
		$path = tempnam(sys_get_temp_dir(), 'dewawi-label-'); file_put_contents($path, $bytes);
		try {
			$process = proc_open(['pdftotext', '-layout', $path, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			$text = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
			if (proc_close($process) !== 0) throw new RuntimeException($error);
			$pages = array_values(array_filter(explode("\f", $text), static fn(string $page): bool => trim($page) !== ''));
			if (count($pages) !== 3) throw new RuntimeException('Label changed PDF pagination');
			foreach ($pages as $page => $text) {
				if (!preg_match('/^\s*' . $marks[$index] . '\s*\n/', $text) || !str_contains($text, 'Synthetic body ' . ($page + 1))) throw new RuntimeException('Exact label or body missing'); $checks++;
			}
		} finally { unlink($path); }
	}
}
$fresh = file_get_contents($root . '/install/config/structure.sql'); $migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
foreach (['documentrevision', 'invoicecorrection'] as $table) {
	$pattern = '/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?ENGINE=InnoDB DEFAULT CHARSET=utf8;/s';
	preg_match($pattern, $fresh, $a); preg_match($pattern, $migration, $b);
	if (!$a || $a !== $b) throw new RuntimeException('Document change install and migration schema differ: ' . $table); $checks++;
}
if (!str_contains($fresh, '`invoicecorrectionid` int(11) DEFAULT NULL') || !str_contains($migration, 'ADD `invoicecorrectionid` int(11) DEFAULT NULL AFTER `emailattachmentid`')
	|| !str_contains($fresh, 'KEY `invoicecorrectionid` (`invoicecorrectionid`)') || !str_contains($migration, 'ADD KEY `invoicecorrectionid` (`invoicecorrectionid`)')) throw new RuntimeException('Correction email reference migration differs'); $checks++;
echo 'PASS: ' . $checks . " PDF label and document change schema checks; both locales, three pages, neutral colors and preserved content\n";
