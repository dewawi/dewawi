<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/library/DEEC/UpdateMaintenance.php';
DEEC_UpdateMaintenance::protectInstallation(dirname(__DIR__));
require_once dirname(__DIR__) . '/tests/documents/document-recovery.php';
require_once dirname(__DIR__) . '/library/DEEC/DocumentReview.php';

function completenessTypes(?string $filter): array
{
	$types = [];
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) $types[$module . '/' . $controller] = [$module, $controller];
	if ($filter === null) return $types;
	if (!isset($types[$filter])) throw new RuntimeException('Invalid document type');
	return [$filter => $types[$filter]];
}

function completenessGroups(): array
{
	return array_fill_keys(['original_complete', 'original_without_registration', 'missing_original_valid_copy', 'missing_original_copy_not_generated', 'damaged_or_unreadable_pdf', 'invalid_archive_metadata', 'other_blockers'], 0);
}

function completenessRegistrations(Zend_Db_Adapter_Abstract $db, DEEC_DocumentArchive $archive, array $identity): array
{
	$relative = $archive->relativePath($identity['clientid'], $identity['module'], $identity['controller'], $identity['id']);
	// Two rows already prove ambiguity; never load an unbounded registration set.
	return $db->fetchAll('SELECT id, clientid, module, controller, documentid, format, version, path, filename, filesize, sha256 FROM documentarchive WHERE (clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format <> ?) OR path = ? ORDER BY id LIMIT 2', [$identity['clientid'], $identity['module'], $identity['controller'], $identity['id'], 'xml', $relative]);
}

function completenessOriginal(Zend_Db_Adapter_Abstract $db, DEEC_DocumentArchive $archive, array $identity): array
{
	return $archive->registrationIssues($identity, completenessRegistrations($db, $archive, $identity));
}

function completenessClassify(array $check, array $copy): string
{
	if (!$check['issues']) return 'original_complete';
	$metadata = array_filter($check['issues'], static fn(string $issue): bool => !str_starts_with($issue, 'file_') && $issue !== 'registration_missing');
	if (!in_array($check['file']['status'], ['ok', 'missing'], true)) return 'damaged_or_unreadable_pdf';
	if ($metadata) return 'invalid_archive_metadata';
	if ($check['file']['status'] === 'ok') return 'original_without_registration';
	if ($copy['status'] === 'available') return 'missing_original_valid_copy';
	if ($copy['status'] === 'not_generated') return 'missing_original_copy_not_generated';
	if (in_array($copy['status'], ['missing', 'unreadable', 'invalid_pdf', 'empty', 'invalid'], true)) return 'damaged_or_unreadable_pdf';
	return 'other_blockers';
}

function completenessRow(Zend_Db_Adapter_Abstract $db, DEEC_DocumentArchive $archive, array $document, string $module, string $controller): array
{
	$identity = ['clientid' => (int)$document['clientid'], 'module' => $module, 'controller' => $controller, 'id' => (int)$document['id'], 'filename' => (string)$document['filename']];
	$issued = DEEC_Permission::isDocumentReadonly($module, $controller, $document);
	$row = ['kind' => 'document', 'clientid' => $identity['clientid'], 'module' => $module, 'controller' => $controller, 'id' => $identity['id'], 'document_number' => (string)($document[$controller . 'id'] ?? ''),
		'issuance_status' => $issued ? (!empty($document['cancelled']) || (int)$document['state'] === 106 ? 'issued_cancelled' : 'issued_or_completed') : 'draft',
		'expected_original_path' => $archive->relativePath($identity['clientid'], $module, $controller, $identity['id']), 'original_status' => 'not_required', 'registration_status' => 'not_required', 'k4_copy_status' => 'not_required',
		'group' => 'draft', 'size' => null, 'sha256' => null, 'reasons' => [], 'copy_details' => null];
	if (!$issued) return $row;
	$check = completenessOriginal($db, $archive, $identity);
	$row['original_status'] = $check['file']['status'];
	$row['size'] = $check['file']['size']; $row['sha256'] = $check['file']['sha256'];
	$row['reasons'] = $check['issues'];
	$registrationIssues = array_values(array_filter($check['issues'], static fn(string $issue): bool => !str_starts_with($issue, 'file_')));
	$row['registration_status'] = !$registrationIssues ? ($row['original_status'] === 'ok' ? 'valid' : 'metadata_consistent_file_unavailable') : (in_array('registrations_ambiguous', $registrationIssues, true) ? 'ambiguous' : ($registrationIssues === ['registration_missing'] ? 'missing' : 'invalid'));
	$copy = ['status' => 'not_checked_original_valid'];
	if ($check['issues']) {
		try { $copy = (new DEEC_DocumentReview())->inspectViewingCopies($identity['id'], $module, $controller); }
		catch (Throwable $error) { $copy = ['status' => 'blocked', 'reason' => 'copy_snapshot_or_access_failed', 'detail' => $error->getMessage()]; }
		$row['copy_details'] = $copy;
		if (!in_array($copy['status'], ['available', 'not_generated'], true)) $row['reasons'][] = 'k4_' . $copy['status'];
		foreach ($copy['copies'] ?? [] as $locale => $found) if (!in_array($found['status'], ['ok', 'not_generated'], true)) $row['reasons'][] = $locale . ':' . $found['reason'];
	}
	$row['k4_copy_status'] = $copy['status'];
	$row['group'] = completenessClassify($check, $copy);
	return $row;
}

function completenessCsv($stream, array $row): void
{
	$values = [];
	foreach (completenessColumns() as $column) {
		$value = $row[$column] ?? '';
		if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		$value = (string)$value;
		// CSV reports can be opened in spreadsheets without executing saved field values.
		if (preg_match('/^[\s]*[=+@\-]/u', $value)) $value = "'" . $value;
		$values[] = $value;
	}
	if (fputcsv($stream, $values, ',', '"', '') === false) throw new RuntimeException('CSV write failed');
}

function completenessColumns(): array
{
	return ['kind', 'clientid', 'module', 'controller', 'id', 'document_number', 'issuance_status', 'expected_original_path', 'original_status', 'registration_status', 'k4_copy_status', 'group', 'size', 'sha256', 'reasons', 'copy_details'];
}

function completenessFiles(string $directory, callable $blocked): Generator
{
	// Visit entries incrementally and never follow a symlink directory.
	try { reviewSafePath($directory); }
	catch (RuntimeException $error) { $blocked($directory, 'scan_symlink'); return; }
	if (!is_readable($directory) || !is_executable($directory)) { $blocked($directory, 'scan_directory_unreadable'); return; }
	try { $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS); }
	catch (UnexpectedValueException $error) { $blocked($directory, 'scan_directory_unreadable'); return; }
	foreach ($iterator as $entry) {
		$path = $entry->getPathname();
		if ($entry->isLink()) {
			if (strtolower($entry->getExtension()) === 'pdf') yield $path;
			else $blocked($path, 'scan_symlink');
		} elseif ($entry->isDir()) yield from completenessFiles($path, $blocked);
		elseif (strtolower($entry->getExtension()) === 'pdf') yield $path;
	}
}

function completenessScan(Zend_Db_Adapter_Abstract $db, DEEC_DocumentArchive $archive, string $base, int $client, array $types, callable $problem, callable $progress): array
{
	$summary = ['pdfs' => 0, 'orphan_pdfs' => 0, 'invalid_registration_pdfs' => 0, 'blockers' => 0];
	foreach ($types as [$module, $controller]) {
		$blocked = static function(string $path, string $reason) use (&$summary, $problem, $base, $client, $module, $controller): void {
			$summary['blockers']++;
			$problem(['kind' => 'filesystem', 'clientid' => $client, 'module' => $module, 'controller' => $controller, 'expected_original_path' => substr($path, strlen($base) + 1), 'group' => 'other_blockers', 'reasons' => [$reason]]);
		};
		$relativeRoot = dirname(dirname($archive->relativePath($client, $module, $controller, 1)));
		$current = $base; $accessible = true;
		foreach (explode('/', $relativeRoot) as $part) {
			$current .= '/' . $part;
			if (is_link($current)) { $blocked($current, 'scan_symlink'); $accessible = false; break; }
			if (!file_exists($current)) { $accessible = false; break; }
			if (!is_dir($current) || !is_readable($current) || !is_executable($current)) { $blocked($current, 'scan_directory_unreadable_or_collision'); $accessible = false; break; }
		}
		if (!$accessible) continue;
		foreach (completenessFiles($current, $blocked) as $path) {
			$summary['pdfs']++;
			$progress('files', $summary['pdfs']);
			$relative = substr($path, strlen($base) + 1); $id = filter_var(pathinfo($path, PATHINFO_FILENAME), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			$reasons = []; $document = null; $check = null;
			if (!$id || $relative !== $archive->relativePath($client, $module, $controller, $id)) $reasons[] = 'noncanonical_pdf_path';
			else {
				$document = $db->fetchRow("SELECT id, clientid, filename, `{$controller}id`, completed, cancelled, state, deleted FROM `$controller` WHERE id = ? AND clientid = ?", [$id, $client]);
				if (!$document || !empty($document['deleted'])) $reasons[] = 'no_active_tenant_document';
				elseif (!DEEC_Permission::isDocumentReadonly($module, $controller, $document)) $reasons[] = 'file_for_draft';
				$identity = ['clientid' => $client, 'module' => $module, 'controller' => $controller, 'id' => $id, 'filename' => (string)($document['filename'] ?? '')];
				$check = completenessOriginal($db, $archive, $identity);
				$reasons = array_merge($reasons, $check['issues']);
			}
			if (!$reasons) continue;
			$orphan = (bool)array_intersect($reasons, ['noncanonical_pdf_path', 'no_active_tenant_document', 'file_for_draft']);
			$summary[$orphan ? 'orphan_pdfs' : 'invalid_registration_pdfs']++;
			$file = $check['file'] ?? $archive->inspect($path, 'pdf');
			$problem(['kind' => 'archive_file', 'clientid' => $client, 'module' => $module, 'controller' => $controller, 'id' => $id ?: '', 'document_number' => (string)($document[$controller . 'id'] ?? ''),
				'expected_original_path' => $relative, 'original_status' => $file['status'], 'group' => $orphan ? 'orphan_pdf' : 'archive_file_problem', 'size' => $file['size'], 'sha256' => $file['sha256'], 'reasons' => $reasons]);
		}
	}
	return $summary;
}

function completenessRequireReader(Zend_Db_Adapter_Abstract $db): void
{
	$grants = $db->fetchCol('SHOW GRANTS FOR CURRENT_USER()');
	if (!$grants) throw new RuntimeException('Cannot verify read-only database grants');
	foreach ($grants as $grant) {
		if (!preg_match('/^GRANT (SELECT|USAGE) ON /', $grant) || str_contains($grant, 'WITH GRANT OPTION')) throw new RuntimeException('Database account must have only SELECT/USAGE grants without roles or grant option');
	}
}

function completenessMain(array $options): int
{
	umask(0077);
	$client = reviewId($options['client'] ?? null); $operator = reviewId($options['operator-user'] ?? null);
	$uid = DEEC_DocumentArchive::requirePublicationUser($options['web-user'] ?? '');
	$types = completenessTypes($options['type'] ?? null);
	$batch = filter_var($options['batch-size'] ?? 250, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
	if (!$batch) throw new RuntimeException('Invalid batch size');
	$base = $options['base'] ?? ''; $output = $options['output'] ?? '';
	reviewSafePath($base); reviewSafePath($output);
	if (realpath($base) !== $base || !is_dir($base)) throw new RuntimeException('Invalid source base');
	if (!defined('BASE_PATH')) define('BASE_PATH', $base);
	DEEC_DocumentReview::privateDirectory(dirname($output));
	if (file_exists($output) || is_link($output)) throw new RuntimeException('Output must be a new private directory');
	DEEC_DocumentReview::privateFile($options['config'] ?? '');
	$settings = migrationConfig($options['config'], $options['environment'] ?? 'production');
	// Enforce a read-only session before the bootstrap performs its first SELECT.
	$settings['driver_options'] = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'];
	$db = null; $documents = null; $problems = null; $summaryFile = null;
	$summary = ['schema_version' => 1, 'mode' => 'read_only', 'complete' => false, 'started_at' => gmdate('c'), 'clientid' => $client, 'php_uid' => $uid, 'database_access' => 'select_only_read_only_session', 'types' => [], 'problems' => 0];
	$lastProgress = 0.0;
	$progress = static function(string $phase, int $count) use (&$lastProgress): void {
		if (microtime(true) - $lastProgress < 2) return;
		$lastProgress = microtime(true);
		fwrite(STDERR, 'Audit progress: ' . $phase . ' checked=' . $count . "\n");
	};
	try {
		$db = recoveryBoot($settings, $base, $client, $operator);
		completenessRequireReader($db);
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		foreach ($types as [$module, $controller]) if (!$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Operator lacks document view permission');
		$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
		$db->beginTransaction();
		if (!mkdir($output, 0700)) throw new RuntimeException('Cannot create private output directory');
		DEEC_DocumentReview::privateDirectory($output);
		$summaryFile = fopen($output . '/summary.json', 'xb');
		$documents = fopen($output . '/documents.csv', 'xb'); $problems = fopen($output . '/problems.csv', 'xb');
		if (!$summaryFile || !$documents || !$problems) throw new RuntimeException('Cannot create private reports');
		foreach ([$documents, $problems] as $stream) if (fputcsv($stream, completenessColumns(), ',', '"', '') === false) throw new RuntimeException('CSV header write failed');
		$problem = static function(array $row) use ($problems, &$summary): void { completenessCsv($problems, $row); $summary['problems']++; };
		$archive = new DEEC_DocumentArchive($base); $checked = 0;
		foreach ($types as $type => [$module, $controller]) {
			$counts = ['non_deleted' => 0, 'issued' => 0, 'drafts' => 0, 'groups' => completenessGroups()]; $after = 0;
			do {
				$rows = $db->fetchAll("SELECT id, clientid, filename, `{$controller}id`, completed, cancelled, state FROM `$controller` WHERE clientid = ? AND deleted = 0 AND id > ? ORDER BY id LIMIT " . $batch, [$client, $after]);
				foreach ($rows as $document) {
					$after = (int)$document['id']; $counts['non_deleted']++;
					$row = completenessRow($db, $archive, $document, $module, $controller);
					if ($row['group'] === 'draft') $counts['drafts']++;
					else { $counts['issued']++; $counts['groups'][$row['group']]++; }
					completenessCsv($documents, $row);
					if ($row['reasons']) $problem($row);
					$progress('documents', ++$checked);
				}
			} while (count($rows) === $batch);
			$counts['status_sum_matches_issued'] = array_sum($counts['groups']) === $counts['issued'];
			if (!$counts['status_sum_matches_issued']) throw new LogicException('Status counts do not reconcile');
			$summary['types'][$type] = $counts;
		}
		$totals = ['non_deleted' => 0, 'issued' => 0, 'drafts' => 0, 'groups' => completenessGroups()];
		foreach ($summary['types'] as $counts) {
			foreach (['non_deleted', 'issued', 'drafts'] as $field) $totals[$field] += $counts[$field];
			foreach ($counts['groups'] as $group => $count) $totals['groups'][$group] += $count;
		}
		$totals['status_sum_matches_issued'] = array_sum($totals['groups']) === $totals['issued'];
		$summary['totals'] = $totals;
		$summary['filesystem'] = completenessScan($db, $archive, $base, $client, $types, $problem, $progress);
		$summary['complete'] = $summary['filesystem']['blockers'] === 0;
		$summary['finished_at'] = gmdate('c');
		$summary['peak_memory_bytes'] = memory_get_peak_usage(true);
		return !$summary['complete'] ? 2 : ($summary['problems'] ? 1 : 0);
	} catch (Throwable $error) {
		$summary['complete'] = false;
		$summary['failure_reason'] = $error->getMessage();
		throw $error;
	} finally {
		if ($db) { try { if ($db->getConnection()->inTransaction()) $db->rollBack(); } finally { $db->closeConnection(); } }
		try {
			foreach ([$documents, $problems] as $stream) if (is_resource($stream) && !fflush($stream)) throw new RuntimeException('Report flush failed');
			if (is_resource($summaryFile)) {
				$json = DEEC_DocumentReview::json($summary);
				if (fwrite($summaryFile, $json) !== strlen($json) || !fflush($summaryFile)) throw new RuntimeException('Summary write failed');
			}
		} finally { foreach ([$documents, $problems, $summaryFile] as $stream) if (is_resource($stream)) fclose($stream); }
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
$options = getopt('', ['config:', 'environment:', 'base:', 'client:', 'operator-user:', 'web-user:', 'output:', 'type:', 'batch-size:', 'help']);
if (isset($options['help'])) {
	echo "Usage: php tools/document-archive-completeness.php --config=/private/reader.ini --base=/var/www/dewawi --client=100 --operator-user=42 --web-user=www-data --output=/private/reports/new-audit [--type=sales/invoice] [--batch-size=250]\nRead-only audit; new private summary.json, documents.csv and problems.csv. Run as the actual non-root PHP account with SELECT-only database credentials. Exit: 0 complete/clean, 1 complete/findings, 2 incomplete/error.\n";
	exit(0);
}
// Suppress PHP diagnostics containing SQL, paths or credentials on the console.
ini_set('display_errors', '0');
set_error_handler(static function(int $severity): bool {
	if (!(error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) return true;
	throw new RuntimeException('Audit operation failed');
});
try {
	$exit = completenessMain($options);
	fwrite(STDERR, 'Audit finished; status=' . $exit . ". Details are in the private reports.\n");
	exit($exit);
} catch (Throwable $error) {
	fwrite(STDERR, "Audit incomplete. Check options, SELECT-only credentials, operator permissions, PHP account and private report paths. Existing reports may be partial; require complete=true.\n");
	exit(2);
}
