<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/document-review.php';
umask(0077);
$work = sys_get_temp_dir() . '/dewawi-document-review-' . bin2hex(random_bytes(8));
mkdir($work, 0700);
$base = $work . '/source';
mkdir($base, 0700);
$archive = new DEEC_DocumentArchive($base);
$checks = 0;

function reviewTestCheck(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}
function reviewTestReject(callable $callback, string $message): void
{
	try { $callback(); }
	catch (RuntimeException | InvalidArgumentException $expected) { reviewTestCheck(true, $message); return; }
	throw new RuntimeException($message);
}
function reviewTestRemove(string $path): void
{
	if (is_link($path) || !is_dir($path)) { unlink($path); return; }
	foreach (new FilesystemIterator($path) as $entry) reviewTestRemove($entry->getPathname());
	rmdir($path);
}
function reviewTestPdf(string $label): string { return "%PDF-1.4\n" . $label . "\n%%EOF\n"; }
function reviewTestWrite(string $path, string $bytes): void
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
	file_put_contents($path, $bytes);
}
function reviewTestManifest(array $run, string $output, int $index = 0): array { return json_decode(file_get_contents($output . '/' . $run['documents'][$index]['manifest']), true, 512, JSON_THROW_ON_ERROR); }

class ReviewMutatingArchive extends DEEC_DocumentArchive
{
	private int $calls = 0;
	public function inspect(string $path, ?string $format = null): array
	{
		if (++$this->calls === 2) file_put_contents($path, reviewTestPdf('changed during copy'));
		return parent::inspect($path, $format);
	}
}

try {
	$identity = ['module' => 'sales', 'controller' => 'invoice', 'id' => 17, 'clientid' => 100, 'document_number' => '20190017'];
	$plans = [
		$identity + ['format' => 'pdf', 'status' => 'blocked', 'reason' => 'historical_versions_differ', 'files' => ['canonical' => ['path' => '/untrusted/report/path']]],
		array_replace($identity, ['controller' => 'quote', 'id' => 18, 'document_number' => '20190018']) + ['format' => 'pdf', 'status' => 'blocked', 'reason' => 'original_missing'],
		array_replace($identity, ['id' => 19, 'document_number' => '20210019']) + ['format' => 'pdf', 'status' => 'blocked', 'reason' => 'original_missing'],
	];
	$json = $work . '/report.jsonl';
	$tsv = $work . '/audit.tsv';
	$reportBytes = '';
	foreach ($plans as $plan) $reportBytes .= json_encode(['kind' => 'document', 'plan' => $plan]) . "\n";
	$reportBytes .= json_encode(['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 100, 'contacts_clientid' => 100, 'documents' => 3, 'unassigned_scan' => 'complete']]) . "\n";
	file_put_contents($json, $reportBytes);
	file_put_contents($tsv, "sales/invoice\t17\t20190017\t2019-05-03\nsales/quote\t18\t20190018\t2020-04-02\nsales/invoice\t17\t20190017\t2019-05-03\n");
	$inputs = reviewInputs($json, $tsv, 'both', 100);
	reviewTestCheck($inputs['counts'] === ['audit' => 2, 'blocked_invoices_in_report' => 2, 'selected_unique' => 3], 'Union did not deduplicate document selections');
	$unnumbered = array_replace($plans[1], ['id' => 99, 'document_number' => '', 'status' => 'ready', 'reason' => '']);
	file_put_contents($work . '/unnumbered.jsonl', json_encode(['kind' => 'document', 'plan' => $unnumbered]) . "\n" . str_replace('"documents":3', '"documents":4', $reportBytes));
	reviewTestCheck(count(reviewInputs($work . '/unnumbered.jsonl', $tsv, 'both', 100)['documents']) === 3, 'Unselected unnumbered document broke full report selection');
	reviewTestCheck(count(reviewInputs($json, $tsv, 'audit', 100)['documents']) === 2, 'Audit selection changed');
	reviewTestCheck(count(reviewInputs($json, null, 'blocked-invoices', 100)['documents']) === 2, 'Blocked invoice selection changed');
	foreach ([str_replace('"clientid":100', '"clientid":101', $reportBytes), substr($reportBytes, 0, strrpos(rtrim($reportBytes), "\n") + 1), $reportBytes . '{}', str_replace('"documents":3', '"documents":4', $reportBytes), str_replace('"unassigned_scan":"complete"', '"unassigned_scan":"skipped_for_filtered_run"', $reportBytes)] as $bad) {
		file_put_contents($work . '/bad.jsonl', $bad);
		reviewTestReject(static fn() => reviewInputs($work . '/bad.jsonl', $tsv, 'both', 100), 'Unsafe or incomplete report accepted');
	}
	foreach (["type\tid\tdocument_number\tdocument_date\n", "sales/invoice\t17\tother-number\t2019-01-01\n", "sales/invoice\t17\t20190017\t2022-01-01\n", "sales/invoice\t17\t20190017\t2019-02-30\n", "sales/../../invoice\t17\tx\t2019-01-01\n", "sales/invoice\t17\tx\t2019-01-01\textra\n"] as $bad) {
		file_put_contents($work . '/bad.tsv', $bad);
		reviewTestReject(static fn() => reviewInputs($json, $work . '/bad.tsv', 'both', 100), 'Invalid headerless TSV accepted');
	}
	symlink($json, $work . '/report-link');
	reviewTestReject(static fn() => reviewInputs($work . '/report-link', $tsv, 'both', 100), 'Symlink report accepted');
	$documents = [];
	$before = [];
	foreach ($inputs['documents'] as $selected) {
		$document = array_replace($selected, ['contactid' => 500, 'contact_record_id' => 800, 'contact_match_count' => 1, 'filename' => $selected['controller'] . '-' . $selected['id'] . '.pdf', 'document_date' => $selected['reported_date'] ?? '2021-02-03']);
		$paths = $archive->paths($document);
		$sources = [];
		foreach ($paths as $name => $path) $sources[] = ['source' => $name, 'path' => $path ?: null, 'status' => null, 'evidence' => []];
		if ($selected['id'] === 17) {
			foreach (['attachment', 'current', 'legacy'] as $name) reviewTestWrite($paths[$name], reviewTestPdf($name === 'legacy' ? 'different original' : 'same original'));
		} elseif ($selected['id'] === 18) {
			reviewTestWrite($paths['current'], reviewTestPdf('older quote'));
			reviewTestWrite($paths['legacy'], reviewTestPdf('newer quote'));
			touch($paths['current'], 1500000000);
			touch($paths['legacy'], 1600000000);
		}
		foreach ($paths as $path) if ($path !== '' && is_file($path)) $before[$path] = [hash_file('sha256', $path), filemtime($path)];
		$documents[reviewKey($selected)] = ['document' => $document, 'sources' => $sources, 'current_plan' => $archive->plan($document)];
	}
	$loader = static fn(array $selected): ?array => $documents[reviewKey($selected)] ?? null;
	$output = $work . '/review/client-100';
	$run = reviewRun($inputs, $base, $output, 100, 100, $loader);
	$invoice = reviewTestManifest($run, $output);
	$missing = reviewTestManifest($run, $output, 1);
	$quote = reviewTestManifest($run, $output, 2);
	reviewTestCheck(count($invoice['candidates']) === 2 && count(array_filter($invoice['sources'], static fn($row) => $row['candidate_path'] !== null)) === 3, 'Different PDFs or deduplicated provenance lost');
	reviewTestCheck($invoice['suggestion'] === null && $invoice['invoice_manual_selection'] && $invoice['manual_selection_required'], 'Invoice original was automatically suggested or chosen');
	reviewTestCheck($missing['candidates'] === [] && $missing['current_reason'] === 'original_missing', 'Missing original did not receive an empty review manifest');
	reviewTestCheck($quote['suggestion']['sha256'] === hash('sha256', reviewTestPdf('newer quote')) && !$quote['suggestion']['authoritative'], 'Non-invoice timestamp suggestion changed');
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) reviewTestCheck(($entry->getPerms() & 0777) === ($entry->isDir() ? 0700 : 0600), 'Review access permissions are too broad');
	$invoiceDir = dirname($output . '/' . $run['documents'][0]['manifest']);
	$originalManifest = file_get_contents($invoiceDir . '/manifest.json');
	reviewTestWrite($invoiceDir . '/external/datev.pdf', reviewTestPdf('manual DATEV copy'));
	$selection = json_decode(file_get_contents($invoiceDir . '/selection.json'), true);
	$selection['reason'] = 'operator work must survive';
	file_put_contents($invoiceDir . '/selection.json', reviewJson($selection));
	$run2 = reviewRun($inputs, $base, $output, 100, 100, $loader);
	reviewTestCheck(file_get_contents($invoiceDir . '/manifest.json') === $originalManifest, 'Rerun overwrote the initial manifest');
	reviewTestCheck(file_get_contents($invoiceDir . '/external/datev.pdf') === reviewTestPdf('manual DATEV copy'), 'Rerun changed external files');
	reviewTestCheck(json_decode(file_get_contents($invoiceDir . '/selection.json'), true)['reason'] === $selection['reason'], 'Rerun overwrote operator selection');
	reviewTestCheck(count(glob($invoiceDir . '/candidates/*.pdf')) === 2 && str_contains($run2['documents'][0]['manifest'], 'manifest.'), 'Rerun duplicated candidates or failed to journal observations');
	$auditRun = reviewRun(reviewInputs($json, $tsv, 'audit', 100), $base, $output, 100, 100, $loader);
	reviewTestCheck(count($auditRun['documents']) === 2, 'Changing selection mode damaged existing reviews');
	foreach ($before as $path => $state) reviewTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Collection modified a historical source');
	foreach (['by-type.csv', 'by-year.csv', 'by-reason.csv', 'overview.csv', 'run.json'] as $file) reviewTestCheck(is_file($run['run_directory'] . '/' . $file), 'Overview missing');
	reviewTestCheck(str_contains(reviewCsv([['=danger', '+danger', '@danger', '-danger']]), "'=danger"), 'Spreadsheet formula not neutralized');

	$one = $inputs;
	$one['documents'] = [$inputs['documents'][0]];
	$context = $documents[reviewKey($one['documents'][0])];
	$paths = $archive->paths($context['document']);
	unlink($paths['legacy']);
	symlink($paths['current'], $paths['legacy']);
	$badRun = reviewRun($one, $base, $work . '/symlink-review', 100, 100, static fn() => $context);
	$badManifest = reviewTestManifest($badRun, $work . '/symlink-review');
	reviewTestCheck(in_array('symlink', array_column($badManifest['sources'], 'status'), true) && count($badManifest['candidates']) === 1, 'Symlink source was copied');
	$context['sources'][2]['status'] = 'contact_mapping_ambiguous';
	$context['sources'][3]['status'] = 'contact_mapping_ambiguous';
	$context['current_plan']['reason'] = 'contact_mapping_ambiguous';
	$ambiguous = reviewRun($one, $base, $work . '/ambiguous-review', 100, 100, static fn() => $context);
	$ambiguousManifest = reviewTestManifest($ambiguous, $work . '/ambiguous-review');
	reviewTestCheck(count($ambiguousManifest['candidates']) === 1 && count(array_filter($ambiguousManifest['sources'], static fn($row) => $row['status'] === 'contact_mapping_ambiguous')) === 2, 'Ambiguous contact sources were attributed');
	$foreign = array_replace_recursive($context, ['document' => ['clientid' => 101]]);
	reviewTestReject(static fn() => reviewRun($one, $base, $work . '/foreign', 100, 100, static fn() => $foreign), 'Foreign tenant document accepted');
	$otherTenant = $one;
	$otherTenant['documents'][0]['clientid'] = 101;
	reviewTestReject(static fn() => reviewRun($otherTenant, $base, $output, 101, 101, $loader), 'Review root was reused by another tenant');
	$changedIdentity = array_replace_recursive($context, ['document' => ['document_number' => null]]);
	$identityRun = reviewRun($one, $base, $work . '/changed-identity', 100, 100, static fn() => $changedIdentity);
	reviewTestCheck(reviewTestManifest($identityRun, $work . '/changed-identity')['current_reason'] === 'document_identity_changed', 'Changed document identity was silently collected');
	$manipulated = $context;
	$manipulated['sources'] = [['source' => 'current', 'path' => $base . '/../report.jsonl', 'status' => null, 'evidence' => []]];
	$unsafeRun = reviewRun($one, $base, $work . '/path-review', 100, 100, static fn() => $manipulated);
	reviewTestCheck(reviewTestManifest($unsafeRun, $work . '/path-review')['candidates'] === [] && $unsafeRun['source_issues'] > 0, 'Path traversal was copied');
	reviewTestReject(static fn() => reviewRun($one, $base, $base . '/public-review', 100, 100, $loader), 'Webroot output accepted');
	symlink($work . '/review', $work . '/linked-output');
	reviewTestReject(static fn() => reviewRun($one, $base, $work . '/linked-output/client-100', 100, 100, $loader), 'Symlink output ancestor accepted');
	$lock = fopen($output . '/.collector.lock', 'r+b');
	flock($lock, LOCK_EX);
	reviewTestReject(static fn() => reviewRun($one, $base, $output, 100, 100, $loader), 'Concurrent collector accepted');
	flock($lock, LOCK_UN);
	fclose($lock);
	$old = $archive->inspect($paths['current'], 'pdf');
	file_put_contents($paths['current'], reviewTestPdf('changed after inspection'));
	reviewTestReject(static fn() => reviewCopy($archive, new DEEC_DocumentArchive($output), $old, $invoiceDir . '/candidates/' . $old['sha256'] . '.pdf'), 'Changed source was accepted');
	$old = $archive->inspect($paths['current'], 'pdf');
	$raceTarget = $invoiceDir . '/candidates/' . $old['sha256'] . '.pdf';
	reviewTestReject(static fn() => reviewCopy(new ReviewMutatingArchive($base), new DEEC_DocumentArchive($output), $old, $raceTarget), 'Source change during copy was accepted');
	reviewTestCheck(!file_exists($raceTarget), 'Changing source published an unverified candidate');
	$candidate = $invoiceDir . '/' . $invoice['candidates'][0]['path'];
	file_put_contents($candidate, reviewTestPdf('manual candidate modification'));
	$corrupt = reviewRun($one, $base, $output, 100, 100, $loader);
	reviewTestCheck($corrupt['errors'] > 0 && file_get_contents($candidate) === reviewTestPdf('manual candidate modification'), 'Tampered review candidate was overwritten');
	echo 'PASS: ' . $checks . " document review checks; isolated synthetic PDFs, no database connection\n";
} finally { reviewTestRemove($work); }
