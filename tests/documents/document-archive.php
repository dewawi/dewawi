<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 2);
$work = sys_get_temp_dir() . '/dewawi-document-archive-' . bin2hex(random_bytes(8));
mkdir($work, 0700);
define('BASE_PATH', $work);
require_once $root . '/library/DEEC/Emailattachment.php';
require_once $root . '/library/DEEC/DocumentArchive.php';

$checks = 0;
function archiveCheck(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}
function archiveRemove(string $path): void
{
	if (is_link($path) || !is_dir($path)) { if (file_exists($path) || is_link($path)) unlink($path); return; }
	foreach (new FilesystemIterator($path) as $entry) archiveRemove($entry->getPathname());
	rmdir($path);
}
function archivePdf(string $label): string
{
	return "%PDF-1.4\n" . $label . "\n%%EOF\n";
}
function archiveWrite(string $path, string $bytes): void
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
	file_put_contents($path, $bytes);
}
register_shutdown_function(static function () use ($work): void { archiveRemove($work); });

$archive = new DEEC_DocumentArchive($work);
$base = [
	'module' => 'sales', 'controller' => 'invoice', 'id' => 112143, 'clientid' => 100,
	'contactid' => 58101, 'contact_record_id' => 80560, 'document_number' => '112143', 'filename' => 'Rechnung-112143.pdf',
];
$paths = $archive->paths($base);
archiveCheck(str_ends_with($paths['canonical'], '/files/attachments/sales/invoice/1/0/100/1/1/112143/Rechnung-112143.pdf'), 'Canonical path is not document based');
archiveCheck(str_ends_with($paths['current'], '/files/contacts/1/0/100/5/8/58101/Rechnung-112143.pdf'), 'Customer-number path changed');
archiveCheck(str_ends_with($paths['legacy'], '/files/contacts/1/0/100/8/0/80560/Rechnung-112143.pdf'), 'Contact-record path changed');
archiveCheck($archive->plan($base)['reason'] === 'original_missing', 'Missing original was not blocked');

$bytes = archivePdf('invoice 112143 original');
archiveWrite($paths['legacy'], $bytes);
$plan = $archive->plan($base);
archiveCheck($plan['status'] === 'ready' && $plan['action'] === 'copy_and_register' && $plan['source'] === 'legacy', 'Legacy original did not produce a migration plan');
$stored = $archive->copy($plan);
archiveCheck($stored['status'] === 'ok' && $stored['size'] === strlen($bytes) && file_get_contents($paths['canonical']) === $bytes, 'Canonical copy failed integrity verification');
archiveCheck(file_get_contents($paths['legacy']) === $bytes, 'Migration changed its historical source');
$plan = $archive->plan($base);
archiveCheck($plan['status'] === 'ready' && $plan['action'] === 'register_metadata', 'Restart did not retain the copied archive');
$metadata = [[
	'id' => 1, 'documentid' => 112143, 'module' => 'sales', 'controller' => 'invoice', 'clientid' => 100,
	'filename' => $base['filename'], 'location' => dirname($paths['canonical']), 'filesize' => strlen($bytes), 'deleted' => 0,
]];
archiveCheck($archive->plan($base, $metadata)['status'] === 'complete', 'Verified metadata was not idempotent');
$foreign = [array_replace($metadata[0], ['clientid' => 101])];
archiveCheck($archive->plan($base, $foreign)['reason'] === 'metadata_differs', 'Foreign-tenant metadata was accepted');
$wrongLocation = [array_replace($metadata[0], ['location' => $work])];
archiveCheck($archive->plan($base, $wrongLocation)['reason'] === 'metadata_differs', 'Wrong archive assignment was accepted');
archiveCheck($archive->plan($base, [$metadata[0], array_replace($metadata[0], ['id' => 2])])['reason'] === 'metadata_ambiguous', 'Duplicate metadata was accepted');
archiveCheck($archive->plan(array_replace($base, ['contact_match_count' => 2]))['reason'] === 'contact_mapping_ambiguous', 'Ambiguous contact mapping was accepted');

unlink($paths['canonical']);
archiveWrite($paths['current'], archivePdf('different current version'));
archiveCheck($archive->plan($base)['reason'] === 'historical_versions_differ', 'Different historical versions were accepted');
unlink($paths['current']);
file_put_contents($paths['current'], '%PDF-1.4 truncated');
archiveCheck($archive->plan($base)['reason'] === 'invalid_historical_source', 'Damaged PDF was accepted');
unlink($paths['current']);
unlink($paths['legacy']);
mkdir($paths['canonical']);
archiveCheck($archive->plan($base)['reason'] === 'canonical_collision_or_invalid', 'Canonical directory collision was accepted');
rmdir($paths['canonical']);

$outside = $work . '/outside.pdf';
archiveWrite($outside, $bytes);
symlink($outside, $paths['legacy']);
archiveCheck($archive->plan($base)['reason'] === 'invalid_historical_source', 'Symlink source was accepted');
unlink($paths['legacy']);
archiveWrite($paths['legacy'], $bytes);
$race = $archive->plan($base);
archiveWrite($paths['canonical'], archivePdf('concurrent writer'));
try {
	$archive->copy($race);
	throw new RuntimeException('Concurrent target was overwritten');
} catch (RuntimeException $expected) {
	archiveCheck($expected->getMessage() === 'Archive target collision', 'Unexpected target race result');
}
archiveCheck(file_get_contents($paths['canonical']) === archivePdf('concurrent writer'), 'Concurrent target bytes changed');
unlink($paths['canonical']);
$changed = $archive->plan($base);
file_put_contents($paths['legacy'], archivePdf('source changed after planning'));
try {
	$archive->copy($changed);
	throw new RuntimeException('Changed source was copied');
} catch (RuntimeException $expected) {
	archiveCheck($expected->getMessage() === 'Archive source changed', 'Source change was not detected');
}
archiveCheck(!is_file($paths['canonical']), 'Changed source left a canonical file');

$sameId = array_replace($base, ['id' => 112144, 'contactid' => 58101, 'contact_record_id' => 58101, 'filename' => 'same-id.pdf']);
$samePaths = $archive->paths($sameId);
archiveWrite($samePaths['current'], $bytes);
archiveCheck($samePaths['current'] === $samePaths['legacy'] && $archive->plan($sameId)['status'] === 'ready', 'Equal contact ID and customer number produced a conflict');

$linked = array_replace($base, ['id' => 112145, 'filename' => 'linked-target.pdf']);
$linkedPaths = $archive->paths($linked);
$linkedDirectory = dirname($linkedPaths['canonical']);
if (!is_dir(dirname($linkedDirectory))) mkdir(dirname($linkedDirectory), 0775, true);
$outsideDirectory = $work . '/outside-directory';
mkdir($outsideDirectory);
symlink($outsideDirectory, $linkedDirectory);
archiveCheck($archive->plan($linked)['reason'] === 'invalid_historical_source' || $archive->plan($linked)['reason'] === 'canonical_collision_or_invalid', 'Symlink archive directory was accepted');
unlink($linkedDirectory);

$manual = $work . '/files/contacts/1/0/100/8/0/80560/manual.txt';
archiveWrite($manual, 'manual contact file');
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) {
	foreach ($controllers as $controller) {
		$fixture = array_replace($base, ['module' => $module, 'controller' => $controller, 'id' => ++$base['id'], 'filename' => $controller . '.pdf']);
		$typePaths = $archive->paths($fixture);
		archiveCheck(str_contains($typePaths['canonical'], '/files/attachments/' . $module . '/' . $controller . '/1/0/100/'), 'Document type uses a non-canonical root');
	}
}
archiveCheck(file_get_contents($manual) === 'manual contact file', 'Manual contact file was changed');

echo 'PASS: ' . $checks . " document archive checks; all eight types covered, sources retained, conflicts blocked\n";
