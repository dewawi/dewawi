<?php

class Sales_Model_List_Creditnotes extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'creditnoteid',
				'label' => 'CREDIT_NOTES_CREDIT_NOTE_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'CREDIT_NOTES_TITLE',
				'type' => 'link',
				'fallback_field' => 'id',
			],
			[
				'name' => 'contact',
				'label' => 'CREDIT_NOTES_CONTACT',
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
				'label' => 'CREDIT_NOTES_BILLING_ADDRESS',
				'type' => 'address',
				'fields' => [
					'billingstreet',
					'billingpostcode',
					'billingcity',
				],
			],
			[
				'name' => 'notes',
				'label' => 'CREDIT_NOTES_NOTES',
				'type' => 'editable_note',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'creditnotedate',
				'label' => 'CREDIT_NOTES_CREDIT_NOTE_DATE',
				'type' => 'date',
				'format' => 'd.m.Y',
			],
			[
				'name' => 'total',
				'label' => 'CREDIT_NOTES_TOTAL',
			],
			[
				'name' => 'state',
				'label' => 'CREDIT_NOTES_STATE',
				'type' => 'state_badge',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function ($item, $element, $list) {
					return !$list->isReadonly($item);
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
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}
}
