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
	public function csv(string $path, array $rows, array $columns): void { $this->writeCsv($path, $rows, $columns); }
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

$csv = tempnam(sys_get_temp_dir(), 'dewawi-gobd-csv-');
try {
	$values = ["Synthetic \"quoted\" value", "Synthetic trailing \\", "Synthetic \\\"quoted\" and newline\nvalue", ''];
	$rows = array_map(static fn(string $value) => ['key' => 'synthetic', 'value' => $value], $values);
	$export->csv($csv, $rows, ['key', 'value']);
	$handle = fopen($csv, 'rb'); fgetcsv($handle, 0, ';', '"', '');
	foreach ($values as $value) gobdCheck(fgetcsv($handle, 0, ';', '"', '') === ['synthetic', $value], 'CSV quoting changed a stored value');
	fclose($handle);
} finally { unlink($csv); }

foreach ([['2019-01-01', '2021-12-31'], ['2034-02-28', '2034-02-28'], ['2024-02-29', '2024-03-01']] as [$from, $to]) DEEC_Export_Gobd::validatePeriod($from, $to, true);
foreach ([['2019-02-29', '2020-01-01'], ['2026-11-13', '2026-11-12'], ['2026-1-01', '2026-12-31'], [null, null]] as [$from, $to]) {
	try { DEEC_Export_Gobd::validatePeriod($from, $to, true); throw new RuntimeException('Invalid GoBD dates were accepted'); }
	catch (InvalidArgumentException $expected) {}
}
echo "PASS: legacy GoBD profile, default README and free-period validation preserved\n";
