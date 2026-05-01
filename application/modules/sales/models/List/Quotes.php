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
			],
			[
				'name' => 'state',
				'label' => 'QUOTES_STATE',
				'type' => 'state_badge',
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
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
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
