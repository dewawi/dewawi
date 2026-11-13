<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function gobdFixtureIssuerReview($db, int $clientId): string
{
	if (!preg_match('~^/tmp/dewawi-(?:gobd-export|document-recovery)-db-[a-zA-Z0-9_]+/source$~D', BASE_PATH) || realpath(BASE_PATH) !== BASE_PATH) throw new RuntimeException('Synthetic issuer fixture requires an isolated test installation');
	$parent = dirname(BASE_PATH) . '/synthetic-issuer-review';
	if (!is_dir($parent)) mkdir($parent, 0700);
	$client = $db->fetchRow('SELECT * FROM client WHERE id = ?', [$clientId]);
	$decisions = [];
	foreach ($db->fetchAll('SELECT * FROM documentarchive WHERE clientid = ?', [$clientId]) as $archive) {
		$table = $archive['controller'];
		if (!DEEC_DocumentArchive::validDocumentType($archive['module'], $table)) continue;
		$number = $db->fetchOne('SELECT `' . $table . 'id` FROM `' . $table . '` WHERE id = ? AND clientid = ?', [$archive['documentid'], $clientId]);
		$decisions[] = ['identity' => ['clientid' => $clientId, 'module' => $archive['module'], 'controller' => $table, 'id' => (int)$archive['documentid'], 'document_number' => (string)$number], 'pdf_sha256' => $archive['sha256'], 'decision' => 'confirmed_same_issuer', 'reviewed_by' => 'synthetic fixture actor', 'reviewed_at' => gmdate('Y-m-d\TH:i:sP'), 'basis' => 'Synthetic test evidence only; full fixture issuer comparison', 'actual_issuer' => 'SYNTHETIC TEST ISSUER', 'full_document_reviewed' => true, 'issuer_compared_with_tenant' => true];
	}
	$record = ['schema_version' => 1, 'clientid' => $clientId, 'tenant_identity_sha256' => DEEC_Export_GobdIssuer::identityHash($client), 'decisions' => $decisions];
	$path = $parent . '/' . $clientId . '.json';
	file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR));
	chmod($path, 0600);
	putenv('DEWAWI_GOBD_ISSUER_REVIEW_DIR=' . $parent);
	$reports = dirname(BASE_PATH) . '/synthetic-export-reports';
	if (!is_dir($reports)) mkdir($reports, 0700);
	putenv('DEWAWI_GOBD_REPORT_DIR=' . $reports);
	return $path;
}
