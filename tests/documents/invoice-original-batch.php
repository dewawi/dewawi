<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/document-recovery.php';

function invoiceBatchText($value): string
{
	if (!is_string($value) || trim($value) === '' || strlen($value) > 4000) throw new RuntimeException('A concrete provenance/comparison/backup reference is required');
	return trim($value);
}

function invoiceBatchJobs(string $root, string $run, int $client): array
{
	DEEC_DocumentReview::validateRoot($root, $client);
	reviewPrivateFile($root . '/.collector.lock');
	reviewSafePath($run);
	if (!str_starts_with($run, $root . '/runs/')) throw new RuntimeException('Run must belong to the private review area');
	$locator = DEEC_DocumentReview::locatorPath($client);
	if (file_exists($locator)) {
		$location = recoveryJsonFile($locator);
		if (($location['review'] ?? '') !== $root || ($location['clientid'] ?? 0) !== $client || ($location['base'] ?? '') !== BASE_PATH || ($location['binding_sha256'] ?? '') !== hash_file('sha256', $root . '/review.json')) throw new RuntimeException('A different or changed review area is bound to this tenant');
	}
	$record = recoveryJsonFile($run);
	if (($record['clientid'] ?? null) !== $client || ($record['base'] ?? '') !== BASE_PATH) throw new RuntimeException('Foreign recovery run');
	$jobs = [];
	foreach ($record['documents'] as $entry) {
		$path = recoveryPath($root, $entry['manifest']);
		if (dirname(dirname($path)) !== $root) throw new RuntimeException('Invalid recovery manifest directory');
		$manifest = recoveryJsonFile($path); $identity = reviewIdentity($manifest['selected'], $client);
		$key = reviewKey($identity);
		if ($key !== $entry['key'] || isset($jobs[$key])) throw new RuntimeException('Ambiguous recovery run identity');
		$jobs[$key] = ['identity' => $identity, 'manifest' => $manifest, 'directory' => dirname($path)];
	}
	return $jobs;
}

function invoiceBatchZip(string $path, string $scratch): array
{
	reviewPrivateFile($path);
	if (filesize($path) > 209715200) throw new RuntimeException('ZIP exceeds 200 MiB');
	// Bound central-directory allocation before libzip reads untrusted metadata.
	$input = fopen($path, 'rb');
	try { fseek($input, max(0, filesize($path) - 65557)); $tail = stream_get_contents($input); } finally { fclose($input); }
	$end = strrpos($tail, "PK\x05\x06");
	if ($end === false || strlen($tail) - $end < 22) throw new RuntimeException('Missing ZIP directory');
	$directory = unpack('vdisk/vstart/ventries_disk/ventries/Vsize/Voffset/vcomment', substr($tail, $end + 4, 18));
	if (($end >= 20 && substr($tail, $end - 20, 4) === "PK\x06\x07") || $directory['disk'] !== 0 || $directory['start'] !== 0 || $directory['entries_disk'] !== $directory['entries'] || $directory['entries'] < 1 || $directory['entries'] > 101 || $directory['size'] > 1048576 || $directory['offset'] + $directory['size'] > filesize($path) - strlen($tail) + $end || $end + 22 + $directory['comment'] !== strlen($tail)) throw new RuntimeException('ZIP directory count/size/bounds, ZIP64 or multi-volume input is unsupported');
	$zipSha = hash_file('sha256', $path); $zip = new ZipArchive(); $files = []; $seen = []; $total = 0;
	if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Invalid ZIP');
	try {
		if ($zip->numFiles !== $directory['entries'] || $zip->numFiles > 101) throw new RuntimeException('ZIP exceeds 100 PDFs and one directory');
		for ($index = 0; $index < $zip->numFiles; $index++) {
			$stat = $zip->statIndex($index); $name = $stat['name'];
			if (isset($seen[$name]) || !$zip->getExternalAttributesIndex($index, $system, $attributes)) throw new RuntimeException('Duplicate or unreadable ZIP entry');
			$seen[$name] = true; $type = $system === ZipArchive::OPSYS_UNIX ? (($attributes >> 16) & 0170000) : 0;
			if ($name === 'correct/' && $stat['size'] === 0 && in_array($type, [0, 0040000], true)) continue;
			if (!in_array($type, [0, 0100000], true) || !preg_match('~^correct/([1-9][0-9]{0,9})\.pdf$~D', $name, $match) || (int)$match[1] > 2147483647) throw new RuntimeException('ZIP allows only regular correct/<exact numeric invoice number>.pdf entries');
			if (($stat['encryption_method'] ?? 0) !== 0 || !in_array($stat['comp_method'], [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true) || $stat['size'] < 12 || $stat['size'] > 20971520 || $stat['comp_size'] < 1 || $stat['size'] > $stat['comp_size'] * 200 || ($total += $stat['size']) > 209715200) throw new RuntimeException('ZIP encryption, compression or expansion limit exceeded');
			$target = $scratch . '/' . $index . '.pdf';
			$input = $zip->getStream($name); $output = fopen($target, 'x+b');
			if (!$input || !$output) throw new RuntimeException('ZIP entry cannot be staged');
			try {
				if (!chmod($target, 0600)) throw new RuntimeException('PDF staging permissions failed');
				$size = 0; $crc = hash_init('crc32b');
				while (!feof($input)) {
					$chunk = fread($input, 65536);
					if ($chunk === false || ($size += strlen($chunk)) > $stat['size'] || $size > 20971520) throw new RuntimeException('ZIP actual expansion differs');
					hash_update($crc, $chunk);
					if (fwrite($output, $chunk) !== strlen($chunk)) throw new RuntimeException('PDF staging write failed');
				}
				if ($size !== $stat['size'] || hash_final($crc) !== sprintf('%08x', $stat['crc'])) throw new RuntimeException('ZIP entry size or CRC differs');
			} finally { fclose($input); fclose($output); }
			$file = (new DEEC_DocumentArchive($scratch))->inspect($target, 'pdf');
			if ((new finfo(FILEINFO_MIME_TYPE))->file($target) !== 'application/pdf') $file['status'] = 'invalid_pdf';
			$files[$name] = $file + ['document_number' => $match[1]];
		}
	} finally { $zip->close(); }
	if (!$files || hash_file('sha256', $path) !== $zipSha) throw new RuntimeException('ZIP is empty or changed while reading');
	ksort($files, SORT_STRING);
	return ['sha256' => $zipSha, 'files' => $files];
}

function invoiceBatchDeclaration(?string $path, int $client, int $user, string $zipSha, array $files): array
{
	if ($path === null) return [];
	$declaration = recoveryJsonFile($path);
	if (($declaration['schema_version'] ?? 0) !== 1 || ($declaration['clientid'] ?? 0) !== $client || ($declaration['operator_user'] ?? 0) !== $user || ($declaration['zip_sha256'] ?? '') !== $zipSha) throw new RuntimeException('Declaration tenant, operator or ZIP differs');
	$entries = [];
	foreach ($declaration['documents'] as $row) {
		$entry = $row['entry'] ?? '';
		if (!is_string($entry) || !isset($files[$entry]) || isset($entries[$entry]) || ($row['sha256'] ?? '') !== $files[$entry]['sha256']) throw new RuntimeException('Declaration contains an unknown, duplicate or changed PDF');
		$entries[$entry] = $row;
	}
	return $entries;
}

function invoiceBatchState(Zend_Db_Adapter_Abstract $db, array $identity, array $file, bool $lock = false): array
{
	$archive = new DEEC_DocumentArchive(BASE_PATH); $rows = recoveryArchives($db, $identity, $lock);
	$relative = $archive->relativePath($identity['clientid'], 'sales', 'invoice', $identity['id']);
	$canonical = $archive->inspect(BASE_PATH . '/' . $relative, 'pdf');
	$status = 'missing';
	if ($rows) {
		$status = count($rows) === 1 && (int)$rows[0]['version'] === 1 && $rows[0]['path'] === $relative && (int)$rows[0]['filesize'] === $file['size'] && $rows[0]['sha256'] === $file['sha256'] && DEEC_Emailattachment::validFilename((string)$rows[0]['filename'])
			&& $canonical['status'] === 'ok' && $canonical['size'] === $file['size'] && $canonical['sha256'] === $file['sha256'] ? 'already_archived' : 'CONFLICT';
	} elseif ($canonical['status'] !== 'missing') $status = 'CONFLICT';
	return ['status' => $status, 'registrations' => $rows, 'canonical' => $canonical];
}

function invoiceBatchDiagnostics(array $job, string $root, string $sha): void
{
	if (glob($root . '/*/diagnostics/' . $sha . '.json') || glob($root . '/*/reconstructions/' . $sha . '.json')) throw new RuntimeException('Known diagnostic/reconstructed PDF cannot be relabeled as an historical original');
	foreach (glob($root . '/*/diagnostics/*.json') as $path) {
		$proof = recoveryJsonFile($path);
		$identity = reviewIdentity($proof['identity'] ?? [], $job['identity']['clientid']);
		if (reviewKey($identity) === reviewKey($job['identity'])) throw new RuntimeException('Existing diagnostic issues require manual clarification before original import');
	}
	foreach (glob(DEEC_DocumentReview::publicationDirectory($root, $job['identity'], true) . '/*.json') as $path) { recoveryJsonFile($path); throw new RuntimeException('Diagnostic publication requires manual clarification'); }
}

function invoiceBatchInvoice(Zend_Db_Adapter_Abstract $db, int $client, string $number, bool $lock = false): array
{
	$documents = $db->fetchAll('SELECT * FROM invoice WHERE clientid = ? AND invoiceid = ? ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), [$client, (int)$number]);
	if (count($documents) !== 1 || (string)$documents[0]['invoiceid'] !== $number) throw new RuntimeException('Invoice number is missing or ambiguous in this tenant');
	return $documents[0];
}

function invoiceBatchRow(Zend_Db_Adapter_Abstract $db, string $entry, array $file, array $jobs, array $statements, string $root): array
{
	$row = ['entry' => $entry, 'document_number' => $file['document_number'], 'sha256' => $file['sha256'], 'size' => $file['size'], 'identity' => null, 'document_date' => null, 'archive_state' => null, 'candidate_status' => $file['status'], 'proposed_action' => 'blocked', 'reason' => '', 'attestation' => $statements[$entry] ?? null];
	try {
		if ($file['status'] !== 'ok') throw new RuntimeException('Invalid PDF structure or MIME type');
		$document = invoiceBatchInvoice($db, (int)Zend_Registry::get('Client')['id'], $file['document_number']);
		$identity = ['module' => 'sales', 'controller' => 'invoice', 'id' => (int)$document['id'], 'clientid' => (int)$document['clientid'], 'document_number' => $file['document_number']];
		$row['identity'] = $identity; $row['document_date'] = $document['invoicedate'];
		$snapshot = recoverySnapshot($db, $identity); $row['snapshot_sha256'] = $snapshot['sha256']; $row['business_basis'] = $snapshot['data'];
		$row['archive_state'] = invoiceBatchState($db, $identity, $file);
		if ($row['archive_state']['status'] === 'CONFLICT') { $row['candidate_status'] = 'CONFLICT'; throw new RuntimeException('Existing archive registration or canonical file differs; no replacement'); }
		if ($row['archive_state']['status'] === 'already_archived') { $row['candidate_status'] = 'verified_existing_original'; $row['proposed_action'] = 'already_archived'; return $row; }
		$job = $jobs[reviewKey($identity)] ?? null;
		if (!$job || $job['identity'] !== $identity) throw new RuntimeException('Invoice is not bound to the selected recovery run');
		$row['manifest_sha256'] = [];
		foreach (glob($job['directory'] . '/manifest*.json') as $path) { $manifest = recoveryJsonFile($path); if (reviewIdentity($manifest['selected'], $identity['clientid']) !== $identity) throw new RuntimeException('Review manifest identity differs'); $row['manifest_sha256'][basename($path)] = hash_file('sha256', $path); }
		recoveryJsonFile($job['directory'] . '/selection.json'); $row['selection_sha256'] = hash_file('sha256', $job['directory'] . '/selection.json');
		invoiceBatchDiagnostics($job, $root, $file['sha256']);
		$row['candidate_status'] = 'attestation_required';
		DEEC_DocumentRecovery::customerArchiveAttestation($row['attestation'] ?? [], $identity, $snapshot['sha256'], $file['sha256']);
		$row['candidate_status'] = 'verified_customer_archive_original'; $row['proposed_action'] = 'register';
	} catch (RuntimeException | InvalidArgumentException | JsonException $error) { $row['reason'] = $error->getMessage(); }
	return $row;
}

function invoiceBatchReport(string $path, array $record): void
{
	$record['summary'] = array_count_values(array_column($record['rows'], isset($record['plan_sha256']) ? 'status' : 'proposed_action'));
	reviewWrite($path, reviewJson($record));
	$stream = fopen('php://temp', 'w+b');
	$fields = ['entry', 'document_number', 'documentid', 'document_date', 'sha256', 'size', 'snapshot_sha256', 'archive_status', 'candidate_status', 'proposed_action', 'status', 'reason'];
	fputcsv($stream, $fields, ';', '"', '');
	foreach ($record['rows'] as $row) {
		$row['documentid'] = $row['identity']['id'] ?? ''; $row['archive_status'] = $row['archive_state']['status'] ?? '';
		fputcsv($stream, array_map(static function(string $field) use ($row): string { $value = (string)($row[$field] ?? ''); return preg_match('/^[=+@-]/', $value) ? "'" . $value : $value; }, $fields), ';', '"', '');
	}
	rewind($stream); reviewWrite(substr($path, 0, -5) . '.csv', stream_get_contents($stream)); fclose($stream);
}

function invoiceBatchApplyRow(Zend_Db_Adapter_Abstract $db, array $row, array $file, array $job, array $authorization, string $root): array
{
	$id = $row['identity']; $sha = $file['sha256']; $archive = new DEEC_DocumentArchive($root);
	if (recoverySnapshot($db, $id, true)['sha256'] !== $row['snapshot_sha256']) throw new RuntimeException('Stale business-data fingerprint; create and confirm a new plan');
	if ((int)invoiceBatchInvoice($db, $id['clientid'], $id['document_number'], true)['id'] !== $id['id']) throw new RuntimeException('Invoice number mapping changed');
	$state = invoiceBatchState($db, $id, $file, true);
	if ($state['status'] === 'already_archived') return ['status' => 'already_archived'];
	invoiceBatchDiagnostics($job, $root, $sha);
	$manifestHashes = [];
	foreach (glob($job['directory'] . '/manifest*.json') as $path) { recoveryJsonFile($path); $manifestHashes[basename($path)] = hash_file('sha256', $path); }
	if ($manifestHashes !== $row['manifest_sha256']) throw new RuntimeException('Review manifests changed after planning');
	$attemptPath = $job['directory'] . '/batch-original-attempts/' . $authorization['plan_sha256'] . '.json';
	if ($state['status'] === 'CONFLICT') {
		$attempt = file_exists($attemptPath) ? recoveryJsonFile($attemptPath) : [];
		if ($state['registrations'] || ($attempt['authorization']['plan_sha256'] ?? '') !== $authorization['plan_sha256'] || ($attempt['identity'] ?? null) !== $id || ($attempt['sha256'] ?? '') !== $sha || ($attempt['snapshot_sha256'] ?? '') !== $row['snapshot_sha256'] || $state['canonical']['status'] !== 'ok' || $state['canonical']['sha256'] !== $sha || $state['canonical']['size'] !== $file['size']) throw new RuntimeException('CONFLICT: existing original/canonical file is protected');
	}
	$selection = recoveryJsonFile($job['directory'] . '/selection.json');
	$ours = ($selection['origin']['batch_plan_sha256'] ?? '') === $authorization['plan_sha256'];
	if (!$ours && hash_file('sha256', $job['directory'] . '/selection.json') !== $row['selection_sha256']) throw new RuntimeException('Another manual selection replaced the planned review state');
	$statement = $row['attestation']; DEEC_DocumentRecovery::customerArchiveAttestation($statement, $id, $row['snapshot_sha256'], $sha);
	$target = $job['directory'] . '/external/batch-original-' . $sha . '.pdf'; reviewDirectory(dirname($target));
	if (!file_exists($target)) $archive->storeEvidence($file['path'], $target);
	reviewPrivateFile($target); $copied = $archive->inspect($target, 'pdf');
	if ($copied['status'] !== 'ok' || $copied['sha256'] !== $sha || $copied['size'] !== $file['size']) throw new RuntimeException('Staged original size/SHA differs');
	reviewDirectory(dirname($attemptPath));
	if (!file_exists($attemptPath)) reviewWrite($attemptPath, reviewJson(['identity' => $id, 'sha256' => $sha, 'snapshot_sha256' => $row['snapshot_sha256'], 'authorization' => $authorization]));
	if (!$ours) {
		$comparison = ['kind' => 'customer_archive_attestation', 'assertion_type' => 'retrospective_customer_archive_attestation', 'identity' => $id, 'sha256' => $sha, 'snapshot_sha256' => $row['snapshot_sha256'], 'confirmed_by' => $authorization['confirmed_by'], 'confirmed_at' => $authorization['confirmed_at'], 'plan_sha256' => $authorization['plan_sha256'], 'declaration_sha256' => $authorization['declaration_sha256'], 'statement' => $statement];
		recoverySelect($job, ['document' => $id, 'decision' => 'selected', 'candidate_path' => 'external/' . basename($target), 'sha256' => $sha, 'reason' => $statement['comparison_reference'], 'origin' => ['kind' => 'historical_pdf', 'source_path' => $statement['provenance'], 'category' => 'verified_recovered_original', 'batch_plan_sha256' => $authorization['plan_sha256'], 'zip_entry' => $row['entry'], 'zip_sha256' => $authorization['zip_sha256']], 'original_comparison' => $comparison], $authorization['confirmed_by'], $archive);
	} elseif (($selection['sha256'] ?? '') !== $sha || ($selection['original_comparison']['statement'] ?? null) !== $statement) throw new RuntimeException('Prior batch selection differs');
	recoveryApprove($db, $job, $archive);
	$result = recoveryImport($db, $job, $archive, $authorization);
	if (invoiceBatchState($db, $id, $file, true)['status'] !== 'already_archived') throw new RuntimeException('Post-import registration/path/size/SHA verification failed');
	return $result;
}

function invoiceBatchMain(array $options): array
{
	umask(0077);
	foreach (['config', 'client', 'operator-user', 'zip', 'review', 'run', 'action'] as $key) if (!isset($options[$key]) || !is_string($options[$key]) || $options[$key] === '') throw new RuntimeException('Required option: --' . $key);
	$client = reviewId($options['client']); $user = reviewId($options['operator-user']); $root = $options['review']; $action = $options['action'];
	if (!in_array($action, ['plan', 'apply'], true)) throw new RuntimeException('Action must be plan or apply');
	$binding = recoveryJsonFile($root . '/review.json'); $base = $binding['base'] ?? '';
	if (($binding['clientid'] ?? null) !== $client) throw new RuntimeException('Foreign review tenant');
	$db = null; $scratch = null; $lock = null;
	try {
		$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $base, $client, $user);
		DEEC_DocumentReview::validateRoot($root, $client);
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if (!$permission->hasPermission('sales', 'invoice', 'view') || !$permission->hasPermission('sales', 'invoice', 'edit')) throw new RuntimeException('Invoice view and edit permissions are required');
		$jobs = invoiceBatchJobs($root, $options['run'], $client);
		$lock = fopen($root . '/.collector.lock', 'r+b');
		if (!flock($lock, ($action === 'apply' ? LOCK_EX : LOCK_SH) | LOCK_NB)) throw new RuntimeException('Review area is busy');
		$jobs = invoiceBatchJobs($root, $options['run'], $client);
		$scratch = $root . '/runs/.invoice-batch-' . bin2hex(random_bytes(12)); reviewDirectory($scratch);
		$zip = invoiceBatchZip($options['zip'], $scratch);
		$statements = invoiceBatchDeclaration($options['declaration'] ?? null, $client, $user, $zip['sha256'], $zip['files']);
		$header = ['schema_version' => 1, 'kind' => 'invoice_original_batch', 'action' => $action, 'clientid' => $client, 'operator_user' => $user, 'base' => $base, 'review' => $root, 'run' => $options['run'], 'run_sha256' => hash_file('sha256', $options['run']), 'review_binding_sha256' => hash_file('sha256', $root . '/review.json'), 'zip' => $options['zip'], 'zip_sha256' => $zip['sha256'], 'declaration_sha256' => isset($options['declaration']) ? hash_file('sha256', $options['declaration']) : null, 'recorded_at' => gmdate('c')];
		$rows = [];
		if ($action === 'plan') {
			$db->query('SET TRANSACTION READ ONLY'); $db->beginTransaction();
			try { foreach ($zip['files'] as $entry => $file) $rows[] = invoiceBatchRow($db, $entry, $file, $jobs, $statements, $root); }
			finally { $db->rollBack(); }
		} else {
			$planPath = $options['plan'] ?? ''; reviewPrivateFile($planPath);
			if (!str_starts_with($planPath, $root . '/runs/') || !is_string($options['confirm-plan'] ?? null) || !hash_equals(hash_file('sha256', $planPath), $options['confirm-plan']) || !array_key_exists('backup-verified', $options)) throw new RuntimeException('Apply requires the exact --confirm-plan SHA and --backup-verified');
			$backupReference = invoiceBatchText($options['backup-reference'] ?? null); $plan = recoveryJsonFile($planPath);
			foreach (['schema_version', 'kind', 'clientid', 'operator_user', 'base', 'review', 'run', 'run_sha256', 'review_binding_sha256', 'zip', 'zip_sha256', 'declaration_sha256'] as $field) if (($plan[$field] ?? null) !== $header[$field]) throw new RuntimeException('Plan context/input differs: ' . $field);
			if (($plan['action'] ?? '') !== 'plan' || $header['declaration_sha256'] === null || array_column($plan['rows'], 'entry') !== array_keys($zip['files'])) throw new RuntimeException('Apply requires a declared, exact complete plan scope');
			$header['plan_sha256'] = $options['confirm-plan'];
			$authorization = ['kind' => 'invoice_original_batch', 'plan_sha256' => $header['plan_sha256'], 'declaration_sha256' => $header['declaration_sha256'], 'zip_sha256' => $zip['sha256'], 'confirmed_by' => $user, 'confirmed_at' => gmdate('c'), 'database_and_file_backup_confirmed' => true, 'backup_reference' => $backupReference];
			foreach ($plan['rows'] as $row) {
				$row['status'] = 'blocked';
				if (in_array($row['proposed_action'], ['register', 'already_archived'], true)) {
					try {
						$file = $zip['files'][$row['entry']]; $id = reviewIdentity($row['identity'], $client);
						if ($id['module'] !== 'sales' || $id['controller'] !== 'invoice' || $file['document_number'] !== $id['document_number'] || $row['document_number'] !== $id['document_number']) throw new RuntimeException('ZIP filename and invoice identity differ');
						if ($file['status'] !== 'ok' || $file['sha256'] !== $row['sha256'] || $file['size'] !== $row['size']) throw new RuntimeException('Planned PDF differs');
						$db->beginTransaction();
						if ($row['proposed_action'] === 'already_archived') {
							if ((int)invoiceBatchInvoice($db, $id['clientid'], $id['document_number'], true)['id'] !== $id['id'] || recoverySnapshot($db, $id, true)['sha256'] !== $row['snapshot_sha256'] || invoiceBatchState($db, $id, $file, true)['status'] !== 'already_archived') throw new RuntimeException('CONFLICT: existing original changed since planning');
							$result = ['status' => 'already_archived'];
						} else {
							if (($statements[$row['entry']] ?? null) !== $row['attestation']) throw new RuntimeException('Planned declaration differs');
							$job = $jobs[reviewKey($id)] ?? null;
							if (!$job || $job['identity'] !== $id) throw new RuntimeException('Review identity changed');
							$result = invoiceBatchApplyRow($db, $row, $file, $job, $authorization, $root);
						}
						$db->commit(); $row['status'] = $result['status']; $row['reason'] = '';
					} catch (Throwable $error) { if ($db->getConnection()->inTransaction()) $db->rollBack(); $row['status'] = str_starts_with($error->getMessage(), 'CONFLICT:') ? 'CONFLICT' : 'failed'; $row['reason'] = $error->getMessage(); }
				}
				$rows[] = $row;
			}
			$header['authorization'] = $authorization;
		}
		$report = $root . '/runs/invoice-original-batch-' . $action . '-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(8)) . '.json';
		invoiceBatchReport($report, $header + ['rows' => $rows]);
		$template = null;
		if ($action === 'plan' && !isset($options['declaration'])) {
			$template = substr($report, 0, -5) . '.declaration.json'; $entries = [];
			foreach ($rows as $row) $entries[] = ['entry' => $row['entry'], 'document' => $row['identity'], 'sha256' => $row['sha256'], 'snapshot_sha256' => $row['snapshot_sha256'] ?? null, 'provenance' => '', 'comparison_reference' => '', 'comparison_performed_at' => null, 'original_provenance_verified' => false, 'identity_verified' => false, 'customer_archive_compared' => false, 'business_values_compared' => false, 'positions_taxes_totals_match' => false, 'unresolved_differences' => true, 'differences' => []];
			reviewWrite($template, reviewJson(['schema_version' => 1, 'clientid' => $client, 'operator_user' => $user, 'zip_sha256' => $zip['sha256'], 'documents' => $entries]));
		}
		return ['report' => $report, 'report_sha256' => hash_file('sha256', $report), 'declaration_template' => $template, 'summary' => array_count_values(array_column($rows, $action === 'plan' ? 'proposed_action' : 'status')), 'exit' => $action === 'apply' && count(array_filter($rows, static fn(array $row): bool => in_array($row['status'], ['failed', 'blocked', 'CONFLICT'], true))) ? 1 : 0];
	} finally {
		if ($db) { if ($db->getConnection()->inTransaction()) $db->rollBack(); $db->closeConnection(); }
		if ($scratch) { foreach (new FilesystemIterator($scratch) as $file) unlink($file->getPathname()); rmdir($scratch); }
		if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
	$options = getopt('', ['config:', 'environment:', 'client:', 'operator-user:', 'zip:', 'review:', 'run:', 'action:', 'declaration:', 'plan:', 'confirm-plan:', 'backup-verified', 'backup-reference:']);
	$result = invoiceBatchMain($options);
	fwrite(STDOUT, reviewJson($result)); exit($result['exit']);
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
