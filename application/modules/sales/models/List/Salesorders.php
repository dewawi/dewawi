<?php

class Sales_Model_List_Salesorders extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'salesorderid',
				'label' => 'SALES_ORDERS_SALES_ORDER_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'SALES_ORDERS_TITLE',
				'type' => 'link',
				'fallback_field' => 'id',
			],
			[
				'name' => 'contact',
				'label' => 'SALES_ORDERS_CONTACT',
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
				'label' => 'SALES_ORDERS_BILLING_ADDRESS',
				'type' => 'address',
				'fields' => [
					'billingstreet',
					'billingpostcode',
					'billingcity',
				],
			],
			[
				'name' => 'notes',
				'label' => 'SALES_ORDERS_NOTES',
				'type' => 'editable_note',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'salesorderdate',
				'label' => 'SALES_ORDERS_SALES_ORDER_DATE',
				'type' => 'date',
				'format' => 'd.m.Y',
			],
			[
				'name' => 'total',
				'label' => 'SALES_ORDERS_TOTAL',
			],
			[
				'name' => 'state',
				'label' => 'SALES_ORDERS_STATE',
				'type' => 'state_badge',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function ($salesorder) {
					return !in_array((string)$salesorder->state, ['105', '106'], true);
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
						'show' => function ($salesorder) {
							return in_array((string)$salesorder->state, ['105', '106'], true);
						},
					],
					[
						'name' => 'edit',
						'show' => function ($salesorder) {
							return !in_array((string)$salesorder->state, ['105', '106'], true);
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
