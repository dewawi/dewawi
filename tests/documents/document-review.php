<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/document-archive-migration.php';
require_once dirname(__DIR__, 2) . '/library/DEEC/Emailattachment.php';
require_once dirname(__DIR__, 2) . '/library/DEEC/DocumentArchive.php';

function reviewId($value): int
{
	$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	if (!$id) throw new RuntimeException('Invalid document or tenant ID');
	return $id;
}

function reviewIdentity(array $row, int $clientId): array
{
	if (reviewId($row['clientid'] ?? null) !== $clientId) throw new RuntimeException('Foreign tenant in input');
	$module = $row['module'] ?? '';
	$controller = $row['controller'] ?? '';
	if (!is_string($module) || !is_string($controller) || !DEEC_DocumentArchive::validDocumentType($module, $controller)) throw new RuntimeException('Invalid document type');
	$number = $row['document_number'] ?? '';
	if ((!is_string($number) && !is_int($number)) || ((string)$number !== '' && !DEEC_Emailattachment::validFilename(str_replace('/', '-', (string)$number)))) throw new RuntimeException('Invalid visible document number');
	return ['module' => $module, 'controller' => $controller, 'id' => reviewId($row['id'] ?? null), 'clientid' => $clientId, 'document_number' => (string)$number];
}

function reviewKey(array $row): string { return $row['module'] . '/' . $row['controller'] . '/' . $row['id']; }

function reviewSafePath(string $path): void
{
	if ($path === '' || $path[0] !== '/' || str_contains($path, '\\') || preg_match('~[\x00-\x1f\x7f]|/(?:\.{1,2})(?:/|$)|//~', $path)) throw new RuntimeException('Unsafe filesystem path');
	$current = '';
	foreach (explode('/', substr($path, 1)) as $part) {
		$current .= '/' . $part;
		clearstatcache(true, $current);
		if (is_link($current)) throw new RuntimeException('Symlink in filesystem path');
	}
}

function reviewInputs(string $jsonl, ?string $tsv, string $selection, int $clientId): array
{
	if (!in_array($selection, ['audit', 'blocked-invoices', 'both', 'all-blocked', 'all-unarchived'], true)) throw new RuntimeException('Invalid selection');
	$reports = [];
	$read = static function(string $path) use (&$reports): string {
		reviewSafePath($path);
		if (!is_file($path) || !is_readable($path)) throw new RuntimeException('Report is unavailable');
		$bytes = file_get_contents($path);
		$reports[] = ['path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
		return $bytes;
	};
	$plans = [];
	$summary = null;
	foreach (explode("\n", $read($jsonl)) as $line) {
		if (trim($line) === '') continue;
		if ($summary !== null) throw new RuntimeException('Records after JSONL summary');
		$row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
		if (($row['kind'] ?? '') === 'summary') { $summary = $row['summary']; continue; }
		if (($row['kind'] ?? '') !== 'document') {
			if (!in_array($row['kind'] ?? '', ['structured_original', 'unassigned_file', 'excluded_document'], true)) throw new RuntimeException('Unknown JSONL record');
			continue;
		}
		$plan = $row['plan'];
		$identity = reviewIdentity($plan, $clientId);
		$key = reviewKey($identity);
		if (isset($plans[$key]) || ($plan['format'] ?? '') !== 'pdf' || !in_array($plan['status'] ?? '', ['blocked', 'ready', 'complete'], true)) throw new RuntimeException('Invalid or duplicate JSONL document');
		if (!is_string($plan['reason'] ?? null) || ($plan['status'] === 'blocked' && $plan['reason'] === '')) throw new RuntimeException('Missing conflict reason');
		$plans[$key] = $identity + ['reported_reasons' => $plan['reason'] === '' ? [] : [$plan['reason']], 'reported_date' => null, 'selections' => [], 'reported_status' => $plan['status']];
	}
	if (!$summary || ($summary['mode'] ?? '') !== 'dry-run' || ($summary['unassigned_scan'] ?? '') !== 'complete'
		|| reviewId($summary['clientid'] ?? null) !== $clientId || ($summary['documents'] ?? -1) !== count($plans)) throw new RuntimeException('A complete tenant dry-run report is required');
	$documents = [];
	$blocked = 0;
	foreach ($plans as $key => $plan) {
		if (($selection === 'all-blocked' && $plan['reported_status'] === 'blocked') || ($selection === 'all-unarchived' && $plan['reported_status'] !== 'complete')) { $plan['selections'][] = $selection; $documents[$key] = $plan; }
		if ($plan['module'] !== 'sales' || $plan['controller'] !== 'invoice' || $plan['reported_status'] !== 'blocked') continue;
		$blocked++;
		if (in_array($selection, ['audit', 'all-blocked', 'all-unarchived'], true)) continue;
		$plan['selections'][] = 'blocked-invoices';
		$documents[$key] = $plan;
	}
	$audit = [];
	if (in_array($selection, ['audit', 'both'], true)) {
		if ($tsv === null) throw new RuntimeException('The audit selection requires the headerless TSV');
		foreach (explode("\n", $read($tsv)) as $line) {
			if ($line === '') continue;
			$values = explode("\t", rtrim($line, "\r"));
			if (count($values) !== 4) throw new RuntimeException('Audit TSV must have exactly four columns and no header');
			[$type, $id, $number, $date] = $values;
			if ($number === '' || $number === 'NULL') throw new RuntimeException('Audit document number is missing');
			$parts = explode('/', $type);
			if (count($parts) !== 2) throw new RuntimeException('Invalid TSV document type');
			$identity = reviewIdentity(['module' => $parts[0], 'controller' => $parts[1], 'id' => $id, 'clientid' => $clientId, 'document_number' => $number], $clientId);
			if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) throw new RuntimeException('Invalid audit date');
			$key = reviewKey($identity);
			$known = $plans[$key] ?? $identity + ['reported_reasons' => [], 'reported_date' => null, 'selections' => [], 'reported_status' => null];
			if ($known['document_number'] !== $number || (isset($documents[$key]) && $documents[$key]['document_number'] !== $number) || (isset($audit[$key]) && $audit[$key] !== $date)) throw new RuntimeException('Conflicting report identities');
			$audit[$key] = $date;
			$known = $documents[$key] ?? $known;
			$known['reported_date'] = $date;
			$known['reported_reasons'] = array_values(array_unique(array_merge($known['reported_reasons'], ['audit_not_archived'])));
			$known['selections'] = array_values(array_unique(array_merge($known['selections'], ['audit'])));
			$documents[$key] = $known;
		}
	}
	if (!$documents) throw new RuntimeException('No documents selected');
	ksort($documents);
	return ['documents' => array_values($documents), 'reports' => $reports, 'selection' => $selection, 'contacts_clientid' => reviewId($summary['contacts_clientid'] ?? null),
		'counts' => ['audit' => count($audit), 'blocked_invoices_in_report' => $blocked, 'selected_unique' => count($documents)]];
}

function reviewQuery(mysqli $db, string $sql, string $types, array $parameters): array
{
	$stmt = $db->prepare($sql);
	try {
		$stmt->bind_param($types, ...$parameters);
		$stmt->execute();
		return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	} finally { $stmt->close(); }
}

function reviewContactUnique(mysqli $db, array $document, string $filename, int $pathContactId, int $contactsClient): bool
{
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) {
		foreach ($controllers as $controller) {
			$rows = reviewQuery($db, "SELECT d.id FROM `$controller` d WHERE d.clientid = ? AND (d.filename = ? OR EXISTS (SELECT 1 FROM emailattachment a WHERE a.clientid = d.clientid AND a.documentid = d.id AND a.module = ? AND a.controller = ? AND a.filename = ?)) AND (d.contactid = ? OR d.contactid IN (SELECT contactid FROM contact WHERE clientid = ? AND id = ? AND deleted = 0))", 'issssiii', [$document['clientid'], $filename, $module, $controller, $filename, $pathContactId, $contactsClient, $pathContactId]);
			foreach ($rows as $row) if ($module !== $document['module'] || $controller !== $document['controller'] || (int)$row['id'] !== (int)$document['id']) return false;
		}
	}
	return true;
}

function reviewLoadDocument(mysqli $db, DEEC_DocumentArchive $archive, array $selected, int $contactsClient): ?array
{
	$table = $selected['controller'];
	if (!DEEC_DocumentArchive::validDocumentType($selected['module'], $table)) throw new RuntimeException('Invalid document type');
	$rows = reviewQuery($db, "SELECT id, clientid, contactid, filename, CAST(`{$table}id` AS CHAR) AS document_number, `{$table}date` AS document_date, state, completed, cancelled FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0", 'ii', [$selected['id'], $selected['clientid']]);
	if (count($rows) !== 1) return null;
	$document = $rows[0] + ['module' => $selected['module'], 'controller' => $table, 'format' => 'pdf'];
	$contacts = reviewQuery($db, 'SELECT id FROM contact WHERE clientid = ? AND contactid = ? AND deleted = 0 ORDER BY id', 'ii', [$contactsClient, $document['contactid']]);
	$document['contact_match_count'] = count($contacts);
	$document['contact_record_id'] = count($contacts) === 1 ? (int)$contacts[0]['id'] : 0;
	$document['contact_record_ids'] = array_map('intval', array_column($contacts, 'id'));
	$document['contacts_clientid'] = $contactsClient;
	$sources = [];
	$addPaths = static function(string $filename, array $evidence) use (&$sources, $document, $archive, $db, $contactsClient): void {
		try { $paths = $archive->paths(array_replace($document, ['filename' => $filename])); }
		catch (InvalidArgumentException $exception) { $sources[] = ['source' => 'document_paths', 'path' => null, 'status' => 'invalid_document_context', 'evidence' => $evidence]; return; }
		foreach (['attachment', 'current', 'legacy'] as $name) {
			$status = null;
			if ($name !== 'attachment') {
				if ($document['contact_match_count'] !== 1) $status = $document['contact_match_count'] > 1 ? 'contact_mapping_ambiguous' : 'contact_mapping_missing';
				elseif (!reviewContactUnique($db, $document, $filename, $name === 'current' ? (int)$document['contactid'] : $document['contact_record_id'], $contactsClient)) $status = 'contact_filename_ambiguous';
			}
			$sources[] = ['source' => $name, 'path' => $paths[$name] ?: null, 'status' => $status, 'evidence' => $evidence];
		}
	};
	$canonical = $archive->path((int)$document['clientid'], $document['module'], $table, (int)$document['id']);
	$sources[] = ['source' => 'canonical', 'path' => $canonical, 'status' => null, 'evidence' => []];
	$addPaths((string)$document['filename'], ['document_filename' => $document['filename']]);
	$legacy = reviewQuery($db, 'SELECT id, filename, location, filesize, deleted FROM emailattachment WHERE clientid = ? AND documentid = ? AND module = ? AND controller = ? ORDER BY id', 'iiss', [$document['clientid'], $document['id'], $document['module'], $table]);
	foreach ($legacy as $row) {
		if (!DEEC_Emailattachment::validFilename((string)$row['filename']) || strtolower(pathinfo($row['filename'], PATHINFO_EXTENSION)) !== 'pdf') continue;
		try { $path = $archive->paths(array_replace($document, ['filename' => $row['filename']]))['attachment']; }
		catch (InvalidArgumentException $exception) { continue; }
		if (rtrim((string)$row['location'], '/') !== dirname($path)) {
			$sources[] = ['source' => 'emailattachment', 'path' => (string)$row['location'] . '/' . $row['filename'], 'status' => 'metadata_path_rejected', 'evidence' => $row];
			continue;
		}
		$addPaths($row['filename'], ['emailattachment' => $row]);
	}
	$mail = reviewQuery($db, 'SELECT a.id, a.emailmessageid, a.documentarchiveid, a.emailattachmentid, a.filename, a.path, a.filesize, a.sha256, a.ordering, m.messagesent, m.deliverystatus FROM emailmessageattachment a JOIN emailmessage m ON m.id = a.emailmessageid AND m.clientid = a.clientid WHERE a.clientid = ? AND m.documentid = ? AND m.module = ? AND m.controller = ? ORDER BY a.id', 'iiss', [$document['clientid'], $document['id'], $document['module'], $table]);
	$client = (string)$document['clientid'];
	$shard = $client[0] . '/' . ($client[1] ?? '0') . '/' . $client;
	foreach ($mail as $row) {
		if (strtolower(pathinfo((string)$row['filename'], PATHINFO_EXTENSION)) !== 'pdf') continue;
		$prefix = 'files/attachments/emailmessage/' . $shard . '/' . intdiv((int)$row['emailmessageid'], 1000) . '/' . (int)$row['emailmessageid'] . '/';
		$status = null;
		$reference = false;
		foreach (['documentarchiveid' => 'documentarchive', 'emailattachmentid' => 'emailattachment'] as $field => $referenceTable) {
			if (empty($row[$field])) continue;
			$found = reviewQuery($db, "SELECT id FROM `$referenceTable` WHERE id = ? AND clientid = ? AND documentid = ? AND module = ? AND controller = ? AND filename = ?", 'iiisss', [$row[$field], $document['clientid'], $document['id'], $document['module'], $table, $row['filename']]);
			if ($found) $reference = true;
		}
		if (!$reference) $status = 'message_document_reference_missing';
		if ((int)$row['ordering'] < 1 || $row['path'] !== $prefix . (int)$row['ordering'] . '.pdf' || !DEEC_Emailattachment::validFilename((string)$row['filename'])) $status = 'metadata_path_rejected';
		$sources[] = ['source' => 'emailmessageattachment', 'path' => $row['path'], 'relative_path' => $row['path'], 'status' => $status, 'evidence' => $row];
	}
	$metadata = migrationMetadata($db, $document, $archive->relativePath((int)$document['clientid'], $document['module'], $table, (int)$document['id']));
	return ['document' => $document, 'sources' => $sources, 'current_plan' => $archive->plan($document, $metadata)];
}

function reviewDirectory(string $path): void
{
	reviewSafePath($path);
	if (!file_exists($path)) {
		if (!is_dir(dirname($path))) reviewDirectory(dirname($path));
		if (!mkdir($path, 0700)) throw new RuntimeException('Review directory could not be created');
	}
	clearstatcache(true, $path);
	if (!is_dir($path) || (fileperms($path) & 0777) !== 0700) throw new RuntimeException('Review directories require mode 0700');
}

function reviewPrivateFile(string $path): void
{
	reviewSafePath($path);
	$stat = lstat($path);
	if (!$stat || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1) throw new RuntimeException('Unsafe existing review file');
}

function reviewWrite(string $path, string $bytes): void
{
	reviewSafePath($path);
	$temp = dirname($path) . '/.write-' . bin2hex(random_bytes(12));
	$handle = @fopen($temp, 'x+b');
	if (!$handle) throw new RuntimeException('Review output could not be staged');
	try {
		try {
			if (!chmod($temp, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('Review output write failed');
		} finally { fclose($handle); }
		if (!@link($temp, $path)) throw new RuntimeException('Review output is already occupied');
	} finally { unlink($temp); }
}

function reviewJson(array $value): string { return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"; }

function reviewCopy(DEEC_DocumentArchive $sourceArchive, DEEC_DocumentArchive $targetArchive, array $before, string $target): void
{
	$source = $before['path'];
	clearstatcache(true, $source);
	$stat = lstat($source);
	$input = @fopen($source, 'rb');
	if (!$input) throw new RuntimeException('Candidate source is unreadable');
	$temp = null;
	try {
		$opened = fstat($input);
		if (!$stat || $stat['dev'] !== $opened['dev'] || $stat['ino'] !== $opened['ino'] || ($opened['mode'] & 0170000) !== 0100000) throw new RuntimeException('Candidate source changed');
		$again = $sourceArchive->inspect($source, 'pdf');
		if ($again['status'] !== 'ok' || $again['size'] !== $before['size'] || $again['sha256'] !== $before['sha256']) throw new RuntimeException('Candidate source changed');
		reviewSafePath($target);
		if (!file_exists($target)) {
			$temp = dirname($target) . '/.copy-' . bin2hex(random_bytes(12));
			$output = fopen($temp, 'x+b');
			try {
				if (!chmod($temp, 0600) || stream_copy_to_stream($input, $output) !== $before['size'] || !fflush($output) || (function_exists('fsync') && !fsync($output))) throw new RuntimeException('Candidate copy failed');
			} finally { fclose($output); }
			$copy = $targetArchive->inspect($temp, 'pdf');
			if ($copy['status'] !== 'ok' || $copy['size'] !== $before['size'] || $copy['sha256'] !== $before['sha256']) throw new RuntimeException('Candidate copy integrity differs');
		}
		clearstatcache(true, $source);
		$after = $sourceArchive->inspect($source, 'pdf');
		$finalStat = lstat($source);
		if ($after['status'] !== 'ok' || $after['size'] !== $before['size'] || $after['sha256'] !== $before['sha256'] || $finalStat['dev'] !== $opened['dev'] || $finalStat['ino'] !== $opened['ino']) throw new RuntimeException('Candidate source changed during copy');
		if ($temp !== null && !@link($temp, $target)) throw new RuntimeException('Candidate target is occupied');
	} finally {
		fclose($input);
		if ($temp !== null && is_file($temp)) unlink($temp);
	}
	reviewPrivateFile($target);
	$copy = $targetArchive->inspect($target, 'pdf');
	if ($copy['status'] !== 'ok' || $copy['size'] !== $before['size'] || $copy['sha256'] !== $before['sha256']) throw new RuntimeException('Existing candidate integrity differs');
}

function reviewCsv(array $rows): string
{
	$handle = fopen('php://temp', 'w+');
	foreach ($rows as $row) {
		$row = array_map(static function($value): string {
			$value = (string)$value;
			return preg_match('/^[=+@\-\t\r]/', $value) ? "'" . $value : $value;
		}, $row);
		fputcsv($handle, $row, ';', '"', '');
	}
	rewind($handle);
	$bytes = stream_get_contents($handle);
	fclose($handle);
	return $bytes;
}

function reviewRun(array $inputs, string $base, string $output, int $clientId, int $contactsClient, callable $load): array
{
	$base = rtrim($base, '/');
	$output = rtrim($output, '/');
	reviewSafePath($base);
	reviewSafePath($output);
	if (realpath($base) !== $base) throw new RuntimeException('Source base must be an existing canonical directory');
	foreach ([$base, dirname(__DIR__, 2)] as $webroot) {
		if ($output === $webroot || str_starts_with($output, $webroot . '/') || str_starts_with($webroot, $output . '/')) throw new RuntimeException('Review output must be outside source and application webroots');
	}
	foreach ($inputs['documents'] as $selected) reviewIdentity($selected, $clientId);
	reviewDirectory($output);
	$targetArchive = new DEEC_DocumentArchive($output);
	$sourceArchive = new DEEC_DocumentArchive($base);
	$lockPath = $output . '/.collector.lock';
	if (!file_exists($lockPath)) reviewWrite($lockPath, '');
	reviewPrivateFile($lockPath);
	$lock = fopen($lockPath, 'r+b');
	if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Another collector is running'); }
	try {
		$binding = ['schema_version' => 1, 'clientid' => $clientId, 'contacts_clientid' => $contactsClient, 'base' => $base];
		$bindingPath = $output . '/review.json';
		reviewSafePath($bindingPath);
		if (file_exists($bindingPath)) {
			reviewPrivateFile($bindingPath);
			if (json_decode(file_get_contents($bindingPath), true, 512, JSON_THROW_ON_ERROR) !== $binding) throw new RuntimeException('Review root belongs to another tenant or source context');
		} else reviewWrite($bindingPath, reviewJson($binding));
		$run = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
		$runDirectory = $output . '/runs/' . $run;
		reviewDirectory($output . '/runs');
		reviewDirectory($runDirectory);
		$result = ['schema_version' => 1, 'run_id' => $run, 'created_at' => gmdate('c'), 'clientid' => $clientId, 'contacts_clientid' => $contactsClient,
			'base' => $base, 'reports' => $inputs['reports'], 'selection' => $inputs['selection'], 'counts' => $inputs['counts'], 'documents' => [], 'errors' => 0, 'source_issues' => 0];
		$overview = [['type', 'id', 'document_number', 'year', 'reported_reasons', 'current_reason', 'candidates', 'source_issues', 'manifest']];
		$groups = ['type' => [], 'year' => [], 'reason' => []];
		foreach ($inputs['documents'] as $selected) {
			$directoryName = $selected['module'] . '-' . $selected['controller'] . '-' . $selected['id'] . '-' . ($selected['document_number'] === '' ? 'unnumbered' : substr(rawurlencode($selected['document_number']), 0, 90));
			$directory = $output . '/' . $directoryName;
			reviewDirectory($directory);
			reviewDirectory($directory . '/candidates');
			reviewDirectory($directory . '/external');
			$first = $directory . '/manifest.json';
			reviewSafePath($first);
			reviewSafePath($directory . '/selection.json');
			if (file_exists($first)) {
				reviewPrivateFile($first);
				$existing = json_decode(file_get_contents($first), true, 512, JSON_THROW_ON_ERROR);
				if (reviewIdentity($existing['selected'] ?? [], $clientId) !== reviewIdentity($selected, $clientId)) throw new RuntimeException('Existing review manifest belongs to another document');
			}
			$manifest = ['schema_version' => 1, 'run_id' => $run, 'observed_at' => gmdate('c'), 'selected' => $selected, 'reports' => $inputs['reports'],
				'document' => null, 'current_reason' => 'document_unavailable', 'sources' => [], 'candidates' => [], 'manual_selection_required' => true,
				'invoice_manual_selection' => $selected['controller'] === 'invoice', 'suggestion' => null, 'errors' => []];
			$context = $load($selected, $sourceArchive);
			if ($context !== null) {
				$document = $context['document'];
				if (reviewId($document['clientid'] ?? null) !== $clientId || reviewKey($document) !== reviewKey($selected)) throw new RuntimeException('Loaded document belongs to another tenant or identity');
				$manifest['document'] = $document;
				if ((string)($document['document_number'] ?? '') !== $selected['document_number'] || ($selected['reported_date'] !== null && $selected['reported_date'] !== $document['document_date'])) {
					$manifest['current_reason'] = 'document_identity_changed';
				} else {
					$manifest['current_reason'] = $context['current_plan']['reason'] ?: $context['current_plan']['status'];
					foreach ($context['sources'] as $source) {
						if (isset($source['relative_path']) && $source['status'] !== 'metadata_path_rejected') $source['path'] = $base . '/' . $source['relative_path'];
						$entry = $source + ['size' => null, 'sha256' => null, 'mtime' => null, 'candidate_path' => null, 'metadata_integrity' => 'not_recorded'];
						if ($source['status'] === null && $source['path'] !== null) {
							clearstatcache(true, $source['path']);
							try {
								$observed = $sourceArchive->inspect($source['path'], 'pdf');
								$entry = array_replace($entry, $observed);
								if ($observed['status'] === 'ok') {
									$recorded = $source['evidence']['emailattachment'] ?? ($source['source'] === 'emailmessageattachment' ? $source['evidence'] : []);
									if (isset($recorded['filesize'])) $entry['metadata_integrity'] = (int)$recorded['filesize'] === $observed['size'] && (!isset($recorded['sha256']) || $recorded['sha256'] === $observed['sha256']) ? 'matches' : 'differs';
									$entry['mtime'] = filemtime($source['path']);
									$relative = 'candidates/' . $observed['sha256'] . '.pdf';
									reviewCopy($sourceArchive, $targetArchive, $observed, $directory . '/' . $relative);
									$entry['candidate_path'] = $relative;
									$manifest['candidates'][$observed['sha256']] = ['path' => $relative, 'size' => $observed['size'], 'sha256' => $observed['sha256']];
								}
							} catch (Throwable $exception) { $entry['status'] = 'copy_failed'; $entry['error'] = $exception->getMessage(); $manifest['errors'][] = $exception->getMessage(); }
						}
						$manifest['sources'][] = $entry;
					}
					if ($manifest['candidates']) {
						$latest = [];
						foreach ($manifest['sources'] as $source) if ($source['status'] === 'ok' && $source['candidate_path'] !== null) $latest[$source['sha256']] = max($latest[$source['sha256']] ?? 0, $source['mtime']);
						if ($latest) {
							$newest = array_keys($latest, max($latest), true);
							if (count($newest) === 1) $manifest['suggestion'] = $manifest['candidates'][$newest[0]] + ['basis' => 'latest_source_mtime', 'mtime' => $latest[$newest[0]], 'authoritative' => false];
						}
					}
				}
			}
			if (in_array($manifest['current_reason'], ['document_unavailable', 'document_identity_changed'], true)) $manifest['errors'][] = $manifest['current_reason'];
			$manifest['candidates'] = array_values($manifest['candidates']);
			$manifestName = file_exists($first) ? 'manifest.' . $run . '.json' : 'manifest.json';
			reviewWrite($directory . '/' . $manifestName, reviewJson($manifest));
			$selectionPath = $directory . '/selection.json';
			if (!file_exists($selectionPath)) reviewWrite($selectionPath, reviewJson(['schema_version' => 1, 'document' => array_intersect_key($selected, array_flip(['module', 'controller', 'id', 'clientid', 'document_number'])),
				'decision' => 'pending', 'candidate_path' => null, 'sha256' => null, 'origin' => null, 'reason' => null, 'reviewed_by' => null, 'reviewed_at' => null]));
			else reviewPrivateFile($selectionPath);
			$type = $selected['module'] . '/' . $selected['controller'];
			$date = $manifest['document']['document_date'] ?? $selected['reported_date'] ?? '';
			$year = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) ? substr($date, 0, 4) : 'unknown';
			$issues = count(array_filter($manifest['sources'], static fn(array $source): bool => !in_array($source['status'], ['ok', 'missing'], true) || $source['metadata_integrity'] === 'differs'));
			$candidates = count($manifest['candidates']);
			$relativeManifest = $directoryName . '/' . $manifestName;
			$overview[] = [$type, $selected['id'], $selected['document_number'], $year, implode('|', $selected['reported_reasons']), $manifest['current_reason'], $candidates, $issues, $relativeManifest];
			$sourceReasons = [];
			foreach ($manifest['sources'] as $source) {
				if (!in_array($source['status'], ['ok', 'missing'], true)) $sourceReasons[] = $source['status'];
				if ($source['metadata_integrity'] === 'differs') $sourceReasons[] = 'recorded_integrity_differs';
			}
			foreach (['type' => [$type], 'year' => [$year], 'reason' => array_unique(array_merge($selected['reported_reasons'], [$manifest['current_reason']], $sourceReasons))] as $group => $values) {
				foreach ($values as $value) {
					$groups[$group][$value] ??= ['documents' => 0, 'candidates' => 0, 'issues' => 0];
					$groups[$group][$value]['documents']++;
					$groups[$group][$value]['candidates'] += $candidates;
					$groups[$group][$value]['issues'] += $issues;
				}
			}
			$result['errors'] += count($manifest['errors']);
			$result['source_issues'] += $issues;
			$result['documents'][] = ['key' => reviewKey($selected), 'manifest' => $relativeManifest, 'candidates' => $candidates, 'source_issues' => $issues];
		}
		reviewWrite($runDirectory . '/overview.csv', reviewCsv($overview));
		foreach ($groups as $group => $values) {
			ksort($values);
			$rows = [[$group, 'documents', 'candidates', 'source_issues']];
			foreach ($values as $value => $counts) $rows[] = array_merge([$value], array_values($counts));
			reviewWrite($runDirectory . '/by-' . $group . '.csv', reviewCsv($rows));
		}
		reviewWrite($runDirectory . '/run.json', reviewJson($result));
		return $result + ['run_directory' => $runDirectory];
	} finally { flock($lock, LOCK_UN); fclose($lock); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

$options = getopt('', ['config:', 'environment:', 'base:', 'output:', 'client:', 'contacts-client:', 'jsonl:', 'audit-tsv:', 'select:']);
umask(0077);
$db = null;
try {
	foreach (['config', 'base', 'output', 'client', 'jsonl'] as $required) if (!isset($options[$required]) || !is_string($options[$required]) || $options[$required] === '') throw new RuntimeException('Required: --config --base --output --client --jsonl [--audit-tsv] [--select=all-unarchived|all-blocked|both|audit|blocked-invoices] [--contacts-client]');
	$clientId = reviewId($options['client']);
	$contactsClient = reviewId($options['contacts-client'] ?? $clientId);
	$inputs = reviewInputs($options['jsonl'], $options['audit-tsv'] ?? null, $options['select'] ?? 'all-blocked', $clientId);
	if ($inputs['contacts_clientid'] !== $contactsClient) throw new RuntimeException('Contact tenant differs from dry-run report');
	$settings = migrationConfig($options['config'], $options['environment'] ?? 'production');
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$db = new mysqli($settings['host'], $settings['username'], $settings['password'], $settings['dbname'], $settings['port']);
	$db->set_charset('utf8mb4');
	$db->query('SET TRANSACTION READ ONLY');
	$db->begin_transaction();
	$result = reviewRun($inputs, $options['base'], $options['output'], $clientId, $contactsClient, static fn(array $selected, DEEC_DocumentArchive $archive): ?array => reviewLoadDocument($db, $archive, $selected, $contactsClient));
	$db->rollback();
	echo reviewJson(['run_directory' => $result['run_directory'], 'counts' => $result['counts'], 'errors' => $result['errors'], 'source_issues' => $result['source_issues']]);
	$exit = $result['errors'] || $result['source_issues'] ? 1 : 0;
} catch (Throwable $exception) {
	if ($db) { try { $db->rollback(); } catch (Throwable $ignored) {} }
	fwrite(STDERR, $exception->getMessage() . "\n");
	$exit = 1;
} finally { if ($db) $db->close(); }
exit($exit);
