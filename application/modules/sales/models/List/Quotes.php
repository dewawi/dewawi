<?php

class Sales_Model_List_Quotes extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'quoteid',
				'label' => 'QUOTES_QUOTE_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'QUOTES_TITLE',
				'type' => 'link',
				'fallback_field' => 'id',
			],
			[
				'name' => 'contact',
				'label' => 'QUOTES_CONTACT',
				'type' => 'contact',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'cid',
				],
			],
			[
				'name' => 'billing_address',
				'label' => 'QUOTES_BILLING_ADDRESS',
				'type' => 'address',
				'fields' => [
					'billingstreet',
					'billingpostcode',
					'billingcity',
				],
			],
			[
				'name' => 'notes',
				'label' => 'QUOTES_NOTES',
				'type' => 'editable_note',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'quotedate',
				'label' => 'QUOTES_QUOTE_DATE',
				'type' => 'date',
				'format' => 'd.m.Y',
			],
			[
				'name' => 'total',
				'label' => 'QUOTES_TOTAL',
				'type' => 'currency',
				'secondary_field' => 'subtotal',
			],
			[
				'name' => 'monitoring',
				'label' => 'QUOTES_MONITORING',
				'type' => 'callback',
				'class' => 'dw-col-monitoring',
				'callback' => function ($item, $column, $list) {
					return $this->renderMonitoringCell($item);
				},
			],
			[
				'name' => 'state',
				'label' => 'QUOTES_STATE',
				'type' => 'state_badge',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function ($item, $element, $list) {
					return !$list->isReadonly($item);
				},
				'state_map' => [
					'100' => 'created',
					'101' => 'in-process',
					'102' => 'check',
					'103' => 'delete',
					'104' => 'released',
					'105' => 'completed',
					'106' => 'cancelled',
				],
			],
			[
				'name' => 'pin',
				'label' => '',
				'type' => 'pin',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					[
						'name' => 'view',
						'show' => function ($item, $element, $list) {
							return $list->isReadonly($item);
						},
					],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							return !$list->isReadonly($item);
						},
					],
					['name' => 'copy'],
					[
						'name' => 'cancel',
						'show' => function ($item, $element, $list) {
							return $list->isCancellable($item);
						},
					],
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}

	private function renderMonitoringCell($item): string
	{
		$status = (string)$this->getFieldValue($item, 'monitoring_status', '');
		if($status === '') return '';

		if($status === 'draft') return $this->badge('QUOTES_MONITORING_DRAFT', 'default');
		if($status === 'cancelled') return $this->badge('QUOTES_MONITORING_CANCELLED', 'cancelled');

		$parts = [];

		if($status === 'continued') {
			$parts[] = $this->badge('QUOTES_MONITORING_CONTINUED', 'completed');
			$parts = array_merge($parts, $this->renderFollowups($item));
			return implode('', $parts);
		}

		if($status === 'followup_draft') {
			$parts[] = $this->badge('QUOTES_MONITORING_FOLLOWUP_DRAFT', 'warning');
			$parts = array_merge($parts, $this->renderFollowups($item));
			$parts[] = '<div><strong>' . $this->escape($this->translate('QUOTES_MONITORING_REVIEW')) . '</strong></div>';
			return implode('', $parts);
		}

		$lastsent = $this->formatMonitoringDate($this->getFieldValue($item, 'monitoring_lastsent'));

		if($lastsent !== '') {
			$parts[] = '<div>' . $this->escape(
				$this->translate('QUOTES_MONITORING_SENT_AT', [$lastsent])
			) . '</div>';
		}

		if($status === 'feedback_response') {
			$parts[] = $this->badge('QUOTES_MONITORING_FEEDBACK_RECEIVED', 'info');

			$feedbackStatus = (string)$this->getFieldValue($item, 'monitoring_feedback_status', '');
			$feedbackReason = (string)$this->getFieldValue($item, 'monitoring_feedback_reason', '');
			$feedbackMessage = trim((string)$this->getFieldValue($item, 'monitoring_feedback_message', ''));

			$statusLabel = $this->getFeedbackLabel('statuses', $feedbackStatus);
			$reasonLabel = $this->getFeedbackLabel('reasons', $feedbackReason);

			if($statusLabel !== '') $parts[] = '<div>' . $this->escape($statusLabel) . '</div>';
			if($reasonLabel !== '') $parts[] = '<div>' . $this->escape($reasonLabel) . '</div>';
			if($feedbackMessage !== '') $parts[] = '<div>' . $this->escape($this->truncate($feedbackMessage, 80)) . '</div>';
		} elseif($status === 'feedback_open') {
			$parts[] = $this->badge('QUOTES_MONITORING_FEEDBACK_OPEN', 'info');
		} elseif($status === 'sent') {
			$parts[] = $this->badge('QUOTES_MONITORING_FOLLOW_UP', 'warning');
		} elseif($status === 'not_sent') {
			$parts[] = $this->badge('QUOTES_MONITORING_CHECK_SEND', 'warning');
			$parts[] = '<div>' . $this->escape($this->translate('QUOTES_MONITORING_NOT_SENT')) . '</div>';
		}

		$days = $this->getFieldValue($item, 'monitoring_open_days');

		if($days !== null && $status !== 'feedback_response') {
			$parts[] = '<div>' . $this->escape(
				$this->translate('QUOTES_MONITORING_OPEN_DAYS', [(int)$days])
			) . '</div>';
		}

		$action = (string)$this->getFieldValue($item, 'monitoring_action', '');

		$actionKey = [
			'follow_up' => 'QUOTES_MONITORING_FOLLOW_UP',
			'check_send' => 'QUOTES_MONITORING_CHECK_SEND',
			'contact' => 'QUOTES_MONITORING_CONTACT',
			'waiting' => 'QUOTES_MONITORING_WAITING',
			'review' => 'QUOTES_MONITORING_REVIEW',
		][$action] ?? '';

		if($actionKey !== '' && !in_array($status, ['sent', 'not_sent'], true)) {
			$parts[] = '<div><strong>' . $this->escape($this->translate($actionKey)) . '</strong></div>';
		}

		return implode('', $parts);
	}

	private function renderFollowups($item): array
	{
		$parts = [];

		$documents = [
			[
				'controller' => 'salesorder',
				'id' => 'monitoring_salesorder_internal_id',
				'number' => 'monitoring_salesorderid',
				'label' => 'QUOTES_MONITORING_SALESORDER',
			],
			[
				'controller' => 'invoice',
				'id' => 'monitoring_invoice_internal_id',
				'number' => 'monitoring_invoiceid',
				'label' => 'QUOTES_MONITORING_INVOICE',
			],
			[
				'controller' => 'deliveryorder',
				'id' => 'monitoring_deliveryorder_internal_id',
				'number' => 'monitoring_deliveryorderid',
				'label' => 'QUOTES_MONITORING_DELIVERYORDER',
			],
		];

		foreach($documents as $document) {
			$id = (int)$this->getFieldValue($item, $document['id'], 0);
			$number = (string)$this->getFieldValue($item, $document['number'], '');

			if($id <= 0) continue;

			$url = $this->buildUrl($item, [
				'module' => 'sales',
				'controller' => $document['controller'],
				'action' => 'view',
				'id_field' => $document['id'],
			]);

			$label = $this->translate($document['label']);
			if($number !== '') $label .= ' ' . $number;

			$parts[] = '<div><a href="' . $this->escapeAttr($url) . '">' . $this->escape($label) . '</a></div>';
		}

		return $parts;
	}

	private function getFeedbackLabel(string $group, string $value): string
	{
		if($value === '') return '';

		$config = (new DEEC_Feedback())->getTypeConfig(DEEC_Feedback::TYPE_QUOTE);
		$key = $config[$group][$value] ?? '';

		return $key !== '' ? $this->translate($key) : $value;
	}

	private function badge(string $labelKey, string $state): string
	{
		return '<div><span class="dw-badge dw-badge--' . $this->escapeAttr($state) . '">' . $this->escape($this->translate($labelKey)) . '</span></div>';
	}

	private function formatMonitoringDate($date): string
	{
		if(!$date) return '';

		$timestamp = strtotime((string)$date);

		return $timestamp ? date('d.m.Y', $timestamp) : '';
	}

	private function truncate(string $value, int $length): string
	{
		if(mb_strlen($value) <= $length) return $value;

		return rtrim(mb_substr($value, 0, $length - 3)) . '...';
	}
}
