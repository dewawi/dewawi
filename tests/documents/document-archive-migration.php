<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
require_once $root . '/library/DEEC/Emailattachment.php';
require_once $root . '/library/DEEC/DocumentArchive.php';

$options = getopt('', [
	'config:', 'environment::', 'base:', 'client:', 'contacts-client::', 'contact::', 'type::', 'document-id::',
	'document-number::',
	'apply', 'confirm-client::', 'backup-verified', 'operator-user::',
]);
$configPath = realpath((string)($options['config'] ?? ''));
$base = realpath((string)($options['base'] ?? ''));
$clientId = filter_var($options['client'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$contactsClientId = filter_var($options['contacts-client'] ?? $clientId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$apply = array_key_exists('apply', $options);
if (!$configPath || !is_file($configPath) || !$base || !is_dir($base) || !$clientId || !$contactsClientId) {
	fwrite(STDERR, "Usage: php tests/documents/document-archive-migration.php --config=/secure/database.ini --base=/path/to/dewawi --client=100 [--contacts-client=100] [--contact=58101] [--type=sales/invoice] [--document-id=59296] [--document-number=112143] [--apply --confirm-client=100 --backup-verified --operator-user=1]\n--document-id is the internal database ID; --document-number is the visible document number. Dry-run JSONL is the default.\n");
	exit(2);
}
$contactFilter = isset($options['contact']) ? filter_var($options['contact'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$documentFilter = isset($options['document-id']) ? filter_var($options['document-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$documentNumberFilter = isset($options['document-number']) ? trim((string)$options['document-number']) : null;
$operatorUser = isset($options['operator-user']) ? filter_var($options['operator-user'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ((isset($options['contact']) && !$contactFilter) || (isset($options['document-id']) && !$documentFilter) || ($documentNumberFilter !== null && ($documentNumberFilter === '' || strlen($documentNumberFilter) > 255))) {
	fwrite(STDERR, "Invalid numeric filter.\n");
	exit(2);
}
if ($apply && ((int)($options['confirm-client'] ?? 0) !== (int)$clientId || !array_key_exists('backup-verified', $options) || !$operatorUser)) {
	fwrite(STDERR, "Apply requires --confirm-client matching --client, --backup-verified and --operator-user.\n");
	exit(2);
}

$types = [];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) {
	foreach ($controllers as $controller) $types[$module . '/' . $controller] = [$module, $controller];
}
if (isset($options['type'])) {
	$type = (string)$options['type'];
	if (!isset($types[$type])) {
		fwrite(STDERR, "Invalid document type.\n");
		exit(2);
	}
	$types = [$type => $types[$type]];
}

function migrationConfig(string $path, string $environment): array
{
	$ini = @file_get_contents($path);
	if ($ini === false || @parse_ini_string($ini, true, INI_SCANNER_NORMAL) === false) throw new RuntimeException('Database configuration could not be read');
	// RAW preserves literal credentials, but only removes double-quote delimiters.
	$ini = preg_replace_callback("/^([ \t]*[^=;\r\n]+=[ \t]*)'([^']*)'([ \t]*(?:;[^\r\n]*)?\r?)$/m", static function(array $match): string {
		return $match[1] . '"' . $match[2] . '"' . $match[3];
	}, $ini);
	$config = @parse_ini_string($ini, true, INI_SCANNER_RAW);
	if ($config === false) throw new RuntimeException('Database configuration could not be read');
	if (isset($config[$environment]) && is_array($config[$environment])) $config = $config[$environment];
	$keys = ['host' => 'resources.db.params.host', 'username' => 'resources.db.params.username', 'password' => 'resources.db.params.password', 'dbname' => 'resources.db.params.dbname'];
	$result = [];
	foreach ($keys as $name => $key) {
		if (!array_key_exists($key, $config) || !is_string($config[$key])) throw new RuntimeException('Database configuration is incomplete');
		$result[$name] = $config[$key];
	}
	$port = $config['resources.db.params.port'] ?? '3306';
	if (!is_string($port) || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) throw new RuntimeException('Database configuration port is invalid');
	$result['port'] = (int)$port;
	return $result;
}

function migrationRows(mysqli $db, string $module, string $controller, int $clientId, int $contactsClientId, ?int $contactFilter, ?int $documentFilter, ?string $documentNumberFilter): array
{
	$number = $controller . 'id';
	$date = $controller . 'date';
	$sql = "SELECT d.id, d.clientid, d.contactid, d.filename, CAST(d.`$number` AS CHAR) AS document_number, d.`$date` AS document_date, d.state, d.completed, d.cancelled, c.id AS contact_record_id, c.matches AS contact_match_count"
		. " FROM `$controller` d LEFT JOIN (SELECT contactid, MIN(id) AS id, COUNT(*) AS matches FROM contact WHERE clientid = ? AND deleted = 0 GROUP BY contactid) c ON c.contactid = d.contactid"
		. " WHERE d.clientid = ? AND d.deleted = 0 AND d.filename IS NOT NULL AND d.filename <> ''"
		. " AND (d.`$number` IS NOT NULL OR d.completed <> 0 OR d.cancelled <> 0 OR d.state IN (105, 106))";
	if ($contactFilter) $sql .= ' AND d.contactid = ?';
	if ($documentFilter) $sql .= ' AND d.id = ?';
	if ($documentNumberFilter !== null) $sql .= " AND CAST(d.`$number` AS CHAR) = ?";
	$sql .= ' ORDER BY d.id';
	$stmt = $db->prepare($sql);
	$bindTypes = 'ii';
	$bind = [$contactsClientId, $clientId];
	if ($contactFilter) { $bindTypes .= 'i'; $bind[] = $contactFilter; }
	if ($documentFilter) { $bindTypes .= 'i'; $bind[] = $documentFilter; }
	if ($documentNumberFilter !== null) { $bindTypes .= 's'; $bind[] = $documentNumberFilter; }
	$stmt->bind_param($bindTypes, ...$bind);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($rows as &$row) { $row['module'] = $module; $row['controller'] = $controller; }
	unset($row);
	return $rows;
}

function migrationMetadata(mysqli $db, array $document, string $path): array
{
	$sql = 'SELECT id, documentid, filename, format, path, filesize, sha256, module, controller, version, correctionarchiveid, clientid FROM documentarchive'
		. ' WHERE (clientid = ? AND documentid = ? AND module = ? AND controller = ? AND format = ?) OR path = ? ORDER BY id';
	$stmt = $db->prepare($sql);
	$clientId = (int)$document['clientid'];
	$documentId = (int)$document['id'];
	$module = (string)$document['module'];
	$controller = (string)$document['controller'];
	$format = (string)($document['format'] ?? 'pdf');
	$stmt->bind_param('iissss', $clientId, $documentId, $module, $controller, $format, $path);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $rows;
}

function migrationLegacyAttachmentMetadata(mysqli $db, array $document, string $directory): array
{
	$stmt = $db->prepare('SELECT id, documentid, filename, filetype, filesize, location, module, controller, clientid, deleted FROM emailattachment WHERE (clientid = ? AND documentid = ? AND module = ? AND controller = ? AND filename = ?) OR (location = ? AND filename = ?) ORDER BY deleted, id');
	$clientId = (int)$document['clientid'];
	$documentId = (int)$document['id'];
	$module = (string)$document['module'];
	$controller = (string)$document['controller'];
	$filename = (string)$document['filename'];
	$stmt->bind_param('iisssss', $clientId, $documentId, $module, $controller, $filename, $directory, $filename);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $rows;
}

function migrationRegister(mysqli $db, array $document, array $file, int $operatorUser): array
{
	$table = $document['controller'];
	$db->begin_transaction();
	try {
		$lock = $db->prepare("SELECT id FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0 FOR UPDATE");
		$documentId = (int)$document['id'];
		$clientId = (int)$document['clientid'];
		$lock->bind_param('ii', $documentId, $clientId);
		$lock->execute();
		if ($lock->get_result()->num_rows !== 1) throw new RuntimeException('Document changed or is unavailable');
		$lock->close();
		$relative = substr($file['path'], strlen(realpath((string)$GLOBALS['base'])) + 1);
		$metadata = migrationMetadata($db, $document, $relative);
		$active = $metadata;
		if (count($active) > 1) throw new RuntimeException('Archive metadata is ambiguous');
		if ($active && ((int)$active[0]['clientid'] !== $clientId || (int)$active[0]['documentid'] !== $documentId
			|| (string)$active[0]['module'] !== (string)$document['module'] || (string)$active[0]['controller'] !== (string)$document['controller']
			|| (string)$active[0]['filename'] !== (string)$document['filename'] || (string)$active[0]['path'] !== $relative
			|| (string)$active[0]['sha256'] !== (string)$file['sha256'])) {
			throw new RuntimeException('Archive metadata differs');
		}
		if (!$active) {
			$archived = date('Y-m-d H:i:s');
			$filename = (string)$document['filename'];
			$size = (int)$file['size'];
			$module = (string)$document['module'];
			$controller = (string)$document['controller'];
			$format = (string)($document['format'] ?? 'pdf');
			$sha = (string)$file['sha256'];
			$version = 1;
			$insert = $db->prepare('INSERT INTO documentarchive (documentid, filename, format, path, filesize, sha256, module, controller, version, correctionarchiveid, parameters, clientid, archived, archivedby) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?)');
			$insert->bind_param('isssisssiisi', $documentId, $filename, $format, $relative, $size, $sha, $module, $controller, $version, $clientId, $archived, $operatorUser);
			$insert->execute();
			$insert->close();
		}
		$metadata = migrationMetadata($db, $document, $relative);
		$active = $metadata;
		if (count($active) !== 1 || (int)$active[0]['clientid'] !== $clientId || (int)$active[0]['documentid'] !== $documentId
			|| (string)$active[0]['module'] !== (string)$document['module'] || (string)$active[0]['controller'] !== (string)$document['controller']
			|| (string)$active[0]['filename'] !== (string)$document['filename'] || (string)$active[0]['path'] !== $relative
			|| (int)$active[0]['filesize'] !== (int)$file['size'] || !hash_equals((string)$active[0]['sha256'], (string)$file['sha256'])) throw new RuntimeException('Archive metadata verification failed');
		$db->commit();
		return $active[0];
	} catch (Throwable $exception) {
		$db->rollback();
		throw $exception;
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$settings = migrationConfig($configPath, (string)($options['environment'] ?? 'production'));
$db = new mysqli($settings['host'], $settings['username'], $settings['password'], $settings['dbname'], $settings['port']);
$db->set_charset('utf8mb4');
$archive = new DEEC_DocumentArchive($base);
$summary = ['mode' => $apply ? 'apply' : 'dry-run', 'clientid' => (int)$clientId, 'contacts_clientid' => (int)$contactsClientId,
	'documents' => 0, 'complete' => 0, 'ready' => 0, 'blocked' => 0, 'applied' => 0, 'failed' => 0, 'unassigned' => 0];
$summary['structured_originals'] = 0;
$summary['structured_blocked'] = 0;
$fullScan = !$contactFilter && !$documentFilter && $documentNumberFilter === null && !isset($options['type']);
$summary['unassigned_scan'] = $fullScan ? 'complete' : 'skipped_for_filtered_run';
$assignedPaths = [];
$selectedDocuments = [];

if (!$apply) {
	$db->query('SET TRANSACTION READ ONLY');
	$db->begin_transaction();
}
try {
	foreach ($types as [$module, $controller]) {
		foreach (migrationRows($db, $module, $controller, $clientId, $contactsClientId, $contactFilter, $documentFilter, $documentNumberFilter) as $document) {
			$document['format'] = 'pdf';
			$selectedDocuments[$module . '/' . $controller . '/' . (int)$document['id']] = true;
			$summary['documents']++;
			$paths = null;
			try { $paths = $archive->paths($document); } catch (Throwable $ignored) {}
			if ($paths) foreach ($paths as $path) if ($path !== '') $assignedPaths[$path] = true;
			$metadata = $paths ? migrationMetadata($db, $document, substr($paths['canonical'], strlen($base) + 1)) : [];
			$plan = $archive->plan($document, $metadata);
			$legacyMetadata = $paths ? migrationLegacyAttachmentMetadata($db, $document, dirname($paths['attachment'])) : [];
			$activeLegacy = array_values(array_filter($legacyMetadata, static fn(array $row): bool => empty($row['deleted'])));
			$legacyConflicts = array_values(array_filter($activeLegacy, static function(array $row) use ($document, $paths): bool {
				return (int)$row['clientid'] !== (int)$document['clientid'] || (int)$row['documentid'] !== (int)$document['id']
					|| $row['module'] !== $document['module'] || $row['controller'] !== $document['controller'] || $row['filename'] !== $document['filename']
					|| rtrim((string)$row['location'], '/') !== dirname($paths['attachment']);
			}));
			$plan['legacy_attachment_metadata'] = ['active' => count($activeLegacy), 'deleted' => count($legacyMetadata) - count($activeLegacy),
				'conflicts' => count($legacyConflicts), 'ids' => array_map('intval', array_column($activeLegacy, 'id'))];
			if ($legacyConflicts && $plan['status'] !== 'blocked') { $plan['status'] = 'blocked'; $plan['action'] = 'none'; $plan['reason'] = 'legacy_attachment_metadata_conflict'; }
			$summary[$plan['status']]++;
			if ($apply && $plan['status'] === 'ready') {
				try {
					$file = $plan['action'] === 'copy_and_register' ? $archive->copy($plan) : $plan['files']['canonical'];
					$registered = migrationRegister($db, $document, $file, $operatorUser);
					$plan['result'] = 'applied';
					$plan['verification'] = [
						'size' => $file['size'], 'sha256' => $file['sha256'], 'archive_id' => (int)$registered['id'],
						'download_path' => '/' . $document['module'] . '/' . $document['controller'] . '/download/id/' . (int)$document['id'],
					];
					$summary['applied']++;
				} catch (Throwable $exception) {
					$plan['result'] = 'failed';
					$plan['error'] = $exception->getMessage();
					$summary['failed']++;
				}
			} else {
				$plan['result'] = $apply ? ($plan['status'] === 'complete' ? 'unchanged' : 'blocked') : 'planned';
			}
			echo json_encode(['kind' => 'document', 'plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		}
	}
	$xmlStatement = $db->prepare('SELECT id, documentid, filename, format, path, filesize, sha256, module, controller, version, clientid, archived FROM documentarchive WHERE clientid = ? AND format = ? ORDER BY module, controller, documentid, version');
	$xmlFormat = 'xml';
	$xmlStatement->bind_param('is', $clientId, $xmlFormat);
	$xmlStatement->execute();
	foreach ($xmlStatement->get_result()->fetch_all(MYSQLI_ASSOC) as $metadata) {
		if (!$fullScan && !isset($selectedDocuments[$metadata['module'] . '/' . $metadata['controller'] . '/' . (int)$metadata['documentid']])) continue;
		$summary['structured_originals']++;
		$record = ['metadata' => $metadata, 'status' => 'blocked', 'reason' => '', 'file' => null];
		if (!DEEC_DocumentArchive::validDocumentType((string)$metadata['module'], (string)$metadata['controller']) || (int)$metadata['version'] !== 1) {
			$record['reason'] = 'invalid_metadata_context';
		} else {
			$expected = $archive->relativePath((int)$metadata['clientid'], (string)$metadata['module'], (string)$metadata['controller'], (int)$metadata['documentid'], 'xml');
			$path = $base . '/' . $expected;
			$assignedPaths[$path] = true;
			$record['file'] = $archive->inspect($path, 'xml');
			$table = (string)$metadata['controller'];
			$documentStatement = $db->prepare("SELECT id FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0");
			$metadataDocumentId = (int)$metadata['documentid'];
			$metadataClientId = (int)$metadata['clientid'];
			$documentStatement->bind_param('ii', $metadataDocumentId, $metadataClientId);
			$documentStatement->execute();
			$documentExists = $documentStatement->get_result()->num_rows === 1;
			$documentStatement->close();
			if (!$documentExists) $record['reason'] = 'document_missing';
			elseif ($metadata['path'] !== $expected) $record['reason'] = 'metadata_path_differs';
			elseif ($record['file']['status'] !== 'ok') $record['reason'] = 'structured_original_invalid';
			elseif ((int)$metadata['filesize'] !== (int)$record['file']['size'] || !hash_equals((string)$metadata['sha256'], (string)$record['file']['sha256'])) $record['reason'] = 'structured_original_integrity_differs';
			else $record['status'] = 'complete';
		}
		if ($record['status'] !== 'complete') $summary['structured_blocked']++;
		echo json_encode(['kind' => 'structured_original', 'record' => $record], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
	}
	$xmlStatement->close();
	if ($fullScan) {
		$clientValue = (string)$clientId;
		$clientShard = $clientValue[0] . '/' . ($clientValue[1] ?? '0') . '/' . $clientValue;
		$scanRoots = [
			'canonical' => $base . '/files/documents/' . $clientShard,
			'contact' => $base . '/files/contacts/' . $clientShard,
		];
		foreach ($types as [$module, $controller]) $scanRoots['attachment:' . $module . '/' . $controller] = $base . '/files/attachments/' . $module . '/' . $controller . '/' . $clientShard;
		foreach ($scanRoots as $location => $rootPath) {
			if (is_link($rootPath)) {
				$summary['unassigned']++;
				echo json_encode(['kind' => 'unassigned_file', 'location' => $location, 'classification' => 'symlink_root', 'file' => $archive->inspect($rootPath, 'pdf')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
				continue;
			}
			if (!is_dir($rootPath)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $entry) {
				$path = $entry->getPathname();
				if ((!$entry->isFile() && !$entry->isLink()) || isset($assignedPaths[$path])) continue;
				$summary['unassigned']++;
				$format = strtolower(pathinfo($path, PATHINFO_EXTENSION));
				$file = $entry->isLink() || in_array($format, ['pdf', 'xml'], true) ? $archive->inspect($path, $format === 'xml' ? 'xml' : 'pdf') : ['path' => $path, 'status' => 'non_document', 'size' => $entry->getSize(), 'sha256' => hash_file('sha256', $path)];
				echo json_encode(['kind' => 'unassigned_file', 'location' => $location, 'classification' => $location === 'contact' ? 'manual_or_unassigned_contact_file' : 'unassigned_archive_candidate', 'file' => $file], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
			}
		}
	}
	if (!$apply) $db->rollback();
} catch (Throwable $exception) {
	if (!$apply) $db->rollback();
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
} finally {
	$db->close();
}
echo json_encode(['kind' => 'summary', 'summary' => $summary], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
exit($summary['failed'] || $summary['blocked'] || $summary['structured_blocked'] ? 1 : 0);
