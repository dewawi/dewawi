<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$repository = dirname(__DIR__, 2);
set_include_path($repository . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function ($class) { $file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php'); if ($file) require_once $file; });
umask(0077);
putenv('DEWAWI_GOBD_ISSUER_REVIEW_DIR');
$work = sys_get_temp_dir() . '/dewawi-issuer-unit-' . bin2hex(random_bytes(8));
mkdir($work, 0700);
mkdir($work . '/installation', 0700);
define('BASE_PATH', $work . '/installation');
$checks = 0;
function issuerCheck(bool $condition): void { global $checks; if (!$condition) throw new RuntimeException('Synthetic issuer assertion failed'); $checks++; }
function issuerReject(callable $operation): void { try { $operation(); } catch (RuntimeException | InvalidArgumentException $expected) { issuerCheck(true); return; } throw new RuntimeException('Unsafe issuer evidence was accepted'); }
$client = ['id' => 5, 'company' => 'SYNTHETIC ISSUER A', 'address' => 'SYNTHETIC ADDRESS', 'postcode' => '00000', 'city' => 'SYNTHETIC CITY', 'country' => 'XX'];
$path = $work . '/issuer.json';
$record = ['schema_version' => 1, 'clientid' => $client['id'], 'tenant_identity_sha256' => DEEC_Export_GobdIssuer::identityHash($client), 'decisions' => []];
try {
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
		$identity = ['clientid' => $client['id'], 'module' => $module, 'controller' => $controller, 'id' => 9, 'document_number' => 'SYNTHETIC-NUMBER'];
		$sha = hash('sha256', $module . '/' . $controller);
		issuerCheck((new DEEC_Export_GobdIssuer($client, null))->check($identity, $sha)['status'] === 'review_required');
		$record['decisions'][] = ['identity' => $identity, 'pdf_sha256' => $sha, 'decision' => 'confirmed_same_issuer', 'reviewed_by' => 'synthetic fixture actor', 'reviewed_at' => gmdate('Y-m-d\TH:i:sP'), 'basis' => 'Synthetic full-document comparison with independent issuer evidence', 'actual_issuer' => $client['company'], 'full_document_reviewed' => true, 'issuer_compared_with_tenant' => true];
	}
	file_put_contents($path, json_encode($record));
	$review = new DEEC_Export_GobdIssuer($client, $path);
	foreach ($record['decisions'] as $decision) {
		issuerCheck($review->check($decision['identity'], $decision['pdf_sha256'])['status'] === 'manually_verified');
		issuerCheck($review->check($decision['identity'], hash('sha256', 'changed PDF'))['status'] === 'review_required');
		issuerCheck($review->check(array_replace($decision['identity'], ['document_number' => 'OTHER-SYNTHETIC-NUMBER']), $decision['pdf_sha256'])['status'] === 'review_required');
	}
	$numeric = $record;
	$numeric['decisions'][0]['identity']['document_number'] = '007';
	file_put_contents($path, json_encode($numeric));
	$exactIdentity = $numeric['decisions'][0]['identity'];
	$numericReview = new DEEC_Export_GobdIssuer($client, $path);
	issuerCheck($numericReview->check(array_replace($exactIdentity, ['document_number' => '7']), $numeric['decisions'][0]['pdf_sha256'])['status'] === 'review_required');
	issuerCheck($numericReview->check(array_reverse($exactIdentity, true), $numeric['decisions'][0]['pdf_sha256'])['status'] === 'manually_verified');
	$foreign = $record;
	$foreign['decisions'][0]['decision'] = 'confirmed_foreign_issuer';
	$foreign['decisions'][0]['actual_issuer'] = 'SYNTHETIC ISSUER B';
	file_put_contents($path, json_encode($foreign));
	issuerCheck((new DEEC_Export_GobdIssuer($client, $path))->check($foreign['decisions'][0]['identity'], $foreign['decisions'][0]['pdf_sha256'])['status'] === 'conflict');
	foreach (['clientid', 'tenant_identity_sha256', 'reviewed_by', 'reviewed_at', 'basis', 'actual_issuer', 'full_document_reviewed', 'issuer_compared_with_tenant', 'duplicate'] as $invalid) {
		$bad = $record;
		if ($invalid === 'clientid') $bad['clientid']++;
		elseif ($invalid === 'tenant_identity_sha256') $bad[$invalid] = str_repeat('0', 64);
		elseif ($invalid === 'duplicate') $bad['decisions'][] = $bad['decisions'][0];
		else $bad['decisions'][0][$invalid] = in_array($invalid, ['full_document_reviewed', 'issuer_compared_with_tenant'], true) ? false : '';
		file_put_contents($path, json_encode($bad));
		issuerReject(static fn() => new DEEC_Export_GobdIssuer($client, $path));
	}
	file_put_contents($path, json_encode($record));
	issuerReject(static fn() => new DEEC_Export_GobdIssuer(array_replace($client, ['company' => 'SYNTHETIC ISSUER CHANGED']), $path));
	chmod($path, 0644);
	issuerReject(static fn() => new DEEC_Export_GobdIssuer($client, $path));
	chmod($path, 0600);
	symlink($path, $work . '/link.json');
	issuerReject(static fn() => new DEEC_Export_GobdIssuer($client, $work . '/link.json'));
	unlink($work . '/link.json');
	file_put_contents(BASE_PATH . '/issuer.json', json_encode($record));
	issuerReject(static fn() => new DEEC_Export_GobdIssuer($client, BASE_PATH . '/issuer.json'));
	unlink(BASE_PATH . '/issuer.json');
	echo 'PASS: ' . $checks . " synthetic issuer checks; eight types, separate identity, exact SHA, manual evidence, conflicts and private paths\n";
} finally {
	unlink($path);
	rmdir(BASE_PATH);
	rmdir($work);
}
