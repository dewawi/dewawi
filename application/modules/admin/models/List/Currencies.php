<?php

class Admin_Model_List_Currencies extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'code',
				'label' => 'ADMIN_CURRENCY_CODE',
				'type' => 'text',
				'class' => 'dw-col-code',
			],
			[
				'name' => 'name',
				'label' => 'ADMIN_NAME',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'symbol',
				'label' => 'ADMIN_CURRENCY_SYMBOL',
				'type' => 'text',
				'class' => 'dw-col-symbol',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					[
						'name' => 'copy',
						'show' => function ($currency, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($currency, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
				],
			],
		];
	}
}
