<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
require_once $root . '/library/DEEC/Emailattachment.php';
require_once $root . '/library/DEEC/DocumentArchive.php';

$options = getopt('', [
	'config:', 'environment::', 'base:', 'client:', 'contacts-client::', 'contact::', 'type::', 'document-id::',
	'apply', 'confirm-client::', 'backup-verified', 'operator-user::',
]);
$configPath = realpath((string)($options['config'] ?? ''));
$base = realpath((string)($options['base'] ?? ''));
$clientId = filter_var($options['client'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$contactsClientId = filter_var($options['contacts-client'] ?? $clientId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$apply = array_key_exists('apply', $options);
if (!$configPath || !is_file($configPath) || !$base || !is_dir($base) || !$clientId || !$contactsClientId) {
	fwrite(STDERR, "Usage: php tests/documents/document-archive-migration.php --config=/secure/database.ini --base=/path/to/dewawi --client=100 [--contacts-client=100] [--contact=58101] [--type=sales/invoice] [--document-id=123] [--apply --confirm-client=100 --backup-verified --operator-user=1]\n");
	exit(2);
}
$contactFilter = isset($options['contact']) ? filter_var($options['contact'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$documentFilter = isset($options['document-id']) ? filter_var($options['document-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$operatorUser = isset($options['operator-user']) ? filter_var($options['operator-user'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ((isset($options['contact']) && !$contactFilter) || (isset($options['document-id']) && !$documentFilter)) {
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
	$config = parse_ini_file($path, true, INI_SCANNER_RAW);
	if ($config === false) throw new RuntimeException('Database configuration could not be read');
	if (isset($config[$environment]) && is_array($config[$environment])) $config = $config[$environment];
	$keys = ['host' => 'resources.db.params.host', 'username' => 'resources.db.params.username', 'password' => 'resources.db.params.password', 'dbname' => 'resources.db.params.dbname'];
	$result = [];
	foreach ($keys as $name => $key) {
		if (!array_key_exists($key, $config)) throw new RuntimeException('Database configuration is incomplete');
		$result[$name] = (string)$config[$key];
	}
	$result['port'] = (int)($config['resources.db.params.port'] ?? 3306);
	return $result;
}

function migrationRows(mysqli $db, string $module, string $controller, int $clientId, int $contactsClientId, ?int $contactFilter, ?int $documentFilter): array
{
	$number = $controller . 'id';
	$date = $controller . 'date';
	$sql = "SELECT d.id, d.clientid, d.contactid, d.filename, CAST(d.`$number` AS CHAR) AS document_number, d.`$date` AS document_date, d.state, d.completed, d.cancelled, c.id AS contact_record_id, c.matches AS contact_match_count"
		. " FROM `$controller` d LEFT JOIN (SELECT contactid, MIN(id) AS id, COUNT(*) AS matches FROM contact WHERE clientid = ? AND deleted = 0 GROUP BY contactid) c ON c.contactid = d.contactid"
		. " WHERE d.clientid = ? AND d.deleted = 0 AND d.filename IS NOT NULL AND d.filename <> ''"
		. " AND (d.`$number` IS NOT NULL OR d.completed <> 0 OR d.cancelled <> 0 OR d.state IN (105, 106))";
	if ($contactFilter) $sql .= ' AND d.contactid = ?';
	if ($documentFilter) $sql .= ' AND d.id = ?';
	$sql .= ' ORDER BY d.id';
	$stmt = $db->prepare($sql);
	if ($contactFilter && $documentFilter) $stmt->bind_param('iiii', $contactsClientId, $clientId, $contactFilter, $documentFilter);
	elseif ($contactFilter) $stmt->bind_param('iii', $contactsClientId, $clientId, $contactFilter);
	elseif ($documentFilter) $stmt->bind_param('iii', $contactsClientId, $clientId, $documentFilter);
	else $stmt->bind_param('ii', $contactsClientId, $clientId);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($rows as &$row) { $row['module'] = $module; $row['controller'] = $controller; }
	unset($row);
	return $rows;
}

function migrationMetadata(mysqli $db, array $document, string $directory): array
{
	$sql = 'SELECT id, documentid, filename, filetype, filesize, location, module, controller, clientid, deleted FROM emailattachment'
		. ' WHERE (clientid = ? AND documentid = ? AND module = ? AND controller = ? AND filename = ?) OR (location = ? AND filename = ?) ORDER BY deleted, id';
	$stmt = $db->prepare($sql);
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
		$directory = dirname($file['path']);
		$metadata = migrationMetadata($db, $document, $directory);
		$active = array_values(array_filter($metadata, static fn(array $row): bool => empty($row['deleted'])));
		if (count($active) > 1) throw new RuntimeException('Archive metadata is ambiguous');
		if ($active && ((int)$active[0]['clientid'] !== $clientId || (int)$active[0]['documentid'] !== $documentId
			|| (string)$active[0]['module'] !== (string)$document['module'] || (string)$active[0]['controller'] !== (string)$document['controller']
			|| (string)$active[0]['filename'] !== (string)$document['filename'] || rtrim((string)$active[0]['location'], '/') !== $directory)) {
			throw new RuntimeException('Archive metadata differs');
		}
		if (!$active) {
			$ordering = 1;
			$type = 'application/pdf';
			$uploaded = date('Y-m-d H:i:s');
			$filename = (string)$document['filename'];
			$size = (int)$file['size'];
			$module = (string)$document['module'];
			$controller = (string)$document['controller'];
			$insert = $db->prepare('INSERT INTO emailattachment (documentid, filename, filetype, filesize, location, module, controller, ordering, clientid, uploaded, uploadedby, deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
			$insert->bind_param('ississsiisi', $documentId, $filename, $type, $size, $directory, $module, $controller, $ordering, $clientId, $uploaded, $operatorUser);
			$insert->execute();
			$insert->close();
		}
		$metadata = migrationMetadata($db, $document, $directory);
		$active = array_values(array_filter($metadata, static fn(array $row): bool => empty($row['deleted'])));
		if (count($active) !== 1 || (int)$active[0]['clientid'] !== $clientId || (int)$active[0]['documentid'] !== $documentId
			|| (string)$active[0]['module'] !== (string)$document['module'] || (string)$active[0]['controller'] !== (string)$document['controller']
			|| (string)$active[0]['filename'] !== (string)$document['filename'] || rtrim((string)$active[0]['location'], '/') !== $directory
			|| (int)$active[0]['filesize'] !== (int)$file['size']) throw new RuntimeException('Archive metadata verification failed');
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
	'documents' => 0, 'complete' => 0, 'ready' => 0, 'blocked' => 0, 'applied' => 0, 'failed' => 0];

if (!$apply) {
	$db->query('SET TRANSACTION READ ONLY');
	$db->begin_transaction();
}
try {
	foreach ($types as [$module, $controller]) {
		foreach (migrationRows($db, $module, $controller, $clientId, $contactsClientId, $contactFilter, $documentFilter) as $document) {
			$summary['documents']++;
			$paths = null;
			try { $paths = $archive->paths($document); } catch (Throwable $ignored) {}
			$metadata = $paths ? migrationMetadata($db, $document, dirname($paths['canonical'])) : [];
			$plan = $archive->plan($document, $metadata);
			$summary[$plan['status']]++;
			if ($apply && $plan['status'] === 'ready') {
				try {
					$file = $plan['action'] === 'copy_and_register' ? $archive->copy($plan) : $plan['files']['canonical'];
					$registered = migrationRegister($db, $document, $file, $operatorUser);
					$plan['result'] = 'applied';
					$plan['verification'] = [
						'size' => $file['size'], 'sha256' => $file['sha256'], 'attachment_id' => (int)$registered['id'],
						'download_path' => '/files/attachments/' . $document['module'] . '/' . $document['controller'] . '/'
							. substr($file['path'], strlen($base . '/files/attachments/' . $document['module'] . '/' . $document['controller'] . '/')),
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
	if (!$apply) $db->rollback();
} catch (Throwable $exception) {
	if (!$apply) $db->rollback();
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
} finally {
	$db->close();
}
echo json_encode(['kind' => 'summary', 'summary' => $summary], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
exit($summary['failed'] || $summary['blocked'] ? 1 : 0);
