<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
require_once $root . '/library/DEEC/Export/Gobd.php';

class GobdAuditExport extends DEEC_Export_Gobd
{
	public function __construct() {}
	public function profile(): array { return $this->getProfile(); }
	public function readme(string $path): void { $this->clientId = 7; $this->writeReadme($path, '2026-05-01', '2026-12-31'); }
}

function gobdCheck(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
}

$export = new GobdAuditExport();
$profile = $export->profile();
foreach (['quotes', 'salesorders', 'invoices', 'creditnotes'] as $dataset) gobdCheck(isset($profile[$dataset]), 'Missing existing fiscal dataset: ' . $dataset);
foreach (['deliveryorders', 'reminders', 'quoterequests', 'purchaseorders'] as $dataset) gobdCheck(!isset($profile[$dataset]), 'Audit assumption changed; review the original package proposal: ' . $dataset);
gobdCheck(in_array('filename', $profile['invoices']['columns'], true), 'Invoice CSV no longer exposes its stored filename reference');
foreach ($profile as $config) {
	gobdCheck(isset($config['table'], $config['columns']), 'Unexpected non-tabular GoBD dataset');
	gobdCheck(!array_intersect(['files', 'manifest', 'checksums'], array_keys($config)), 'Audit assumption changed; review new binary export support');
}

$readme = tempnam(sys_get_temp_dir(), 'dewawi-gobd-audit-');
try {
	$export->readme($readme);
	$content = file_get_contents($readme);
	gobdCheck(!str_contains($content, 'SHA-256') && !str_contains($content, 'Original PDF'), 'Audit assumption changed; review new integrity documentation');
} finally {
	unlink($readme);
}

echo "PASS: GoBD export gap verified; CSV metadata has no original files, manifest or checksums\n";
