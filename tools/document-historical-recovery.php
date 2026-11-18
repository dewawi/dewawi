<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/document-recovery-assessment.php';

const HISTORICAL_FILE_RULE = 'attachment-legacy-sha256-v1';

function historicalRecoveryRow(Zend_Db_Adapter_Abstract $db, array $identity, string $extractor, bool $lock = false): array
{
	$row = ['identity' => $identity, 'action' => 'excluded', 'reason' => '', 'checks' => [], 'files' => [], 'original_provenance_verified' => false];
	try {
		$snapshot = recoverySnapshot($db, $identity, $lock); $document = $snapshot['data']['document'];
		$row['snapshot_sha256'] = $snapshot['sha256']; $row['checks']['identity_and_tenant'] = true;
		if ($identity['document_number'] === '' || !DEEC_Emailattachment::validFilename((string)$document['filename']) || strtolower(pathinfo($document['filename'], PATHINFO_EXTENSION)) !== 'pdf') throw new RuntimeException('Missing document number or valid PDF filename');
		$table = $identity['controller'];
		if ((int)$db->fetchOne("SELECT COUNT(*) FROM `$table` WHERE clientid = ? AND `{$table}id` = ? AND deleted = 0", [$identity['clientid'], $identity['document_number']]) !== 1) throw new RuntimeException('Ambiguous visible document number');
		if ((int)($document['contactid'] ?? 0) <= 0) throw new RuntimeException('Stored document contact association missing');
		$contactsOwner = (int)Zend_Registry::get('Client')['modules']['contacts'];
		$contacts = $db->fetchCol('SELECT id FROM contact WHERE clientid = ? AND contactid = ? AND deleted = 0 ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), [$contactsOwner, $document['contactid']]);
		$row['contacts_clientid'] = $contactsOwner; $row['contact_record_ids'] = array_map('intval', $contacts);
		if (count($contacts) !== 1) throw new RuntimeException('Contact mapping missing or ambiguous');
		$row['checks']['contact_mapping'] = true;
		$archive = new DEEC_DocumentArchive(BASE_PATH);
		$paths = $archive->paths($document + ['module' => $identity['module'], 'controller' => $table, 'contact_record_id' => (int)$contacts[0]]);
		foreach ($paths as $name => $path) $row['files'][$name] = $archive->inspect($path, 'pdf');
		$a = $row['files']['attachment']; $b = $row['files']['legacy'];
		if ($a['status'] !== 'ok' || $b['status'] !== 'ok' || !is_int($a['size']) || $a['size'] <= 0 || $a['size'] !== $b['size'] || !preg_match('/^[a-f0-9]{64}$/D', $a['sha256'] ?? '') || !hash_equals($a['sha256'], (string)$b['sha256'])) throw new RuntimeException('Attachment and legacy PDFs are missing, invalid or differ');
		$row['checks']['matching_readable_files'] = true;
		$row['current_version_differs'] = $row['files']['current']['status'] === 'ok' && $row['files']['current']['sha256'] !== $a['sha256'];
		$attachments = $db->fetchAll('SELECT id, clientid, documentid, module, controller, filename, location, filesize, filetype, deleted FROM emailattachment WHERE deleted = 0 AND ((clientid = ? AND documentid = ? AND module = ? AND controller = ? AND filename = ?) OR (location = ? AND filename = ?)) ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), [$identity['clientid'], $identity['id'], $identity['module'], $table, $document['filename'], dirname($a['path']), $document['filename']]);
		$row['attachments'] = $attachments;
		if (!$attachments) throw new RuntimeException('Expected document attachment association missing');
		foreach ($attachments as $attachment) if ((int)$attachment['clientid'] !== $identity['clientid'] || (int)$attachment['documentid'] !== $identity['id'] || $attachment['module'] !== $identity['module'] || $attachment['controller'] !== $table || $attachment['filename'] !== $document['filename'] || rtrim($attachment['location'], '/') !== dirname($a['path']) || (int)$attachment['filesize'] !== $a['size'] || !in_array(strtolower($attachment['filetype']), ['pdf', 'application/pdf'], true)) throw new RuntimeException('Document attachment metadata conflicts with the actual PDF');
		$row['checks']['document_attachment_association'] = true;
		$extracted = assessmentExtract($a['path'], $extractor, $a['sha256']);
		$row['pdf_validation'] = ['status' => $extracted['status'], 'diagnostic' => $extracted['diagnostic'] ?? ''];
		if (!in_array($extracted['status'], ['ok', 'empty_text'], true) || $row['pdf_validation']['diagnostic'] !== '') throw new RuntimeException('PDF parser could not validate the historical file without errors');
		$facts = assessmentFacts($extracted['text'] ?? '', $identity, ['document_date' => $document[$table . 'date']]);
		$row['document_identity_text'] = array_intersect_key($facts['comparisons'], array_flip(['number', 'type', 'date']));
		if (DEEC_DocumentReview::isViewingCopy($identity['clientid'], $a['sha256'])) throw new RuntimeException('Known viewing copy is not a historical file original');
		if ($facts['non_original_markers']) throw new RuntimeException('Marked draft or reconstructed copy cannot be recovered as an original');
		foreach ($row['document_identity_text'] as $comparison) if ($comparison['status'] === 'text_differs') throw new RuntimeException('Extracted PDF identity contradicts the stored document; manual clarification required');
		if ($db->fetchOne('SELECT id FROM invoicecorrection WHERE clientid = ? AND sha256 = ? LIMIT 1', [$identity['clientid'], $a['sha256']])) throw new RuntimeException('Known invoice correction is not an original');
		$locator = DEEC_DocumentReview::locatorPath($identity['clientid']);
		if (file_exists($locator)) {
			$location = DEEC_DocumentReview::read($locator); $root = $location['review'] ?? '';
			DEEC_DocumentReview::validateRoot($root, $identity['clientid']);
			if (($location['binding_sha256'] ?? '') !== hash_file('sha256', $root . '/review.json')) throw new RuntimeException('Changed review binding');
			foreach (['reconstructions', 'diagnostics'] as $type) if (glob($root . '/*/' . $type . '/' . $a['sha256'] . '.json')) throw new RuntimeException('Known reconstruction is not a historical file original');
		}
		$row['checks']['pdf_structure_and_not_known_copy'] = true;
		$relative = $archive->relativePath($identity['clientid'], $identity['module'], $table, $identity['id']);
		$registrations = $db->fetchAll('SELECT * FROM documentarchive WHERE (clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ?) OR path = ? ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), [$identity['clientid'], $identity['module'], $table, $identity['id'], 'pdf', $relative]);
		$row['registrations'] = $registrations;
		if (count($registrations) > 1) throw new RuntimeException('Conflicting archive registrations');
		if ($registrations) {
			$registered = $registrations[0];
			$expected = ['clientid' => $identity['clientid'], 'module' => $identity['module'], 'controller' => $table, 'documentid' => $identity['id'], 'format' => 'pdf', 'version' => 1, 'path' => $relative, 'sha256' => $a['sha256'], 'filesize' => $a['size'], 'filename' => $document['filename']];
			foreach ($expected as $key => $value) if ((string)$registered[$key] !== (string)$value) throw new RuntimeException('Existing original registration differs; it is protected');
			if (in_array(DEEC_DocumentArchive::pdfProvenance($registered), ['reviewed_reconstruction_duplicate', 'reconstruction_origin_unconfirmed'], true)) throw new RuntimeException('Registered reconstruction is not a historical file original');
		}
		$row['checks']['no_conflicting_registration'] = true;
		if ($row['files']['canonical']['status'] !== 'missing') throw new RuntimeException('Canonical original path is occupied; it is protected');
		$row['checks']['canonical_path_missing'] = true; $row['action'] = 'recover';
	} catch (RuntimeException | InvalidArgumentException | Zend_Exception $error) { $row['reason'] = $error->getMessage(); }
	return $row;
}

function historicalRecoveryComparable(array $row): array
{
	unset($row['files']['canonical'], $row['registrations'], $row['checks']['canonical_path_missing'], $row['action'], $row['reason']);
	return $row;
}

function historicalRecoveryCurrent(Zend_Db_Adapter_Abstract $db, array $planned, string $extractor, string $planSha, bool $lock = false): array
{
	$current = historicalRecoveryRow($db, $planned['identity'], $extractor, $lock);
	if (historicalRecoveryComparable($current) !== historicalRecoveryComparable($planned)) throw new RuntimeException('Planned source files, identity, associations or stored data changed; review a new preview');
	$canonical = $current['files']['canonical']; $source = $planned['files']['attachment'];
	if ($canonical['status'] === 'ok' && $canonical['sha256'] === $source['sha256'] && $canonical['size'] === $source['size'] && count($current['registrations']) === 1) {
		$registered = $current['registrations'][0];
		$recovery = json_decode((string)$registered['parameters'], true)['recovery'] ?? [];
		if ($current['registrations'] === $planned['registrations'] || (($recovery['rule'] ?? '') === HISTORICAL_FILE_RULE && ($recovery['approval']['batch_plan_sha256'] ?? '') === $planSha && DEEC_DocumentArchive::hasRecoveryApproval($registered))) return ['status' => 'already_recovered', 'row' => $current];
	}
	if ($current['action'] !== 'recover' || $current['registrations'] !== $planned['registrations']) throw new RuntimeException('Canonical file or archive registration changed; no overwrite is allowed');
	return ['status' => 'ready', 'row' => $current];
}

function historicalRecoveryApply(Zend_Db_Adapter_Abstract $db, array $row, string $extractor, array $approval): array
{
	$user = Zend_Registry::get('User');
	if (empty($user['admin']) || ($approval['approved_by'] ?? 0) !== (int)$user['id'] || ($approval['manual'] ?? false) !== true || ($approval['rule'] ?? '') !== HISTORICAL_FILE_RULE || ($approval['database_and_file_backup_confirmed'] ?? false) !== true || !preg_match('/^[a-f0-9]{64}$/D', $approval['batch_plan_sha256'] ?? '') || trim((string)($approval['backup_reference'] ?? '')) === '') throw new RuntimeException('Administrator batch approval and verified backup evidence required');
	$archive = new DEEC_DocumentArchive(BASE_PATH); $published = null; $commitStarted = false; $db->beginTransaction();
	try {
		$current = historicalRecoveryCurrent($db, $row, $extractor, $approval['batch_plan_sha256'], true);
		if ($current['status'] === 'already_recovered') { $db->commit(); return ['status' => 'already_recovered']; }
		$id = $row['identity']; $source = $row['files']['attachment'];
		$published = $archive->storeFile($source['path'], $id['clientid'], $id['module'], $id['controller'], $id['id'], 'pdf', $source['sha256']);
		foreach (['attachment', 'legacy'] as $name) if ($archive->inspect($row['files'][$name]['path'], 'pdf') !== $row['files'][$name]) throw new RuntimeException('Historical source changed during recovery');
		$recovery = ['rule' => HISTORICAL_FILE_RULE, 'historical_file_recovery' => true, 'original_provenance_verified' => false, 'recovered_at' => gmdate('c'), 'recovered_by' => $approval['approved_by'], 'snapshot_sha256' => $row['snapshot_sha256'],
			'selection' => ['origin' => ['kind' => 'historical_pdf', 'category' => 'historical_file_recovery_origin_unconfirmed', 'source_path' => $source['path']]],
			'source_files' => $row['files'], 'document_attachment_associations' => $row['attachments'], 'historical_conflicts' => $row['current_version_differs'] ? ['current_contact_version_differs_retained'] : [],
			'approval' => array_replace($approval, ['identity' => $id, 'sha256' => $source['sha256'], 'size' => $source['size'], 'original_provenance_verified' => false])];
		$document = recoverySnapshot($db, $id, true)['data']['document'];
		$model = new Application_Model_DbTable_Documentarchive(); $registered = $model->registerOriginal($document, $id['module'], $id['controller'], 'pdf', null, ['recovery' => $recovery]);
		$verified = $model->getForDocument($id['id'], $id['module'], $id['controller']);
		if (!$verified || $verified['sha256'] !== $source['sha256'] || (int)$verified['filesize'] !== $source['size'] || recoverySnapshot($db, $id)['sha256'] !== $row['snapshot_sha256']) throw new RuntimeException('Final recovery registration/file/data integrity check failed');
		$commitStarted = true; $db->commit();
		return ['status' => $row['registrations'] ? 'restored_registered_bytes' : 'recovered', 'registrationid' => (int)$registered['id'], 'sha256' => $source['sha256'], 'rule' => HISTORICAL_FILE_RULE, 'original_provenance_verified' => false];
	} catch (Throwable $error) {
		$rolledBack = false; try { $db->rollBack(); $rolledBack = true; } catch (Throwable $ignored) { error_log('Historical recovery rollback failed'); }
		if (!$commitStarted && $rolledBack && $published) try { $archive->discardUncommitted($published); } catch (Throwable $ignored) { error_log('Historical recovery file retained for inspection'); }
		throw $error;
	}
}

function historicalRecoveryMain(array $options): array
{
	umask(0077);
	foreach (['config', 'base', 'client', 'operator-user', 'inventory', 'output', 'action'] as $key) if (!is_string($options[$key] ?? null) || $options[$key] === '') throw new RuntimeException('Required option: --' . $key);
	$client = reviewId($options['client']); $user = reviewId($options['operator-user']); $base = $options['base']; $action = $options['action'];
	if (!in_array($action, ['plan', 'apply'], true)) throw new RuntimeException('Action must be plan or apply');
	reviewSafePath($base); if (realpath($base) !== $base) throw new RuntimeException('Canonical installation base required');
	DEEC_DocumentReview::privateFile($options['config']);
	$lock = null;
	$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $base, $client, $user);
	try {
		if (empty(Zend_Registry::get('User')['admin'])) throw new RuntimeException('Historical recovery batch requires an authorized administrator');
		$inventory = assessmentInventory($options['inventory'], $client); $scope = [];
		if ((int)$inventory['summary']['contacts_clientid'] !== (int)Zend_Registry::get('Client')['modules']['contacts']) throw new RuntimeException('Inventory contact tenant differs');
		foreach (assessmentRows($options['inventory']) as $entry) if (($entry['kind'] ?? '') === 'document' && $entry['plan']['status'] !== 'complete') $scope[] = reviewIdentity($entry['plan'], $client);
		if (!hash_equals($inventory['sha256'], hash_file('sha256', $options['inventory']))) throw new RuntimeException('Inventory changed during scope selection');
		$extractor = $options['pdftotext'] ?? '/usr/bin/pdftotext';
		$header = ['schema_version' => 1, 'kind' => 'historical_file_recovery_batch', 'rule' => HISTORICAL_FILE_RULE, 'clientid' => $client, 'operator_user' => $user, 'base' => $base, 'inventory' => $inventory['path'], 'inventory_sha256' => $inventory['sha256'], 'scope' => $scope, 'pdftotext' => $extractor];
		$plan = null; $approval = null;
		if ($action === 'apply') {
			DEEC_DocumentReview::privateFile($options['plan'] ?? '');
			$planBytes = file_get_contents($options['plan']); $plan = json_decode($planBytes, true, 512, JSON_THROW_ON_ERROR);
			if (($plan['action'] ?? '') !== 'plan' || !is_string($options['confirm-plan'] ?? null) || !hash_equals(hash('sha256', $planBytes), $options['confirm-plan']) || !array_key_exists('backup-verified', $options) || !is_string($options['backup-reference'] ?? null) || trim($options['backup-reference']) === '' || strlen($options['backup-reference']) > 4000) throw new RuntimeException('Apply requires exact reviewed --confirm-plan SHA, --backup-verified and a concrete --backup-reference for database and files');
			foreach ($header as $key => $value) if (($plan[$key] ?? null) !== $value) throw new RuntimeException('Reviewed plan context differs: ' . $key);
			if (array_column($plan['rows'], 'identity') !== $scope) throw new RuntimeException('Reviewed plan scope differs');
			$approval = ['manual' => true, 'method' => 'administrator_reviewed_batch', 'rule' => HISTORICAL_FILE_RULE, 'approved_by' => $user, 'approved_at' => gmdate('c'), 'batch_plan_sha256' => $options['confirm-plan'], 'database_and_file_backup_confirmed' => true, 'backup_reference' => trim($options['backup-reference'])];
			DEEC_DocumentReview::privateDirectory(dirname($options['plan']));
			$lockPath = dirname($options['plan']) . '/.apply.lock'; reviewSafePath($lockPath);
			if (file_exists($lockPath)) DEEC_DocumentReview::privateFile($lockPath);
			$lock = fopen($lockPath, 'c+b');
			if (!$lock || !chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) throw new RuntimeException('Reviewed batch lock unavailable');
			DEEC_DocumentReview::privateFile($lockPath);
		}
		$output = $options['output']; reviewSafePath($output); DEEC_DocumentReview::privateDirectory(dirname($output));
		if (file_exists($output) || is_link($output)) throw new RuntimeException('A new private output directory is required');
		reviewDirectory($output); DEEC_DocumentReview::privateDirectory($output);
		$rows = [];
		$db->query('SET TRANSACTION READ ONLY'); $db->beginTransaction();
		try {
			if ($action === 'plan') foreach ($scope as $identity) $rows[] = historicalRecoveryRow($db, $identity, $extractor);
			else foreach ($plan['rows'] as $row) {
				if (!in_array($row['action'] ?? '', ['recover', 'excluded'], true)) throw new RuntimeException('Invalid reviewed action');
				if ($row['action'] === 'recover') {
					try { historicalRecoveryCurrent($db, $row, $extractor, $approval['batch_plan_sha256']); }
					catch (Throwable $error) { throw new RuntimeException(reviewKey($row['identity']) . ': ' . $error->getMessage(), 0, $error); }
				}
			}
		} finally { $db->rollBack(); }
		if ($action === 'apply') {
			reviewWrite($output . '/approval.json', reviewJson($approval));
			foreach ($plan['rows'] as $row) {
				if ($row['action'] === 'recover') {
					try { $row['result'] = historicalRecoveryApply($db, $row, $extractor, $approval); }
					catch (Throwable $error) { $row['result'] = ['status' => 'failed', 'reason' => $error->getMessage()]; }
				} else $row['result'] = ['status' => 'excluded', 'reason' => $row['reason']];
				reviewWrite($output . '/' . str_replace('/', '-', reviewKey($row['identity'])) . '.json', reviewJson($row));
				$rows[] = $row;
			}
		}
		$report = $header + ['action' => $action, 'recorded_at' => gmdate('c'), 'approval' => $approval, 'rows' => $rows];
		reviewWrite($output . '/report.json', reviewJson($report));
		$csv = [['module', 'controller', 'internal_id', 'document_number', 'action', 'result', 'reason', 'sha256', 'size', 'attachment', 'legacy']];
		foreach ($rows as $row) $csv[] = ['module' => $row['identity']['module'], 'controller' => $row['identity']['controller'], 'internal_id' => $row['identity']['id'], 'document_number' => $row['identity']['document_number'], 'action' => $row['action'], 'result' => $row['result']['status'] ?? '', 'reason' => $row['result']['reason'] ?? $row['reason'], 'sha256' => $row['files']['attachment']['sha256'] ?? '', 'size' => $row['files']['attachment']['size'] ?? '', 'attachment' => $row['files']['attachment']['path'] ?? '', 'legacy' => $row['files']['legacy']['path'] ?? ''];
		reviewWrite($output . '/documents.csv', reviewCsv($csv));
		$summary = array_count_values(array_map(static fn(array $row): string => $row['result']['status'] ?? $row['action'], $rows));
		reviewWrite($output . '/summary.json', reviewJson($header + ['action' => $action, 'summary' => $summary, 'original_provenance_verified' => false]));
		return ['report' => $output . '/report.json', 'report_sha256' => hash_file('sha256', $output . '/report.json'), 'summary' => $summary, 'exit' => isset($summary['failed']) ? 1 : 0];
	} finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } $db->closeConnection(); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
	$result = historicalRecoveryMain(getopt('', ['config:', 'environment:', 'base:', 'client:', 'operator-user:', 'inventory:', 'output:', 'action:', 'pdftotext:', 'plan:', 'confirm-plan:', 'backup-verified', 'backup-reference:']));
	echo reviewJson($result); exit($result['exit']);
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
