<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Read-only filesystem classification for the TSV produced by original-document-production-audit.sql.
$options = getopt('', ['input:', 'base:']);
$input = isset($options['input']) ? realpath((string)$options['input']) : false;
$base = isset($options['base']) ? realpath((string)$options['base']) : false;
if (!$input || !is_file($input) || !$base || !is_dir($base)) {
	fwrite(STDERR, "Usage: php tests/documents/original-document-audit.php --input=/secure/documents.tsv --base=/path/to/dewawi\n");
	exit(2);
}

function auditShard(int $id): string
{
	$value = (string)$id;
	return $value[0] . '/' . ($value[1] ?? '0') . '/' . $value;
}

function auditPdf(string $path, string $base): array
{
	if (is_link($path)) return ['symlink', null, null];
	if (!is_file($path)) return ['missing', null, null];
	$real = realpath($path);
	if (!$real || $real !== $path || !str_starts_with($real, rtrim($base, '/') . '/')) return ['symlink_or_outside', null, null];
	$size = filesize($path);
	if ($size === 0) return ['empty', 0, hash_file('sha256', $path)];
	$handle = @fopen($path, 'rb');
	if (!$handle) return ['unreadable', $size, null];
	try {
		$header = fread($handle, 5);
		fseek($handle, max(0, $size - 2048));
		$tail = stream_get_contents($handle);
	} finally {
		fclose($handle);
	}
	$status = $size >= 12 && $header === '%PDF-' && str_ends_with(rtrim((string)$tail), '%%EOF') ? 'ok' : 'invalid_pdf';
	return [$status, $size, hash_file('sha256', $path)];
}

function auditFilename(string $filename): bool
{
	return $filename !== '' && strlen($filename) <= 255 && !in_array($filename, ['.', '..'], true)
		&& preg_match('//u', $filename) === 1 && !preg_match('/[\x00-\x1f\x7f\\\\\/]/u', $filename);
}

function auditValue(array $row, string $column): string
{
	return isset($row[$column]) && $row[$column] !== 'NULL' ? (string)$row[$column] : '';
}

$handle = fopen($input, 'rb');
$columns = fgetcsv($handle, 0, "\t");
$required = ['module', 'controller', 'id', 'clientid', 'contactid', 'contact_record_id', 'contact_number', 'document_number', 'document_date', 'filename', 'state', 'completed', 'completeddate', 'deleted', 'focus_from_may_2026', 'attachment_id', 'attachment_filename', 'attachment_filesize', 'attachment_location', 'attachment_deleted'];
if (!$columns || array_diff($required, $columns)) {
	fwrite(STDERR, "The TSV header does not match original-document-production-audit.sql.\n");
	exit(2);
}

$documents = [];
while (($values = fgetcsv($handle, 0, "\t")) !== false) {
	if (count($values) !== count($columns)) throw new RuntimeException('Malformed TSV row');
	$row = array_combine($columns, $values);
	$key = auditValue($row, 'module') . '/' . auditValue($row, 'controller') . '/' . auditValue($row, 'id');
	if (!isset($documents[$key])) $documents[$key] = ['row' => $row, 'attachments' => []];
	if (auditValue($row, 'attachment_id') !== '') $documents[$key]['attachments'][] = $row;
}
fclose($handle);

$outputColumns = [
	'module', 'controller', 'id', 'clientid', 'contactid', 'contact_record_id', 'contact_number', 'document_number', 'document_date', 'filename',
	'state', 'completed', 'completeddate', 'deleted', 'focus_from_may_2026',
	'current_path', 'current_status', 'current_size', 'current_sha256', 'legacy_path', 'legacy_status', 'legacy_size', 'legacy_sha256',
	'original_path', 'original_status',
	'original_size', 'original_sha256', 'attachment_path', 'attachment_metadata', 'attachment_status',
	'attachment_size', 'attachment_sha256', 'comparison', 'classification',
];
fputcsv(STDOUT, $outputColumns, ';');
$filenames = [];
$assignedFiles = [];
$results = [];
$directories = [];

foreach ($documents as $key => $entry) {
	$row = $entry['row'];
	$module = auditValue($row, 'module');
	$controller = auditValue($row, 'controller');
	$id = (int)auditValue($row, 'id');
	$clientId = (int)auditValue($row, 'clientid');
	$contactNumber = (int)auditValue($row, 'contact_number');
	$contactRecordId = (int)auditValue($row, 'contact_record_id');
	$filename = auditValue($row, 'filename');
	$filenameValid = auditFilename($filename);
	$currentRelative = 'files/contacts/' . auditShard($clientId) . '/' . auditShard($contactNumber) . '/' . $filename;
	$legacyRelative = 'files/contacts/' . auditShard($clientId) . '/' . auditShard($contactRecordId) . '/' . $filename;
	$attachmentRelative = 'files/attachments/' . $module . '/' . $controller . '/' . auditShard($clientId) . '/' . auditShard($id) . '/' . $filename;
	$attachmentPath = $base . '/' . $attachmentRelative;
	[$currentStatus, $currentSize, $currentHash] = $filenameValid ? auditPdf($base . '/' . $currentRelative, $base) : [$filename === '' ? 'filename_missing' : 'filename_invalid', null, null];
	[$legacyStatus, $legacySize, $legacyHash] = $filenameValid ? auditPdf($base . '/' . $legacyRelative, $base) : [$filename === '' ? 'filename_missing' : 'filename_invalid', null, null];
	if ($currentStatus === 'ok') [$originalRelative, $originalStatus, $originalSize, $originalHash] = [$currentRelative, $currentStatus, $currentSize, $currentHash];
	elseif ($legacyStatus === 'ok') [$originalRelative, $originalStatus, $originalSize, $originalHash] = [$legacyRelative, $legacyStatus, $legacySize, $legacyHash];
	elseif ($currentStatus !== 'missing') [$originalRelative, $originalStatus, $originalSize, $originalHash] = [$currentRelative, $currentStatus, $currentSize, $currentHash];
	else [$originalRelative, $originalStatus, $originalSize, $originalHash] = [$legacyRelative, $legacyStatus, $legacySize, $legacyHash];
	[$attachmentStatus, $attachmentSize, $attachmentHash] = $filenameValid ? auditPdf($attachmentPath, $base) : [$filename === '' ? 'filename_missing' : 'filename_invalid', null, null];
	$activeAttachments = array_values(array_filter($entry['attachments'], static fn(array $attachment): bool => auditValue($attachment, 'attachment_deleted') === '0'));
	$deletedAttachments = count($entry['attachments']) - count($activeAttachments);
	$metadata = count($activeAttachments) . '_active';
	if ($deletedAttachments) $metadata .= '+' . $deletedAttachments . '_deleted';
	if (!$entry['attachments']) $metadata = 'none';
	foreach ($activeAttachments as $attachment) {
		$location = rtrim(auditValue($attachment, 'attachment_location'), '/');
		if ($location !== '' && !str_ends_with($location, '/' . dirname($attachmentRelative))) $metadata .= '+wrong_location';
		$recordedSize = auditValue($attachment, 'attachment_filesize');
		if ($attachmentSize !== null && $recordedSize !== '' && (int)$recordedSize !== $attachmentSize) $metadata .= '+wrong_size';
	}
	$comparison = $originalStatus === 'ok' && $attachmentStatus === 'ok'
		? (hash_equals((string)$originalHash, (string)$attachmentHash) ? 'identical' : 'different') : 'not_comparable';
	if ($currentStatus === 'ok' && $legacyStatus === 'ok' && !hash_equals((string)$currentHash, (string)$legacyHash)) $classification = 'original_locations_differ';
	elseif ($filename === '') $classification = 'database_filename_missing';
	elseif (!$filenameValid) $classification = 'database_filename_invalid';
	elseif ($originalStatus === 'ok' && $attachmentStatus === 'ok' && $comparison === 'different') $classification = 'copies_differ';
	elseif ($originalStatus === 'ok' && $attachmentStatus === 'ok' && count($activeAttachments) === 0) $classification = 'attachment_unregistered';
	elseif ($originalStatus === 'ok' && $attachmentStatus === 'ok') $classification = count($activeAttachments) === 1 ? 'ok' : 'attachment_metadata_ambiguous';
	elseif ($originalStatus === 'missing' && $attachmentStatus === 'ok') $classification = 'original_missing_attachment_recoverable';
	elseif ($originalStatus === 'ok' && $attachmentStatus === 'missing') $classification = count($activeAttachments) ? 'attachment_file_missing' : 'attachment_missing';
	elseif ($originalStatus === 'missing' && $attachmentStatus === 'missing') $classification = 'both_files_missing';
	else $classification = 'invalid_or_unreadable_file';

	if ($filenameValid) {
		$filenameKey = $clientId . '/' . $contactNumber . '/' . $filename;
		$filenames[$filenameKey][] = $key;
		$assignedFiles[$currentRelative] = true;
		$assignedFiles[$legacyRelative] = true;
	}
	$directories[$base . '/files/contacts/' . auditShard($clientId) . '/' . auditShard($contactNumber)] = true;
	$directories[$base . '/files/contacts/' . auditShard($clientId) . '/' . auditShard($contactRecordId)] = true;
	$results[$key] = array_merge(array_map(static fn(string $column): string => auditValue($row, $column), array_slice($outputColumns, 0, 15)), [
		$currentRelative, $currentStatus, $currentSize, $currentHash, $legacyRelative, $legacyStatus, $legacySize, $legacyHash,
		$originalRelative, $originalStatus, $originalSize, $originalHash, $attachmentRelative, $metadata,
		$attachmentStatus, $attachmentSize, $attachmentHash, $comparison, $classification,
	]);
}

foreach ($filenames as $keys) {
	if (count($keys) < 2) continue;
	foreach ($keys as $key) $results[$key][array_search('classification', $outputColumns, true)] .= '+duplicate_contact_filename';
}
foreach ($results as $result) fputcsv(STDOUT, $result, ';');

foreach (array_keys($directories) as $directory) {
	if (!is_dir($directory)) continue;
	$realDirectory = realpath($directory);
	if (is_link($directory) || !$realDirectory || $realDirectory !== $directory || !str_starts_with($realDirectory, rtrim($base, '/') . '/')) {
		$result = array_fill(0, count($outputColumns), '');
		$result[array_search('module', $outputColumns, true)] = 'filesystem';
		$result[array_search('original_path', $outputColumns, true)] = substr($directory, strlen($base) + 1);
		$result[array_search('original_status', $outputColumns, true)] = 'symlink_or_outside';
		$result[array_search('classification', $outputColumns, true)] = 'unsafe_contact_directory';
		fputcsv(STDOUT, $result, ';');
		continue;
	}
	foreach (new DirectoryIterator($directory) as $file) {
		if (!$file->isFile() || strtolower($file->getExtension()) !== 'pdf') continue;
		$relative = substr($file->getPathname(), strlen($base) + 1);
		if (isset($assignedFiles[$relative])) continue;
		[$status, $size, $hash] = auditPdf($file->getPathname(), $base);
		$result = array_fill(0, count($outputColumns), '');
		$result[array_search('module', $outputColumns, true)] = 'filesystem';
		$result[array_search('original_path', $outputColumns, true)] = $relative;
		$result[array_search('original_status', $outputColumns, true)] = $status;
		$result[array_search('original_size', $outputColumns, true)] = $size;
		$result[array_search('original_sha256', $outputColumns, true)] = $hash;
		$result[array_search('classification', $outputColumns, true)] = 'unassigned_contact_pdf';
		fputcsv(STDOUT, $result, ';');
	}
}
