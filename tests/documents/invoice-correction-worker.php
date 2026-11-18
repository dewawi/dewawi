<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? ''; $work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-document-integrity-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only disposable correction fixtures are accepted');
require __DIR__ . '/document-recovery.php';
try {
	$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $work . '/source', 7, 42);
	if (($argv[7] ?? '') === 'pdf-failure') { class DEEC_Pdf { public function correction(array $row): string { throw new RuntimeException('Synthetic PDF failure'); } } }
	$row = (new Sales_Service_InvoiceCorrectionService())->approve((int)$argv[2], (int)$argv[3], (int)$argv[4], $argv[5], json_decode($argv[6], true, 512, JSON_THROW_ON_ERROR));
	echo json_encode(['ok' => true, 'id' => $row['id'], 'sha256' => $row['sha256']], JSON_THROW_ON_ERROR);
} catch (Throwable $error) { echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR); }
