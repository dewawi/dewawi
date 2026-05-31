<?php

class Admin_Model_List_Clients extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_CLIENT_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'parentid',
				'label' => 'ADMIN_PARENT_ID',
				'type' => 'text',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'company',
				'label' => 'ADMIN_COMPANY',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'address',
				'label' => 'ADMIN_ADDRESS',
				'type' => 'text',
			],
			[
				'name' => 'postcode',
				'label' => 'ADMIN_POSTCODE',
				'type' => 'text',
			],
			[
				'name' => 'city',
				'label' => 'ADMIN_CITY',
				'type' => 'text',
			],
			[
				'name' => 'country',
				'label' => 'ADMIN_COUNTRY',
				'type' => 'text',
			],
			[
				'name' => 'email',
				'label' => 'ADMIN_EMAIL',
				'type' => 'text',
			],
			[
				'name' => 'website',
				'label' => 'ADMIN_WEBSITE',
				'type' => 'text',
			],
			[
				'name' => 'activated',
				'label' => 'ADMIN_ACTIVATED',
				'type' => 'text',
				'show' => function ($client, $column, $list) {
					return $list->hasPermission('admin');
				},
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					[
						'name' => 'copy',
						'show' => function ($client, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($client, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
				],
			],
		];
	}
}
