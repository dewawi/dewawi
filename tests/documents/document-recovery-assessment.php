<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$work = sys_get_temp_dir() . '/dewawi-assessment-' . bin2hex(random_bytes(8));
mkdir($work, 0700); mkdir($work . '/source', 0700);
define('BASE_PATH', $work . '/source');
require dirname(__DIR__, 2) . '/tools/document-recovery-assessment.php';
require_once dirname(__DIR__, 2) . '/library/Tcpdf/tcpdf.php';
$checks = 0;
class AssessmentFixturePdf extends TCPDF { public function __construct() { parent::__construct(); $this->tcpdflink = false; } }
function assessmentCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function assessmentReject(callable $callback, string $message): void
{
	try { $callback(); } catch (RuntimeException | InvalidArgumentException | JsonException $expected) { assessmentCheck(true, $message); return; }
	throw new RuntimeException($message);
}
function assessmentRemove(string $path): void
{
	if (is_link($path) || !is_dir($path)) { unlink($path); return; }
	foreach (new FilesystemIterator($path) as $file) assessmentRemove($file->getPathname());
	rmdir($path);
}
function assessmentWrite(string $path, string $bytes): void
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
	file_put_contents($path, $bytes); chmod($path, 0600);
}
function assessmentPdf(string $text): string
{
	$pdf = new AssessmentFixturePdf(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetFont('dejavusans', '', 10); $pdf->AddPage(); $pdf->Write(5, $text);
	return $pdf->Output('', 'S');
}
function assessmentPlan(int $id, array $bytes = []): array
{
	$document = ['module' => 'sales', 'controller' => 'invoice', 'id' => $id, 'clientid' => 7, 'document_number' => 'SYN-' . $id, 'contactid' => 12, 'contact_record_id' => 1, 'filename' => 'synthetic-' . $id . '.pdf', 'format' => 'pdf', 'status' => 'blocked', 'reason' => 'historical_versions_differ'];
	$archive = new DEEC_DocumentArchive(BASE_PATH); $files = [];
	foreach ($archive->paths($document) as $label => $path) {
		if (isset($bytes[$label])) assessmentWrite($path, $bytes[$label]);
		$files[$label] = $archive->inspect($path, 'pdf');
	}
	return $document + ['files' => $files];
}
function assessmentFixtureInventory(string $path, array $plans, array $extra = []): void
{
	$counts = ['blocked' => 0, 'ready' => 0, 'complete' => 0]; $text = '';
	foreach ($plans as $plan) { $counts[$plan['status']]++; $text .= json_encode(['kind' => 'document', 'plan' => $plan]) . "\n"; }
	$text .= json_encode(['kind' => 'summary', 'summary' => array_replace(['mode' => 'dry-run', 'clientid' => 7, 'contacts_clientid' => 7, 'documents' => count($plans), 'unassigned_scan' => 'complete'] + $counts, $extra)]) . "\n";
	assessmentWrite($path, $text);
}
function assessmentCli(array $options, int $expected = 0, string $memory = '128M'): array
{
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'memory_limit=' . $memory, dirname(__DIR__, 2) . '/tools/document-recovery-assessment.php'];
	foreach ($options as $key => $value) $command[] = '--' . $key . '=' . $value;
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	assessmentCheck(proc_close($process) === $expected, 'Unexpected CLI exit: ' . $out . $error);
	assessmentCheck($error === '' || $expected === 2, 'CLI emitted a warning: ' . $error);
	return $expected === 2 ? ['error' => $error] : json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
function assessmentResult(array $plan, ?callable $load = null, string $extractor = '/usr/bin/pdftotext', int $limit = 200): array
{
	return assessmentDocument($plan, BASE_PATH, $load, $extractor, '2025-01-01', $limit);
}
function assessmentCandidate(array $result, string $path): array
{
	return array_values(array_filter($result['candidates'], static fn(array $row): bool => $row['original_path'] === $path))[0];
}

try {
	$text = "Invoice\nInvoice ID: SYN-1\nInvoice date: 2026-01-02\nIssuer: Synthetic issuer\nRecipient: Synthetic recipient\nSubtotal: 100.00\nTaxes: 19.00\nTotal: 119.00\n";
	$a = assessmentPdf($text); $b = assessmentPdf(str_replace('119.00', '120.00', $text)); $c = assessmentPdf(str_replace('2026-01-02', '2024-02-03', $text));
	$cases = [
		[['attachment' => $a, 'current' => $b, 'legacy' => $a], 2, 'attachment=1;current=2;legacy=1'],
		[['attachment' => $a, 'current' => $a, 'legacy' => $b], 2, 'attachment=1;current=1;legacy=2'],
		[['attachment' => $b, 'current' => $a, 'legacy' => $a], 2, 'attachment=1;current=2;legacy=2'],
		[['attachment' => $a, 'current' => $b, 'legacy' => $c], 3, 'attachment=1;current=2;legacy=3'],
		[['attachment' => $a, 'current' => $a, 'legacy' => $a], 1, 'attachment=1;current=1;legacy=1'],
		[['attachment' => $a, 'legacy' => $a], 1, 'attachment=1;current=missing_or_unverified;legacy=1'],
		[['current' => $a, 'legacy' => $b], 2, 'attachment=missing_or_unverified;current=1;legacy=2'],
	];
	$plans = [];
	foreach ($cases as $index => [$bytes, $groups, $pattern]) {
		$plan = assessmentPlan($index + 1, $bytes); $plans[] = $plan; $result = assessmentResult($plan);
		assessmentCheck(count($result['versions']) === $groups && $result['sha_pattern'] === $pattern, 'SHA partition or missing-source grouping failed');
		assessmentCheck($result['classification'] === ($groups > 1 ? 'conflicting_versions_needing_review' : 'consistent_candidates_needing_origin_confirmation'), 'Byte grouping incorrectly selected a version');
		assessmentCheck(!$result['original_confirmed'] && !$result['approval_allowed'], 'Assessment approved an original');
		foreach ($result['versions'] as $version) assessmentCheck($version['extraction']['status'] === 'ok', 'Real PDF extraction failed');
	}
	// Exhaust the remaining presence/equality partitions; private production subpatterns are not available here.
	foreach ([[], ['attachment' => $a], ['current' => $a], ['legacy' => $a], ['attachment' => $a, 'current' => $a], ['attachment' => $a, 'current' => $b], ['attachment' => $a, 'legacy' => $b], ['current' => $a, 'legacy' => $a]] as $index => $bytes) {
		$result = assessmentResult(assessmentPlan(101 + $index, $bytes));
		assessmentCheck(count($result['versions']) === count(array_unique($bytes)), 'Remaining missing-source SHA partition failed');
		assessmentCheck(!$result['approval_allowed'], 'Missing-source SHA partition authorized an original');
	}
	$basis = ['document_date' => '2026-01-02', 'issuer' => 'Synthetic issuer', 'recipient' => 'Synthetic recipient', 'subtotal' => '100.0000', 'taxes' => 19, 'total' => '119'];
	$plan = array_replace($plans[0], $basis); $result = assessmentResult($plan); $facts = $result['versions'][0]['facts'];
	foreach (['number', 'date', 'type', 'issuer', 'recipient'] as $field) assessmentCheck($facts['comparisons'][$field]['status'] === 'text_matches', 'Failed labeled content comparison: ' . $field);
	foreach (['subtotal', 'taxes', 'total'] as $field) assessmentCheck($facts['comparisons'][$field]['status'] === 'text_matches_at_two_decimals', 'Failed stored amount comparison: ' . $field);
	assessmentCheck($result['priority']['rank'] === 0 && $result['versions'][1]['difference']['added_count'] > 0, 'Recent invoice priority or content difference missing');
	assessmentCheck(!$facts['comparison_is_authenticity_proof'], 'Text equality became authenticity proof');
	$equalText = substr($a, 0, strrpos($a, '%%EOF')) . "% synthetic different bytes\n%%EOF\n";
	$sameText = assessmentResult(assessmentPlan(20, ['attachment' => $a, 'current' => $equalText]));
	assessmentCheck(count($sameText['versions']) === 2 && $sameText['versions'][1]['difference']['normalized_text_equal'], 'Equal text incorrectly merged different bytes');
	$ambiguousFacts = assessmentFacts($text . "Invoice ID: SYN-10\nInvoice date: 01/02/2026\nInvoice date: 2025-01-01\nTotal: 1,234\n", $plan, $basis);
	assessmentCheck($ambiguousFacts['comparisons']['number']['status'] === 'unresolved' && $ambiguousFacts['comparisons']['date']['status'] === 'unresolved' && $ambiguousFacts['comparisons']['total']['status'] === 'unresolved', 'Ambiguous values were resolved');
	assessmentCheck($ambiguousFacts['unparsed_date_values'] === ['01/02/2026'], 'Ambiguous slash date was inferred');
	assessmentCheck(assessmentFacts(str_replace('SYN-1', 'SYN-10', $text), $plan, $basis)['comparisons']['number']['status'] === 'text_differs', 'Document number substring was treated as exact identity');
	assessmentCheck(assessmentDate('2026-02-30') === null && assessmentDate('03.02.2026') === '2026-02-03', 'Invalid or German date handling failed');
	assessmentCheck(assessmentFacts($text . 'MUSTER – NICHT AUSGESTELLT', $plan, $basis)['non_original_markers'], 'Non-original marker omitted');
	foreach (['MUSTER', 'SAMPLE', 'KOPIE', 'COPY'] as $mark) assessmentCheck(assessmentFacts($mark . "\n" . $text, $plan, $basis)['non_original_markers'], 'Short non-original marker omitted');
	assessmentCheck(assessmentFacts("Total: 1.2.3,45\n", $plan, $basis)['comparisons']['total']['parsed_decimal'] === null, 'Malformed separators were guessed');
	assessmentCheck(assessmentFacts("Total: 1.234,56\n", $plan, ['total' => '1234.56'])['comparisons']['total']['status'] === 'text_matches_at_two_decimals', 'German grouped amount failed');
	assessmentCheck(assessmentFacts("Taxes: 19%\n", $plan, ['taxes' => 19])['comparisons']['taxes']['status'] === 'unresolved', 'A percentage was compared as a monetary amount');
	assessmentCheck(assessmentResult($plans[0], null, '/not-installed')['versions'][0]['extraction']['status'] === 'unavailable', 'Missing extractor hidden');
	$blank = new AssessmentFixturePdf(); $blank->setPrintHeader(false); $blank->setPrintFooter(false); $blank->AddPage();
	$blankResult = assessmentResult(assessmentPlan(21, ['attachment' => $blank->Output('', 'S')]));
	assessmentCheck($blankResult['versions'][0]['extraction']['status'] === 'empty_text', 'Empty extraction was fabricated: ' . json_encode($blankResult['versions'][0]['extraction']));
	assessmentCheck(assessmentResult(assessmentPlan(22))['classification'] === 'no_candidate_found', 'Missing files misclassified');
	foreach (['not a PDF', "%PDF-1.4\ninvalid internal PDF structure\n%%EOF\n"] as $index => $invalid) assessmentCheck(assessmentResult(assessmentPlan(23 + $index, ['attachment' => $invalid]))['classification'] === 'invalid_or_unreadable_candidate', 'Invalid PDF accepted');
	$changed = assessmentPlan(25, ['attachment' => $a]); assessmentWrite($changed['files']['attachment']['path'], $b);
	$changedResult = assessmentResult($changed); $entry = assessmentCandidate($changedResult, $changed['files']['attachment']['path']);
	assessmentCheck(in_array('recorded_sha256_differs', $entry['issues'], true) && $changedResult['classification'] === 'invalid_or_unreadable_candidate', 'Changed SHA not reported');
	$changed['files']['attachment']['sha256'] = 'not-a-sha'; $changed['files']['attachment']['size'] = 1;
	assessmentCheck(in_array('recorded_size_differs', assessmentCandidate(assessmentResult($changed), $changed['files']['attachment']['path'])['issues'], true), 'Changed metadata size not reported');
	$foreign = assessmentPlan(26); $foreignPath = (new DEEC_DocumentArchive(BASE_PATH))->paths(array_replace($foreign, ['clientid' => 8]))['attachment']; assessmentWrite($foreignPath, $a);
	$foreign['files']['attachment']['path'] = $foreignPath;
	assessmentCheck(assessmentCandidate(assessmentResult($foreign), $foreignPath)['observed'] === null, 'Foreign tenant PDF was read');
	$unsafe = assessmentPlan(27); $unsafe['files']['attachment']['path'] = BASE_PATH . '/../canary.pdf';
	assessmentCheck(assessmentCandidate(assessmentResult($unsafe), $unsafe['files']['attachment']['path'])['observed'] === null, 'Traversal candidate was read');
	$link = assessmentPlan(28); mkdir(dirname($link['files']['attachment']['path']), 0700, true); symlink($foreignPath, $link['files']['attachment']['path']);
	assessmentCheck(assessmentCandidate(assessmentResult($link), $link['files']['attachment']['path'])['observed'] === null, 'Symlink candidate was read');
	$additional = assessmentPlan(29); $extra = dirname($additional['files']['attachment']['path']) . '/other-version.pdf'; assessmentWrite($extra, $a);
	assessmentCheck(assessmentResult($additional)['classification'] === 'possible_additional_candidate', 'Scoped unassigned candidate not identified');
	$unscoped = assessmentPlan(30); assessmentWrite(dirname($unscoped['files']['current']['path']) . '/unrelated-name.pdf', $a);
	assessmentCheck(assessmentResult($unscoped)['classification'] === 'no_candidate_found', 'Unassigned contact folder files were guessed by filename');
	$ambiguous = array_replace($plans[0], ['reason' => 'contact_mapping_ambiguous']);
	assessmentCheck(assessmentResult($ambiguous)['classification'] === 'contact_mapping_ambiguous', 'Ambiguous contact mapping lost');
	$context = ['document' => $plans[0] + ['contact_match_count' => 2], 'sources' => []];
	$mapped = assessmentResult($plans[0], static fn(array $id): array => $context);
	assessmentCheck($mapped['classification'] === 'contact_mapping_ambiguous' && assessmentCandidate($mapped, $plans[0]['files']['current']['path'])['observed'] === null, 'Ambiguous contact candidate was read');
	$context['document']['contact_match_count'] = 1;
	$context['sources'] = [['source' => 'current', 'path' => $plans[0]['files']['current']['path'], 'status' => 'contact_filename_ambiguous', 'evidence' => []]];
	assessmentCheck(assessmentCandidate(assessmentResult($plans[0], static fn(array $id): array => $context), $plans[0]['files']['current']['path'])['observed'] === null, 'Inventory association bypassed DB filename ambiguity');
	$context['sources'][0]['status'] = null; $context['sources'][0]['evidence']['document_filename'] = $plans[0]['filename'];
	$renamedPlan = array_replace($plans[0], ['filename' => 'outdated-inventory-name.pdf']);
	assessmentCheck(assessmentCandidate(assessmentResult($renamedPlan, static fn(array $id): array => $context), $plans[0]['files']['current']['path'])['observed']['status'] === 'ok', 'Explicit current document filename evidence was ignored');
	assessmentCheck(assessmentResult($plans[0], static fn(array $id) => null)['versions'] === [], 'Unavailable document authorization fell back to offline reads');
	$context['document']['document_number'] = 'OTHER';
	assessmentCheck(assessmentResult($plans[0], static fn(array $id): array => $context)['versions'] === [], 'Changed document identity was read');
	$invalidContext = array_replace($plans[0], ['controller' => 'invalid', 'reason' => 'invalid_document_context']);
	assessmentCheck(assessmentResult($invalidContext)['classification'] === 'invalid_or_unreadable_candidate', 'Invalid document context crashed or read files');
	$mailPlan = assessmentPlan(31); $mailPath = BASE_PATH . '/files/attachments/emailmessage/7/0/7/0/35/1.pdf'; assessmentWrite($mailPath, $a);
	$mailSource = ['source' => 'emailmessageattachment', 'path' => substr($mailPath, strlen(BASE_PATH) + 1), 'status' => null, 'evidence' => ['emailmessageid' => 35, 'ordering' => 1, 'sha256' => hash('sha256', $a), 'filesize' => strlen($a), 'messagesent' => '2026-01-02', 'deliverystatus' => 'accepted']];
	$mailContext = ['document' => $mailPlan + ['contact_match_count' => 1], 'sources' => [$mailSource]];
	foreach (['current', 'legacy'] as $label) $mailContext['sources'][] = ['source' => $label, 'path' => $mailPlan['files'][$label]['path'], 'status' => null, 'evidence' => []];
	assessmentCheck(assessmentResult($mailPlan, static fn(array $id): array => $mailContext)['classification'] === 'consistent_candidates_needing_origin_confirmation', 'Email references not correlated');
	$mailContext['sources'][0]['status'] = 'message_document_reference_missing';
	assessmentCheck(assessmentResult($mailPlan, static fn(array $id): array => $mailContext)['classification'] === 'possible_additional_candidate', 'Missing email reference falsely confirmed');
	$mailContext['sources'][0]['status'] = 'metadata_path_rejected';
	assessmentCheck(assessmentCandidate(assessmentResult($mailPlan, static fn(array $id): array => $mailContext), $mailPath)['observed'] === null, 'Invalid email reference path read');
	$multi = assessmentPlan(32, ['attachment' => $a]); assessmentWrite(dirname($multi['files']['attachment']['path']) . '/second.pdf', $b);
	assessmentCheck(count(assessmentResult($multi)['versions']) === 2, 'Multiple attachment versions not found');
	assessmentCheck(!assessmentResult($multi, null, '/usr/bin/pdftotext', 1)['analysis_complete'], 'Candidate cap was silently complete');
	foreach (['limit' => 'echo str_repeat("x", 1048577);', 'encoding' => 'echo chr(255);', 'failure' => 'exit(5);'] as $name => $code) {
		$binary = $work . '/' . $name; assessmentWrite($binary, "#!/usr/bin/php\n<?php\n" . $code); chmod($binary, 0700);
		assessmentCheck(assessmentExtract($plans[0]['files']['attachment']['path'], $binary)['status'] === ['limit' => 'output_limit', 'encoding' => 'invalid_text_encoding', 'failure' => 'failed'][$name], 'Extractor failure was hidden');
	}
	$mutated = assessmentPlan(33, ['attachment' => $a]); $binary = $work . '/mutate';
	assessmentWrite($binary, "#!/usr/bin/php\n<?php\nfile_put_contents(" . var_export($mutated['files']['attachment']['path'], true) . ", 'changed fixture'); echo 'Invoice ID: SYN-33';"); chmod($binary, 0700);
	$mutatedResult = assessmentResult($mutated, null, $binary);
	assessmentCheck($mutatedResult['versions'][0]['extraction']['status'] === 'file_changed_during_extraction' && $mutatedResult['versions'][0]['facts'] === null, 'Changed bytes retained extracted facts');
	assessmentCheck(assessmentExtract($plans[0]['files']['attachment']['path'], '/usr/bin/pdftotext', str_repeat('0', 64))['status'] === 'file_changed_during_extraction', 'Extraction opened a different SHA');

	$inventory = $work . '/inventory.jsonl'; assessmentFixtureInventory($inventory, $plans);
	$input = assessmentInventory($inventory, 7); assessmentCheck($input['counts']['blocked'] === 7, 'Blocked inventory count changed');
	$validBytes = file_get_contents($inventory);
	$invalidInputs = [substr($validBytes, 0, strrpos(rtrim($validBytes), "\n") + 1), $validBytes . "{}\n", str_replace('"documents":7', '"documents":8', $validBytes), str_replace('"clientid":7', '"clientid":8', $validBytes), str_replace('"unassigned_scan":"complete"', '"unassigned_scan":"incomplete"', $validBytes), "{broken JSON\n", json_encode(['kind' => 'document', 'plan' => $plans[0]]) . "\n" . $validBytes];
	foreach ($invalidInputs as $bytes) { assessmentWrite($work . '/bad.jsonl', $bytes); assessmentReject(static fn() => assessmentInventory($work . '/bad.jsonl', 7), 'Incomplete or inconsistent inventory accepted'); }
	assessmentWrite($work . '/null-summary.jsonl', "{\"kind\":\"summary\",\"summary\":null}\n" . $validBytes);
	assessmentReject(static fn() => assessmentInventory($work . '/null-summary.jsonl', 7), 'Null summary before otherwise valid inventory accepted');
	chmod($inventory, 0644); assessmentReject(static fn() => assessmentInventory($inventory, 7), 'Public inventory accepted'); chmod($inventory, 0600);
	symlink($inventory, $work . '/link.jsonl'); assessmentReject(static fn() => assessmentInventory($work . '/link.jsonl', 7), 'Linked inventory accepted');
	link($inventory, $work . '/hardlink.jsonl'); assessmentReject(static fn() => assessmentInventory($inventory, 7), 'Hardlinked inventory accepted'); unlink($work . '/hardlink.jsonl');
	mkdir($work . '/public', 0755); chmod($work . '/public', 0755);
	assessmentReject(static fn() => assessmentRun($input, BASE_PATH, $work . '/public/output', 7, '/usr/bin/pdftotext', '2025-01-01', 200), 'Public output parent accepted');
	mkdir(BASE_PATH . '/private', 0700);
	assessmentReject(static fn() => assessmentRun($input, BASE_PATH, BASE_PATH . '/private/output', 7, '/usr/bin/pdftotext', '2025-01-01', 200), 'Webroot output accepted');
	$state = [];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile() && !$file->isLink()) $state[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
	$options = ['inventory' => $inventory, 'base' => BASE_PATH, 'client' => 7, 'output' => $work . '/assessment', 'recent-since' => '2025-01-01'];
	$summary = assessmentCli($options); assessmentCheck($summary['complete'] && $summary['documents'] === 7 && $summary['originals_confirmed'] === 0, 'CLI summary invalid');
	foreach (['started.json', 'documents.jsonl', 'review.csv', 'summary.json'] as $file) assessmentCheck((fileperms($options['output'] . '/' . $file) & 0777) === 0600, 'Output not private');
	assessmentCheck((fileperms($options['output']) & 0777) === 0700 && count(glob($options['output'] . '/*')) === 4, 'Output contains copies or directory permissions invalid');
	$details = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($options['output'] . '/documents.jsonl', FILE_IGNORE_NEW_LINES));
	assessmentCheck(count($details) === 7 && $details[0]['document']['document_number'] === 'SYN-1', 'Per-document details missing identity');
	$csv = fopen($options['output'] . '/review.csv', 'rb'); fgetcsv($csv, 0, ';'); $first = fgetcsv($csv, 0, ';'); fclose($csv);
	assessmentCheck($first[0] === '0' && $first[5] === '1' && $first[9] === '1', 'Priority CSV lost ordering or JSONL pointer');
	assessmentCheck(str_contains(reviewCsv([['=FORMULA', '+FORMULA', '@FORMULA']]), "'=FORMULA"), 'CSV formula escaping lost');
	$workers = [];
	foreach (['concurrent-a', 'concurrent-b'] as $name) {
		$command = [PHP_BINARY, '-d', 'error_reporting=8191', dirname(__DIR__, 2) . '/tools/document-recovery-assessment.php'];
		foreach (array_replace($options, ['output' => $work . '/' . $name]) as $key => $value) $command[] = '--' . $key . '=' . $value;
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); $workers[] = [$process, $pipes];
	}
	foreach ($workers as [$process, $pipes]) {
		$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
		assessmentCheck(proc_close($process) === 0 && $error === '' && json_decode($out, true)['documents'] === 7, 'Concurrent read-only assessment failed');
	}
	assessmentCli($options, 2);
	assessmentCli(array_replace($options, ['inventory' => $work . '/bad.jsonl', 'output' => $work . '/bad-output']), 2);
	assessmentCheck(!file_exists($work . '/bad-output'), 'Invalid input created output');
	assessmentFixtureInventory($work . '/capped.jsonl', [$multi]); assessmentCli(array_replace($options, ['inventory' => $work . '/capped.jsonl', 'output' => $work . '/capped', 'max-candidates' => 1]), 3);
	foreach ($state as $path => $before) assessmentCheck([hash_file('sha256', $path), filemtime($path)] === $before, 'Assessment modified source PDF');
	assessmentCheck(file_get_contents($inventory) === $validBytes, 'Assessment modified inventory');
	$large = fopen($work . '/large.jsonl', 'xb');
	for ($id = 10000; $id < 60000; $id++) fwrite($large, json_encode(['kind' => 'document', 'plan' => array_replace($plans[0], ['id' => $id, 'status' => 'complete', 'reason' => '', 'files' => []])]) . "\n");
	fwrite($large, json_encode(['kind' => 'document', 'plan' => assessmentPlan(90)]) . "\n");
	fwrite($large, json_encode(['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 7, 'contacts_clientid' => 7, 'documents' => 50001, 'complete' => 50000, 'blocked' => 1, 'ready' => 0, 'unassigned_scan' => 'complete']]) . "\n"); fclose($large); chmod($work . '/large.jsonl', 0600);
	assessmentCheck(assessmentCli(array_replace($options, ['inventory' => $work . '/large.jsonl', 'output' => $work . '/large-output']), 0, '32M')['documents'] === 1, 'Large inventory was not streamed within memory limit');
	echo 'PASS: ' . $checks . " recovery assessment checks; synthetic PDFs, all 15 SHA presence/equality patterns, paths, extraction, private output and 50001-record streaming\n";
} finally { assessmentRemove($work); }
