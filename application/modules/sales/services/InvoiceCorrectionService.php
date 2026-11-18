<?php

class Sales_Service_InvoiceCorrectionService
{
	private const FIELDS = ['billingname1', 'billingname2', 'billingdepartment', 'billingstreet', 'billingpostcode', 'billingcity', 'subject', 'reference'];
	private const RIGHTS = ['view' => ['view'], 'create' => ['view', 'add', 'edit'], 'edit' => ['view', 'edit'], 'approve' => ['view', 'edit'], 'send' => ['view', 'add'], 'export' => ['view']];
	private const RECORD_FIELDS = ['clientid', 'invoiceid', 'sequence', 'previousid', 'reason', 'snapshot_json', 'snapshot_sha256', 'changes_json', 'revision', 'status', 'created', 'createdby', 'modified', 'modifiedby', 'approved', 'approvedby', 'approval_json', 'format_scope', 'format_reason', 'consent', 'path', 'filesize', 'sha256'];

	public function context(int $id, string $operation = 'view'): array
	{
		$user = (array)Zend_Registry::get('User'); $client = Zend_Registry::get('Client'); $permission = new DEEC_Permission($user);
		$context = $permission->getObjectContext($id, 'sales', 'invoice');
		$rights = self::RIGHTS[$operation] ?? null;
		if ($rights === null) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		if (!$context || (int)$context['clientid'] !== (int)$client['id'] || (int)$user['clientid'] !== (int)$client['id']) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		foreach ($rights as $right) if (!$permission->hasPermission('sales', 'invoice', $right)) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		if ($operation === 'send' && !$permission->hasRoutePermission(new Zend_Controller_Request_Simple('send', 'email', 'contacts'))) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		if (in_array($operation, ['approve', 'export'], true) && empty($user['admin'])) throw new RuntimeException('DOCUMENT_CHANGES_ADMIN_REQUIRED');
		if (in_array($operation, ['create', 'edit', 'approve', 'send'], true) && (empty($context['parent']['invoiceid']) || !DEEC_Permission::isDocumentReadonly('sales', 'invoice', $context['parent']) || !empty($context['parent']['cancelled']))) throw new RuntimeException('DOCUMENT_CHANGES_FINALIZED_REQUIRED');
		return $context['parent'];
	}

	public function history(int $id): array
	{
		$invoice = $this->context($id);
		return (new Application_Model_DbTable_Invoicecorrection())->getForInvoice($id, (int)$invoice['clientid']);
	}

	public static function fingerprint(array $record): string
	{
		$values = []; foreach (self::RECORD_FIELDS as $field) $values[$field] = (string)($record[$field] ?? '');
		return hash('sha256', json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
	}

	public function get(int $invoiceId, int $id, bool $lock = false): array
	{
		$invoice = $this->context($invoiceId); $db = Zend_Db_Table::getDefaultAdapter();
		$row = $db->fetchRow('SELECT * FROM invoicecorrection WHERE id = ? AND invoiceid = ? AND clientid = ?' . ($lock ? ' FOR UPDATE' : ''), [$id, $invoiceId, $invoice['clientid']]);
		if (!$row || !hash_equals(self::fingerprint($row), (string)$row['record_sha256']) || !hash_equals(hash('sha256', $row['snapshot_json']), $row['snapshot_sha256'])) throw new RuntimeException('CORRECTION_INTEGRITY_FAILED');
		$row['snapshot'] = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR); $row['changes'] = json_decode($row['changes_json'], true, 512, JSON_THROW_ON_ERROR);
		if ((int)$row['snapshot']['source']['document']['id'] !== $invoiceId || (int)$row['snapshot']['source']['document']['clientid'] !== (int)$invoice['clientid']) throw new RuntimeException('CORRECTION_INTEGRITY_FAILED');
		$row['identity'] = 'RB-' . $row['snapshot']['source']['document']['invoiceid'] . '-' . $row['sequence'];
		return $row;
	}

	public function original(int $id): array
	{
		$this->context($id);
		try { $row = (new Application_Model_DbTable_Documentarchive())->getForDocument($id, 'sales', 'invoice'); }
		catch (RuntimeException $error) { throw new RuntimeException('CORRECTION_ORIGINAL_REQUIRED'); }
		if (!$row) throw new RuntimeException('CORRECTION_ORIGINAL_REQUIRED');
		$provenance = DEEC_DocumentArchive::pdfProvenance($row); $parameters = json_decode((string)($row['parameters'] ?? ''), true);
		if (in_array($provenance, ['reviewed_reconstruction_duplicate', 'reconstruction_origin_unconfirmed'], true)
			|| (is_array($parameters) && array_key_exists('recovery', $parameters) && $provenance !== 'verified_recovered_original')
			|| (!empty($row['parameters']) && json_last_error() !== JSON_ERROR_NONE)) throw new RuntimeException('CORRECTION_ORIGINAL_REQUIRED');
		return array_intersect_key($row, array_flip(['id', 'sha256', 'filesize', 'path', 'filename'])) + ['provenance' => $provenance];
	}

	private function source(int $id, bool $lock): array
	{
		$invoice = $this->context($id);
		if (empty($invoice['invoiceid']) || !empty($invoice['cancelled'])) throw new RuntimeException('DOCUMENT_CHANGES_FINALIZED_REQUIRED');
		$snapshot = DEEC_DocumentReview::snapshot(Zend_Db_Table::getDefaultAdapter(), ['id' => $id, 'module' => 'sales', 'controller' => 'invoice', 'clientid' => (int)$invoice['clientid'], 'document_number' => (string)$invoice['invoiceid']], $lock)['data'];
		foreach (['notes', 'locked', 'lockedtime', 'pinned', 'modified', 'modifiedby'] as $field) unset($snapshot['document'][$field]);
		foreach (['subtotal', 'taxes', 'total'] as $field) if (!is_numeric($snapshot['document'][$field] ?? null)) throw new RuntimeException('CORRECTION_SOURCE_INCOMPLETE');
		if (empty($snapshot['document']['currency']) || empty($snapshot['document']['invoicedate'])) throw new RuntimeException('CORRECTION_SOURCE_INCOMPLETE');
		return $snapshot;
	}

	private static function plain($value): string { return trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string)$value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }

	public static function fields(array $snapshot): array
	{
		$fields = [];
		foreach (self::FIELDS as $name) $fields['document.' . $name] = self::plain($snapshot['document'][$name] ?? '');
		foreach ($snapshot['pos'] as $position) if (empty($position['deleted'])) foreach (['title', 'description'] as $field) $fields['position.' . $position['id'] . '.' . $field] = self::plain($position[$field] ?? '');
		return $fields;
	}

	public static function effective(array $record): array
	{
		$fields = $record['snapshot']['base_fields'];
		foreach ($record['changes'] as $key => $change) $fields[$key] = $change['after'];
		return $fields;
	}

	public function create(int $id, string $reason, string $requestKey): int
	{
		$invoice = $this->context($id, 'create'); $reason = trim($reason);
		if ($reason === '' || mb_strlen($reason) > 2000 || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) throw new RuntimeException('DOCUMENT_CHANGES_REASON_REQUIRED');
		$db = Zend_Db_Table::getDefaultAdapter(); $db->beginTransaction();
		try {
			(new Sales_Model_DbTable_Invoice())->getByIdForUpdate($id);
			$existing = $db->fetchRow('SELECT * FROM invoicecorrection WHERE clientid = ? AND createdby = ? AND request_key = ?', [$invoice['clientid'], Zend_Registry::get('User')['id'], $requestKey]);
			if ($existing) { if ((int)$existing['invoiceid'] !== $id || $existing['reason'] !== $reason) throw new RuntimeException('DOCUMENT_CHANGES_STALE'); $db->commit(); return (int)$existing['id']; }
			$source = $this->source($id, true); $original = $this->original($id); $fields = self::fields($source); $baseFields = $fields;
			$previous = $db->fetchRow("SELECT id FROM invoicecorrection WHERE clientid = ? AND invoiceid = ? AND status = 'approved' ORDER BY sequence DESC LIMIT 1", [$invoice['clientid'], $id]);
			if ($previous) {
				$prior = $this->get($id, (int)$previous['id']); $this->path($prior);
				if ($prior['snapshot']['original'] !== $original || !hash_equals($prior['snapshot']['source_sha256'], hash('sha256', DEEC_DocumentReview::json($source)))) throw new RuntimeException('DOCUMENT_CHANGES_STALE');
				$baseFields = self::effective($prior);
			}
			$client = Zend_Registry::get('Client');
			$issuer = array_intersect_key((array)$client, array_flip(['company', 'address', 'postcode', 'city', 'country']));
			foreach (['company', 'address', 'postcode', 'city', 'country'] as $field) if (empty($issuer[$field])) throw new RuntimeException('CORRECTION_ISSUER_INCOMPLETE');
			$snapshot = json_encode(['source' => $source, 'source_sha256' => hash('sha256', DEEC_DocumentReview::json($source)), 'original' => $original, 'original_fields' => $fields, 'base_fields' => $baseFields, 'previous_identity' => $prior['identity'] ?? null, 'issuer' => $issuer, 'issuer_basis' => 'current_issuer_at_correction_creation_requires_identity_confirmation'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			$row = ['clientid' => (int)$invoice['clientid'], 'invoiceid' => $id, 'sequence' => 1 + (int)$db->fetchOne('SELECT COUNT(*) FROM invoicecorrection WHERE clientid = ? AND invoiceid = ?', [$invoice['clientid'], $id]), 'previousid' => $previous['id'] ?? null,
				'reason' => $reason, 'snapshot_json' => $snapshot, 'snapshot_sha256' => hash('sha256', $snapshot), 'changes_json' => '{}', 'revision' => 1, 'status' => 'draft', 'created' => date('Y-m-d H:i:s'), 'createdby' => (int)Zend_Registry::get('User')['id'], 'modified' => null, 'modifiedby' => null, 'approved' => null, 'approvedby' => null, 'format_scope' => 'unresolved', 'format_reason' => null, 'consent' => 0, 'path' => null, 'filesize' => null, 'sha256' => null, 'request_key' => $requestKey];
			$row['record_sha256'] = self::fingerprint($row); $db->insert('invoicecorrection', $row); $newId = (int)$db->lastInsertId(); $db->commit(); return $newId;
		} catch (Throwable $error) { $db->rollBack(); throw $error; }
	}

	public function save(int $invoiceId, int $id, int $revision, string $expectedHash, string $reason, array $values): void
	{
		$this->context($invoiceId, 'edit'); $reason = trim($reason); $db = Zend_Db_Table::getDefaultAdapter(); $db->beginTransaction();
		try {
			$row = $this->get($invoiceId, $id, true); $this->assertDraft($row, $revision, $expectedHash);
			if ($reason === '' || mb_strlen($reason) > 2000) throw new RuntimeException('DOCUMENT_CHANGES_REASON_REQUIRED');
			$changes = $row['changes'];
			foreach ($values as $key => $value) {
				if (!array_key_exists($key, $row['snapshot']['base_fields']) || !is_string($value) || mb_strlen($value) > (str_ends_with($key, '.description') ? 20000 : 2000)) throw new RuntimeException('CORRECTION_FINANCIAL_BLOCKED');
				$value = trim($value);
				if ($value !== $row['snapshot']['base_fields'][$key]) $changes[$key] = ['original' => $row['snapshot']['original_fields'][$key], 'before' => $row['snapshot']['base_fields'][$key], 'after' => $value];
				else unset($changes[$key]);
			}
			$row = array_replace($row, ['reason' => $reason, 'changes_json' => json_encode($changes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'revision' => $revision + 1, 'modified' => date('Y-m-d H:i:s'), 'modifiedby' => (int)Zend_Registry::get('User')['id']]);
			$db->update('invoicecorrection', array_intersect_key($row, array_flip(['reason', 'changes_json', 'revision', 'modified', 'modifiedby'])) + ['record_sha256' => self::fingerprint($row)], ['id = ?' => $id, 'clientid = ?' => $row['clientid']]); $db->commit();
		} catch (Throwable $error) { $db->rollBack(); throw $error; }
	}

	private function assertDraft(array $row, int $revision, string $hash): void
	{
		if ($row['status'] !== 'draft' || (int)$row['revision'] !== $revision || !hash_equals($row['record_sha256'], $hash)) throw new RuntimeException('DOCUMENT_CHANGES_STALE');
	}

	public function approve(int $invoiceId, int $id, int $revision, string $expectedHash, array $approval): array
	{
		$this->context($invoiceId, 'approve'); $db = Zend_Db_Table::getDefaultAdapter(); $archive = new DEEC_DocumentArchive(BASE_PATH); $published = null; $commitStarted = false;
		$db->beginTransaction();
		try {
			(new Sales_Model_DbTable_Invoice())->getByIdForUpdate($invoiceId); $row = $this->get($invoiceId, $id, true);
			if ($row['status'] === 'approved') { $this->path($row); $db->commit(); return $row; }
			$this->assertDraft($row, $revision, $expectedHash);
			$latest = $db->fetchOne("SELECT id FROM invoicecorrection WHERE clientid = ? AND invoiceid = ? AND status = 'approved' ORDER BY sequence DESC LIMIT 1", [$row['clientid'], $invoiceId]);
			if ((int)$latest !== (int)$row['previousid'] || $this->original($invoiceId) !== $row['snapshot']['original'] || !hash_equals(hash('sha256', DEEC_DocumentReview::json($this->source($invoiceId, true))), $row['snapshot']['source_sha256'])) throw new RuntimeException('DOCUMENT_CHANGES_STALE');
			if ($latest) { $prior = $this->get($invoiceId, (int)$latest); $this->path($prior); if (self::effective($prior) !== $row['snapshot']['base_fields']) throw new RuntimeException('DOCUMENT_CHANGES_STALE'); }
			if (!$row['changes']) throw new RuntimeException('CORRECTION_NO_CHANGES');
			$originReference = $approval['original_reference'] ?? '';
			if (($approval['original_confirmed'] ?? '') !== '1' || !is_string($originReference) || trim($originReference) === '' || mb_strlen($originReference) > 2000) throw new RuntimeException('CORRECTION_ORIGIN_REQUIRED');
			$scope = $approval['format_scope'] ?? ''; $basis = $approval['format_reason'] ?? '';
			if (!is_string($scope) || !in_array($scope, ['consumer', 'outside_domestic_scope', 'transition_2026', 'pre2025_supply'], true) || !is_string($basis) || trim($basis) === '' || mb_strlen($basis) > 2000
				|| ($scope === 'transition_2026' && date('Y') > '2026') || ($approval['identity_confirmed'] ?? '') !== '1' || ($approval['nonfinancial_confirmed'] ?? '') !== '1' || ($approval['structured_not_required'] ?? '') !== '1') throw new RuntimeException('CORRECTION_FORMAT_BLOCKED');
			if ($scope === 'outside_domestic_scope' && strtoupper(trim($row['snapshot']['issuer']['country'])) === 'DE' && strtoupper(trim($row['snapshot']['source']['document']['billingcountry'])) === 'DE') throw new RuntimeException('CORRECTION_FORMAT_BLOCKED');
			if ($scope === 'pre2025_supply' && !empty($row['snapshot']['source']['document']['deliverydate']) && $row['snapshot']['source']['document']['deliverydate'] >= '2025-01-01') throw new RuntimeException('CORRECTION_FORMAT_BLOCKED');
			$xml = $archive->inspect($archive->path((int)$row['clientid'], 'sales', 'invoice', $invoiceId, 'xml'), 'xml');
			if ($xml['status'] !== 'missing' || (new Application_Model_DbTable_Documentarchive())->getForDocument($invoiceId, 'sales', 'invoice', 'xml')) throw new RuntimeException('CORRECTION_STRUCTURED_BLOCKED');
			$fields = self::effective($row);
			foreach (['billingname1', 'billingstreet', 'billingpostcode', 'billingcity'] as $field) if (trim($fields['document.' . $field]) === '') throw new RuntimeException('CORRECTION_RECIPIENT_INCOMPLETE');
			$row = array_replace($row, ['status' => 'approved', 'approved' => date('Y-m-d H:i:s'), 'approvedby' => (int)Zend_Registry::get('User')['id'], 'approval_json' => json_encode(['original_confirmed_for_correction' => true, 'original_reference' => trim($originReference), 'identity_confirmed' => true, 'nonfinancial_confirmed' => true, 'structured_not_required' => true, 'reviewed_record_sha256' => $expectedHash], JSON_THROW_ON_ERROR), 'format_scope' => $scope, 'format_reason' => trim($basis), 'consent' => ($approval['recipient_consent'] ?? '') === '1' ? 1 : 0, 'revision' => $revision + 1]);
			$bytes = (new DEEC_Pdf())->correction($row); $target = $this->target($row);
			$published = $archive->storeAt($bytes, $target, 'pdf'); if (!chmod($target, 0600)) throw new RuntimeException('CORRECTION_INTEGRITY_FAILED');
			$row['path'] = substr($target, strlen(BASE_PATH) + 1); $row['filesize'] = $published['size']; $row['sha256'] = $published['sha256']; $row['record_sha256'] = self::fingerprint($row);
			$db->update('invoicecorrection', array_intersect_key($row, array_flip(['status', 'approved', 'approvedby', 'approval_json', 'format_scope', 'format_reason', 'consent', 'revision', 'path', 'filesize', 'sha256', 'record_sha256'])), ['id = ?' => $id, 'clientid = ?' => $row['clientid']]);
			$this->path($row); $commitStarted = true; $db->commit(); return $row;
		} catch (Throwable $error) {
			$rolledBack = false; try { $db->rollBack(); $rolledBack = true; } catch (Throwable $rollback) { error_log('Correction rollback failed'); }
			if (!$commitStarted && $rolledBack && $published) {
				try { $archive->discardUncommitted($published); } catch (Throwable $cleanup) { error_log('Uncommitted correction PDF retained for inspection'); }
			}
			throw $error;
		}
	}

	private function target(array $row): string
	{
		$original = (new DEEC_DocumentArchive(BASE_PATH))->path((int)$row['clientid'], 'sales', 'invoice', (int)$row['invoiceid']);
		return dirname($original) . '/corrections/' . (int)$row['invoiceid'] . '/' . (int)$row['id'] . '.pdf';
	}

	public function path(array $row): string
	{
		$invoice = $this->context((int)$row['invoiceid']); $target = $this->target($row);
		if ((int)$row['clientid'] !== (int)$invoice['clientid']) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		if ($row['status'] !== 'approved' || !hash_equals(self::fingerprint($row), (string)$row['record_sha256']) || BASE_PATH . '/' . $row['path'] !== $target) throw new RuntimeException('CORRECTION_INTEGRITY_FAILED');
		$file = (new DEEC_DocumentArchive(BASE_PATH))->inspect($target, 'pdf');
		if ($file['status'] !== 'ok' || $file['sha256'] !== $row['sha256'] || $file['size'] !== (int)$row['filesize'] || (fileperms($target) & 0777) !== 0600) throw new RuntimeException('CORRECTION_INTEGRITY_FAILED');
		return $target;
	}

	public function attachment(int $invoiceId, int $id, string $csrf): array
	{
		$this->context($invoiceId, 'send');
		if (!hash_equals(Sales_Service_DocumentRevisionService::token('invoice', $invoiceId), $csrf)) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		$row = $this->get($invoiceId, $id);
		if (empty($row['consent'])) throw new RuntimeException('CORRECTION_CONSENT_REQUIRED');
		return ['path' => $this->path($row), 'filename' => $row['identity'] . '.pdf', 'invoicecorrectionid' => $id];
	}

	public function manifest(int $invoiceId): array
	{
		$invoice = $this->context($invoiceId, 'export'); $records = [];
		foreach ($this->history($invoiceId) as $entry) if ($entry['status'] === 'approved') {
			$row = $this->get($invoiceId, (int)$entry['id']); $this->path($row);
			$records[] = ['kind' => 'editorial_invoice_correction', 'identity' => $row['identity'], 'id' => (int)$row['id'], 'sequence' => (int)$row['sequence'], 'previousid' => $row['previousid'], 'reason' => $row['reason'], 'created' => $row['created'], 'createdby' => $row['createdby'], 'modified' => $row['modified'], 'modifiedby' => $row['modifiedby'], 'approved' => $row['approved'], 'approvedby' => $row['approvedby'], 'approval' => json_decode($row['approval_json'], true, 64, JSON_THROW_ON_ERROR), 'format_scope' => $row['format_scope'], 'format_reason' => $row['format_reason'], 'recipient_consent' => (bool)$row['consent'], 'original_reference' => $row['snapshot']['original'], 'changes' => $row['changes'], 'effective_fields' => self::effective($row), 'snapshot_sha256' => $row['snapshot_sha256'], 'record_sha256' => $row['record_sha256'], 'pdf' => ['sha256' => $row['sha256'], 'size' => (int)$row['filesize'], 'download' => '/sales/invoice/correctionpdf/id/' . $invoiceId . '/correctionid/' . $row['id']]];
		}
		return ['schema_version' => 1, 'kind' => 'editorial_invoice_corrections', 'clientid' => (int)$invoice['clientid'], 'invoiceid' => $invoiceId, 'invoice_number' => $invoice['invoiceid'], 'original_invoice_values_replaced' => false, 'gobd_original_delivery_modified' => false, 'corrections' => $records];
	}
}
