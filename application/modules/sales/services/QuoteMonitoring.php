<?php

class Sales_Service_QuoteMonitoring
{
	public function enrich($items): array
	{
		$rows = $this->normalizeItems($items);
		if(!$rows) return [];

		$documentIds = [];
		$quoteIds = [];
		$clientIds = [];

		foreach($rows as $row) {
			if(!empty($row['id'])) $documentIds[] = (int)$row['id'];
			if(!empty($row['quoteid'])) $quoteIds[] = (int)$row['quoteid'];
			if(!empty($row['clientid'])) $clientIds[] = (int)$row['clientid'];
		}

		$documentIds = array_values(array_unique(array_filter($documentIds)));
		$quoteIds = array_values(array_unique(array_filter($quoteIds)));
		$clientIds = array_values(array_unique(array_filter($clientIds)));

		$db = (new Sales_Model_DbTable_Quote())->getAdapter();

		$emails = $this->getEmailData($db, $documentIds);
		$feedback = $this->getFeedbackData($db, $documentIds, $clientIds);
		$salesorders = $this->getFollowupData($db, 'salesorder', 'salesorderid', $quoteIds, $clientIds);
		$invoices = $this->getFollowupData($db, 'invoice', 'invoiceid', $quoteIds, $clientIds);
		$deliveryorders = $this->getFollowupData($db, 'deliveryorder', 'deliveryorderid', $quoteIds, $clientIds);

		foreach($rows as &$row) {
			$documentId = (int)($row['id'] ?? 0);
			$key = $this->quoteKey((int)($row['clientid'] ?? 0), (int)($row['quoteid'] ?? 0));

			$email = $emails[$documentId] ?? [];
			$response = $feedback[$documentId] ?? [];
			$salesorder = $salesorders[$key] ?? [];
			$invoice = $invoices[$key] ?? [];
			$deliveryorder = $deliveryorders[$key] ?? [];

			$row['monitoring_lastsent'] = $email['lastsent'] ?? null;
			$row['monitoring_sentcount'] = (int)($email['sentcount'] ?? 0);

			$row['monitoring_feedback_id'] = (int)($response['id'] ?? 0);
			$row['monitoring_feedback_status'] = $response['status'] ?? null;
			$row['monitoring_feedback_reason'] = $response['reason'] ?? null;
			$row['monitoring_feedback_message'] = $response['message'] ?? null;
			$row['monitoring_feedback_created'] = $response['created'] ?? null;
			$row['monitoring_feedback_responded'] = $response['responded'] ?? null;

			$row['monitoring_salesorder_internal_id'] = (int)($salesorder['id'] ?? 0);
			$row['monitoring_salesorderid'] = $salesorder['salesorderid'] ?? null;

			$row['monitoring_invoice_internal_id'] = (int)($invoice['id'] ?? 0);
			$row['monitoring_invoiceid'] = $invoice['invoiceid'] ?? null;

			$row['monitoring_deliveryorder_internal_id'] = (int)($deliveryorder['id'] ?? 0);
			$row['monitoring_deliveryorderid'] = $deliveryorder['deliveryorderid'] ?? null;

			$this->applyStatus($row);
		}
		unset($row);

		return $rows;
	}

	private function normalizeItems($items): array
	{
		$rows = [];

		foreach($items as $item) {
			if(is_array($item)) $rows[] = $item;
			elseif(is_object($item) && method_exists($item, 'toArray')) $rows[] = $item->toArray();
			else $rows[] = (array)$item;
		}

		return $rows;
	}

	private function getEmailData($db, array $documentIds): array
	{
		if(!$documentIds) return [];

		$rows = $db->fetchAll(
			$db->select()
				->from('emailmessage', ['id', 'documentid', 'messagesent'])
				->where('documentid IN (?)', $documentIds)
				->where('module = ?', 'sales')
				->where('controller = ?', 'quote')
				->where('deleted = ?', 0)
				->where('response = ?', 'sent')
				->order('id DESC')
		);

		$result = [];

		foreach($rows as $row) {
			$id = (int)$row['documentid'];

			if(!isset($result[$id])) {
				$result[$id] = [
					'lastsent' => $row['messagesent'],
					'sentcount' => 0,
				];
			}

			$result[$id]['sentcount']++;
		}

		return $result;
	}

	private function getFeedbackData($db, array $documentIds, array $clientIds): array
	{
		if(!$documentIds) return [];

		$select = $db->select()
			->from('feedback', [
				'id',
				'parentid',
				'status',
				'reason',
				'message',
				'created',
				'responded',
			])
			->where('parentid IN (?)', $documentIds)
			->where('module = ?', 'sales')
			->where('controller = ?', 'quote')
			->where('deleted = ?', 0)
			->order('id DESC');

		if($clientIds) $select->where('clientid IN (?)', $clientIds);

		$result = [];

		foreach($db->fetchAll($select) as $row) {
			$id = (int)$row['parentid'];
			if(!isset($result[$id])) $result[$id] = $row;
		}

		return $result;
	}

	private function getFollowupData($db, string $table, string $documentField, array $quoteIds, array $clientIds): array
	{
		if(!$quoteIds || !$clientIds) return [];

		$rows = $db->fetchAll(
			$db->select()
				->from($table, ['id', 'clientid', 'quoteid', $documentField, 'state', 'created'])
				->where('quoteid IN (?)', $quoteIds)
				->where('clientid IN (?)', $clientIds)
				->where('deleted = ?', 0)
				->where('state <> ?', 106)
				->order('id DESC')
		);

		$result = [];

		foreach($rows as $row) {
			$key = $this->quoteKey((int)$row['clientid'], (int)$row['quoteid']);
			if(!isset($result[$key])) $result[$key] = $row;
		}

		return $result;
	}

	private function applyStatus(array &$row): void
	{
		$row['monitoring_open_days'] = null;
		$row['monitoring_action'] = '';

		if(empty($row['quoteid'])) {
			$row['monitoring_status'] = 'draft';
			return;
		}

		if((int)($row['state'] ?? 0) === 106 || !empty($row['cancelled'])) {
			$row['monitoring_status'] = 'cancelled';
			return;
		}

		$hasFollowup =
			!empty($row['monitoring_salesorder_internal_id'])
			|| !empty($row['monitoring_invoice_internal_id'])
			|| !empty($row['monitoring_deliveryorder_internal_id']);

		$hasFinalFollowup =
			!empty($row['monitoring_salesorderid'])
			|| !empty($row['monitoring_invoiceid'])
			|| !empty($row['monitoring_deliveryorderid']);

		if($hasFinalFollowup) {
			$row['monitoring_status'] = 'continued';
			return;
		}

		if($hasFollowup) {
			$row['monitoring_status'] = 'followup_draft';
			$row['monitoring_action'] = 'review';
			return;
		}

		if(!empty($row['monitoring_feedback_id'])) {
			if(!empty($row['monitoring_feedback_responded'])) {
				$row['monitoring_status'] = 'feedback_response';
				$row['monitoring_action'] = $this->getFeedbackAction((string)($row['monitoring_feedback_status'] ?? ''));
				return;
			}

			$row['monitoring_status'] = 'feedback_open';
			$row['monitoring_open_days'] = $this->daysSince($row['monitoring_feedback_created'] ?? null);
			return;
		}

		if(!empty($row['monitoring_sentcount'])) {
			$row['monitoring_status'] = 'sent';
			$row['monitoring_action'] = 'follow_up';
			$row['monitoring_open_days'] = $this->daysSince($row['monitoring_lastsent'] ?? null);
			return;
		}

		$row['monitoring_status'] = 'not_sent';
		$row['monitoring_action'] = 'check_send';
		$row['monitoring_open_days'] = $this->daysSince($row['quotedate'] ?? $row['created'] ?? null);
	}

	private function getFeedbackAction(string $status): string
	{
		return [
			'interested' => 'follow_up',
			'question' => 'contact',
			'pending' => 'waiting',
			'declined' => 'review',
		][$status] ?? 'review';
	}

	private function daysSince($date): ?int
	{
		if(!$date) return null;

		$timestamp = strtotime((string)$date);
		if(!$timestamp) return null;

		return max(0, (int)floor((time() - $timestamp) / 86400));
	}

	private function quoteKey(int $clientId, int $quoteId): string
	{
		return $clientId . ':' . $quoteId;
	}
}
