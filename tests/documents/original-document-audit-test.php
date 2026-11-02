<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 2);
$work = sys_get_temp_dir() . '/dewawi-original-audit-' . bin2hex(random_bytes(8));
mkdir($work, 0700);

function auditTestRemove(string $path): void
{
	if (!is_dir($path) || is_link($path)) { unlink($path); return; }
	foreach (new FilesystemIterator($path) as $entry) auditTestRemove($entry->getPathname());
	rmdir($path);
}

function auditTestFile(string $base, string $relative, string $contents): string
{
	$path = $base . '/' . $relative;
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
	file_put_contents($path, $contents);
	return $path;
}

function auditTestCheck(bool $condition, string $message): void
{
	if (!$condition) throw new RuntimeException($message);
}

try {
	$header = ['module', 'controller', 'id', 'clientid', 'contactid', 'document_number', 'document_date', 'filename', 'state', 'completed', 'completeddate', 'deleted', 'focus_from_may_2026', 'attachment_id', 'attachment_filename', 'attachment_filesize', 'attachment_location', 'attachment_deleted'];
	$rows = [];
	$pdf = "%PDF-1.4\nOriginal audit fixture\n%%EOF\n";
	foreach ([
		[1, '7301', 'invoice-1.pdf', 11, 1],
		[2, '7302', 'invoice-2.pdf', 12, 1],
		[3, '7303', 'invoice-3.pdf', 13, 1],
		[4, '7304', 'invoice-4.pdf', null, 1],
		[5, '7305', '../invalid.pdf', null, 1],
	] as [$id, $number, $filename, $attachmentId, $focus]) {
		$attachmentLocation = $attachmentId ? $work . '/files/attachments/sales/invoice/7/0/7/' . (string)$id . '/0/' . (string)$id : 'NULL';
		$rows[] = ['sales', 'invoice', $id, 7, 80560, $number, '2026-05-0' . $id, $filename, 105, 1, '2026-05-01 12:00:00', 0, $focus, $attachmentId ?? 'NULL', $attachmentId ? $filename : 'NULL', $attachmentId ? strlen($pdf) : 'NULL', $attachmentLocation, $attachmentId ? 0 : 'NULL'];
	}
	$tsv = $work . '/documents.tsv';
	$stream = fopen($tsv, 'wb');
	fputcsv($stream, $header, "\t");
	foreach ($rows as $row) fputcsv($stream, $row, "\t");
	fclose($stream);

	$contact = 'files/contacts/7/0/7/8/0/80560/';
	$attachment = 'files/attachments/sales/invoice/7/0/7/';
	$original1 = auditTestFile($work, $contact . 'invoice-1.pdf', $pdf);
	auditTestFile($work, $attachment . '1/0/1/invoice-1.pdf', $pdf);
	auditTestFile($work, $attachment . '2/0/2/invoice-2.pdf', $pdf);
	auditTestFile($work, $contact . 'invoice-3.pdf', $pdf);
	auditTestFile($work, $attachment . '3/0/3/invoice-3.pdf', "%PDF-1.4\nDifferent fixture\n%%EOF\n");
	auditTestFile($work, $contact . 'invoice-4.pdf', '');
	auditTestFile($work, $contact . 'unassigned.pdf', $pdf);
	$before = [hash_file('sha256', $original1), filemtime($original1)];

	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/documents/original-document-audit.php')
		. ' --input=' . escapeshellarg($tsv) . ' --base=' . escapeshellarg($work);
	$output = shell_exec($command);
	auditTestCheck(is_string($output) && $output !== '', 'Audit produced no report');
	$report = fopen('php://memory', 'w+');
	fwrite($report, $output);
	rewind($report);
	$columns = fgetcsv($report, 0, ';');
	$classifications = [];
	while (($values = fgetcsv($report, 0, ';')) !== false) {
		$row = array_combine($columns, $values);
		$classifications[$row['id'] ?: $row['original_path']] = $row['classification'];
	}
	fclose($report);
	auditTestCheck($classifications['1'] === 'ok', 'Identical original copies were not accepted');
	auditTestCheck($classifications['2'] === 'original_missing_attachment_recoverable', 'Recoverable missing original was not distinguished');
	auditTestCheck($classifications['3'] === 'copies_differ', 'Divergent copies were not detected');
	auditTestCheck($classifications['4'] === 'invalid_or_unreadable_file', 'Empty original was not detected');
	auditTestCheck($classifications['5'] === 'database_filename_invalid', 'Unsafe database filename was not isolated');
	auditTestCheck($classifications[$contact . 'unassigned.pdf'] === 'unassigned_contact_pdf', 'Unassigned contact PDF was not reported');
	auditTestCheck([hash_file('sha256', $original1), filemtime($original1)] === $before, 'Read-only audit changed an original file');
	echo "PASS: original document operator audit classifications are read-only\n";
} finally {
	auditTestRemove($work);
}
