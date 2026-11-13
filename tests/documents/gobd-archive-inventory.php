<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/document-recovery.php';

function gobdArchiveInventory($db, int $clientId, int $userId, string $output, ?string $from = null, ?string $to = null): array
{
	DEEC_Export_Gobd::assertDocumentAccess($clientId, $userId);
	DEEC_Export_Gobd::validatePeriod($from, $to);
	DEEC_DocumentReview::privateDirectory(dirname($output));
	if (file_exists($output) || is_link($output)) throw new RuntimeException('Inventory output exists');
	$handle = fopen($output, 'xb');
	if (!$handle || !chmod($output, 0600)) throw new RuntimeException('Inventory output is unavailable');
	$hash = hash_init('sha256'); $counts = ['registered' => 0, 'valid' => 0, 'invalid' => 0];
	$write = static function(array $row) use ($handle, $hash): void {
		$line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
		if (fwrite($handle, $line) !== strlen($line)) throw new RuntimeException('Inventory write failed');
		hash_update($hash, $line);
	};
	$transaction = false;
	try {
		if ($db->getConnection()->inTransaction()) throw new RuntimeException('Inventory requires its own read-only snapshot');
		$db->query('SET TRANSACTION READ ONLY'); $db->beginTransaction(); $transaction = true;
		$client = $db->fetchRow('SELECT * FROM client WHERE id = ?', [$clientId]);
		$write(['kind' => 'header', 'schema_version' => 1, 'clientid' => $clientId, 'tenant_identity_sha256' => DEEC_Export_GobdIssuer::identityHash($client), 'base' => BASE_PATH, 'from' => $from, 'to' => $to, 'created_by' => $userId, 'created_at' => gmdate('c'), 'mode' => 'registered_archive_read_only']);
		$archiveDb = new Application_Model_DbTable_Documentarchive();
		foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
			$sql = 'SELECT DISTINCT d.id, d.' . $controller . 'id AS document_number, d.' . $controller . 'date AS document_date FROM `' . $controller . '` d JOIN documentarchive a ON a.documentid = d.id AND a.clientid = d.clientid AND a.module = ? AND a.controller = ? AND a.format = ? WHERE d.clientid = ?';
			$params = [$module, $controller, 'pdf', $clientId];
			if ($from !== null) { $sql .= ' AND d.' . $controller . 'date >= ? AND d.' . $controller . 'date <= ?'; $params[] = $from; $params[] = $to; }
			$query = $db->query($sql . ' ORDER BY d.id', $params);
			while ($document = $query->fetch(Zend_Db::FETCH_ASSOC)) {
				$counts['registered']++;
				$identity = ['clientid' => $clientId, 'module' => $module, 'controller' => $controller, 'id' => (int)$document['id'], 'document_number' => (string)$document['document_number']];
				try {
					$original = $archiveDb->getForExport($identity['id'], $module, $controller);
					if (!$original) throw new RuntimeException('Registered document is not technically readonly');
					$write(['kind' => 'archive', 'identity' => $identity, 'document_date' => $document['document_date'], 'pdf' => ['path' => BASE_PATH . '/' . $original['path'], 'size' => (int)$original['filesize'], 'sha256' => $original['sha256']], 'pdf_origin' => DEEC_DocumentArchive::pdfProvenance($original)]);
					$counts['valid']++;
				} catch (RuntimeException | InvalidArgumentException $error) {
					$write(['kind' => 'invalid_archive', 'identity' => $identity, 'reason' => $error->getMessage()]); $counts['invalid']++;
				}
			}
		}
		$db->rollBack(); $transaction = false;
		$line = json_encode(['kind' => 'summary', 'counts' => $counts, 'content_sha256' => hash_final($hash)], JSON_THROW_ON_ERROR) . "\n";
		if (fwrite($handle, $line) !== strlen($line) || !fflush($handle)) throw new RuntimeException('Inventory summary write failed');
		return $counts;
	} finally {
		fclose($handle);
		if ($transaction) $db->rollBack();
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
$options = getopt('', ['config:', 'environment:', 'base:', 'client:', 'operator-user:', 'from:', 'to:', 'output:']);
$db = null;
try {
	foreach (['config', 'base', 'client', 'operator-user', 'output'] as $field) if (!is_string($options[$field] ?? null)) throw new RuntimeException('Required inventory arguments are missing');
	$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $options['base'], reviewId($options['client']), reviewId($options['operator-user']));
	gobdArchiveInventory($db, reviewId($options['client']), reviewId($options['operator-user']), $options['output'], $options['from'] ?? null, $options['to'] ?? null);
	echo "Private archive inventory written; no business data or archive registration changed.\n";
	$exit = 0;
} catch (Throwable $error) { fwrite(STDERR, "Archive inventory failed; check private configuration and permissions.\n"); $exit = 2; }
finally { if ($db) $db->closeConnection(); }
exit($exit);
