<?php

class Sales_Model_List_Quotes extends DEEC_List
{
	public function __construct()
	{
		$this->init();
	}

	public function init(): void
	{
		$this->setId('quotes');

		$this->setRowClassCallback(function ($item) {
			return !empty($item->pinned) ? 'is-pinned' : '';
		});

		$this->setColumns($this->buildColumns());
	}

	protected function buildColumns()
	{
		return [
			[
				'name' => 'quoteid',
				'label' => 'QUOTES_QUOTE_ID',
				'data_label' => 'QUOTES_QUOTE_ID',
				'type' => 'link',
				'field' => 'quoteid',
				'url' => [
					'action' => 'edit',
					'id_field' => 'id',
				],
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'QUOTES_TITLE',
				'data_label' => 'QUOTES_TITLE',
				'type' => 'link',
				'field' => 'title',
				'fallback_field' => 'id',
				'url' => [
					'action' => 'edit',
					'id_field' => 'id',
				],
				'class' => 'dw-col-title',
			],
			[
				'name' => 'contact',
				'label' => 'QUOTES_CONTACT',
				'data_label' => 'QUOTES_CONTACT',
				'type' => 'contact',
				'class' => 'dw-col-contact',
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
				'data_label' => 'QUOTES_BILLING_ADDRESS',
				'type' => 'address',
				'class' => 'dw-col-address',
				'fields' => [
					'billingstreet',
					'billingpostcode',
					'billingcity',
				],
			],
			[
				'name' => 'notes',
				'label' => 'QUOTES_NOTES',
				'data_label' => 'QUOTES_NOTES',
				'type' => 'editable_note',
				'field' => 'notes',
				'editable_name' => 'notes',
				'empty_label' => 'TOOLBAR_NEW',
				'class' => 'dw-col-notes notes',
			],
			[
				'name' => 'quotedate',
				'label' => 'QUOTES_QUOTE_DATE',
				'data_label' => 'QUOTES_QUOTE_DATE',
				'type' => 'date',
				'field' => 'quotedate',
				'format' => 'd.m.Y',
				'class' => 'dw-col-date',
			],
			[
				'name' => 'total',
				'label' => 'QUOTES_TOTAL',
				'data_label' => 'QUOTES_TOTAL',
				'type' => 'text',
				'field' => 'total',
				'class' => 'dw-col-total',
			],
			[
				'name' => 'state',
				'label' => 'QUOTES_STATE',
				'data_label' => 'QUOTES_STATE',
				'type' => 'state_badge',
				'field' => 'state',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function ($quote) {
					return !in_array((string)$quote->state, ['105', '106'], true);
				},
				'badge_map' => [
					'101' => 'dw-badge--warning',
					'103' => 'dw-badge--danger',
					'104' => 'dw-badge--success',
					'105' => 'dw-badge--success',
					'106' => 'dw-badge--success',
				],
			],
			[
				'name' => 'pin',
				'label' => '',
				'type' => 'pin',
				'class' => 'dw-col-pin',
				'function' => 'pin',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					[
						'name' => 'view',
						'show' => function ($quote) {
							return in_array((string)$quote->state, ['105', '106'], true);
						},
					],
					[
						'name' => 'edit',
						'show' => function ($quote) {
							return !in_array((string)$quote->state, ['105', '106'], true);
						},
					],
					['name' => 'copy'],
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}
}
