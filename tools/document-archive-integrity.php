<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/library/DEEC/UpdateMaintenance.php';
DEEC_UpdateMaintenance::protectInstallation(dirname(__DIR__));

require_once dirname(__DIR__) . '/tests/documents/document-recovery.php';

function integrityTime($value): ?string
{
	if (!is_string($value)) return null;
	$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
	return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
}

function integrityExpectation(array $document, ?string $since): string
{
	$completed = integrityTime($document['completeddate'] ?? null);
	$created = integrityTime($document['created'] ?? null);
	if ($since === null) return 'unknown_expectation';
	if (($completed !== null && $completed >= $since) || ($created !== null && $created >= $since)) return 'current_expectation';
	return $completed !== null ? 'historical' : 'undated_or_unknown';
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

$options = getopt('', ['config:', 'environment:', 'base:', 'client:', 'operator-user:', 'report:', 'required-since:', 'type:', 'limit:', 'after-id:', 'after-archive-id:', 'help']);
if (isset($options['help'])) {
	echo "Usage: php tools/document-archive-integrity.php --config=/secure/read-only.ini --base=/path/to/dewawi --client=100 --operator-user=42 --report=/private/reports/unique.jsonl [--required-since='YYYY-MM-DD HH:MM:SS'] [--type=sales/invoice] [--limit=1000] [--after-id=0] [--after-archive-id=0]\nStrictly read-only database/file inspection; creates only the requested new private report. See tests/documents/document-archive-integrity.md for scheduling and exit codes.\n";
	exit(0);
}
try {
	$clientId = reviewId($options['client'] ?? null);
	$userId = reviewId($options['operator-user'] ?? null);
	$limit = filter_var($options['limit'] ?? 1000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
	$after = filter_var($options['after-id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
	$afterArchive = filter_var($options['after-archive-id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
	if (!$limit || $after === false || $afterArchive === false) throw new RuntimeException('Invalid batch limits');
	$since = $options['required-since'] ?? null;
	if ($since !== null && integrityTime($since) === null) throw new RuntimeException('Invalid required-since timestamp');
	$base = $options['base'] ?? '';
	$report = $options['report'] ?? '';
	reviewSafePath($report);
	if (file_exists($report) || is_link($report)) throw new RuntimeException('Report already exists');
	$db = recoveryBoot(migrationConfig($options['config'] ?? '', $options['environment'] ?? 'production'), $base, $clientId, $userId);
	DEEC_DocumentReview::privateDirectory(dirname($report));
	$archive = new DEEC_DocumentArchive($base);
	$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
	$types = [];
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) $types[$module . '/' . $controller] = [$module, $controller];
	if (isset($options['type'])) {
		if (!isset($types[$options['type']])) throw new RuntimeException('Invalid document type');
		$types = [$options['type'] => $types[$options['type']]];
	}
	foreach ($types as [$module, $controller]) if (!$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Operator lacks document view permission; select an authorized type');
	$summary = ['mode' => 'read_only', 'clientid' => $clientId, 'required_since' => $since, 'documents' => 0, 'registrations' => 0, 'checked' => 0, 'failed' => 0, 'issues' => [], 'expectations' => [], 'complete' => true, 'next_document_ids' => [], 'next_archive_id' => $afterArchive];
	$lines = [];
	$archiveColumns = 'id, documentid, filename, format, path, filesize, sha256, module, controller, version, clientid';
	$record = static function(array $entry) use (&$summary, &$lines): void {
		$summary['checked']++;
		if ($entry['issues']) $summary['failed']++;
		foreach ($entry['issues'] as $issue) $summary['issues'][$issue] = ($summary['issues'][$issue] ?? 0) + 1;
		$expectation = $entry['expectation'];
		$summary['expectations'][$expectation] = ($summary['expectations'][$expectation] ?? 0) + 1;
		$lines[] = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	};
	$db->query('SET TRANSACTION READ ONLY');
	$db->beginTransaction();
	try {
		foreach ($types as $type => [$module, $controller]) {
			$documents = $db->fetchAll("SELECT id, clientid, filename, `{$controller}id` AS document_number, completeddate, created FROM `$controller` WHERE clientid = ? AND deleted = 0 AND id > ? AND (`{$controller}id` <> 0 OR completed <> 0 OR cancelled <> 0 OR state IN (105, 106)) ORDER BY id LIMIT " . ($limit + 1), [$clientId, $after]);
			if (count($documents) > $limit) { $summary['complete'] = false; array_pop($documents); }
			$summary['next_document_ids'][$type] = $after;
			foreach ($documents as $document) {
				$id = (int)$document['id']; $summary['documents']++; $summary['next_document_ids'][$type] = $id;
				$identity = ['clientid' => $clientId, 'module' => $module, 'controller' => $controller, 'id' => $id];
				foreach ($controller === 'invoice' ? ['pdf', 'xml'] : ['pdf'] as $format) {
					$relative = $archive->relativePath($clientId, $module, $controller, $id, $format);
					$registrations = $db->fetchAll('SELECT ' . $archiveColumns . ' FROM documentarchive WHERE (clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ?) OR path = ? ORDER BY id', [$clientId, $module, $controller, $id, $format, $relative]);
					if ($format === 'xml' && !$registrations && !file_exists($base . '/' . $relative) && !is_link($base . '/' . $relative)) continue;
					$check = $archive->registrationIssues($identity + ['filename' => $document['filename']], $registrations, $format);
					$record(['kind' => 'document', 'document' => $identity + ['document_number' => $document['document_number']], 'format' => $format, 'expectation' => integrityExpectation($document, $since), 'timestamps' => array_intersect_key($document, array_flip(['completeddate', 'created']))] + $check);
				}
			}
		}
		$sql = 'SELECT ' . $archiveColumns . ' FROM documentarchive WHERE clientid = ? AND id > ?'; $bind = [$clientId, $afterArchive];
		if (isset($options['type'])) { $sql .= ' AND module = ? AND controller = ?'; $bind = array_merge($bind, reset($types)); }
		$registrations = $db->fetchAll($sql . ' ORDER BY id LIMIT ' . ($limit + 1), $bind);
		if (count($registrations) > $limit) { $summary['complete'] = false; array_pop($registrations); }
		foreach ($registrations as $registration) {
			$summary['registrations']++; $summary['next_archive_id'] = (int)$registration['id'];
			$module = $registration['module']; $controller = $registration['controller']; $format = $registration['format'];
			$issues = []; $document = null;
			if (!DEEC_DocumentArchive::validDocumentType($module, $controller) || !in_array($format, ['pdf', 'xml'], true) || (int)$registration['documentid'] <= 0) $issues[] = 'registration_context_invalid';
			else {
				$owner = $db->fetchOne("SELECT clientid FROM `$controller` WHERE id = ?", [$registration['documentid']]);
				if ($owner === false) $issues[] = 'registered_document_missing';
				elseif ((int)$owner !== $clientId) $issues[] = 'registered_document_tenant_mismatch';
				else {
					$document = $db->fetchRow("SELECT * FROM `$controller` WHERE id = ? AND clientid = ?", [$registration['documentid'], $clientId]);
					if (!DEEC_Permission::isDocumentReadonly($module, $controller, $document)) $issues[] = 'registration_for_draft';
					else {
						$identity = ['clientid' => $clientId, 'module' => $module, 'controller' => $controller, 'id' => (int)$document['id'], 'filename' => $document['filename']];
						$related = $db->fetchAll('SELECT ' . $archiveColumns . ' FROM documentarchive WHERE clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ?', [$clientId, $module, $controller, $document['id'], $format]);
						$issues = $archive->registrationIssues($identity, $related, $format)['issues'];
					}
				}
			}
			$record(['kind' => 'registration', 'archiveid' => (int)$registration['id'], 'module' => $module, 'controller' => $controller, 'documentid' => (int)$registration['documentid'], 'format' => $format, 'expectation' => integrityExpectation($document ?: [], $since), 'issues' => $issues]);
		}
	} finally { $db->rollBack(); $db->closeConnection(); }
	$lines[] = json_encode(['kind' => 'summary', 'summary' => $summary], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	reviewWrite($report, implode("\n", $lines) . "\n");
	echo json_encode($summary + ['report' => $report], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	exit($summary['failed'] ? 1 : ($summary['complete'] ? 0 : 3));
} catch (Throwable $error) {
	$detail = in_array(get_class($error), [RuntimeException::class, InvalidArgumentException::class], true) ? ': ' . $error->getMessage() : '';
	fwrite(STDERR, 'Archive integrity check could not complete: ' . get_class($error) . $detail . '. Check arguments, operator permissions, read-only configuration and private report directory.' . "\n");
	exit(2);
}
