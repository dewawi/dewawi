<?php

class Admin_Model_List_Users extends DEEC_List
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
				'name' => 'username',
				'label' => 'ADMIN_USER_NAME',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'password',
				'label' => 'ADMIN_PASSWORD',
				'type' => 'callback',
				'class' => 'dw-col-password',
				'callback' => function () {
					return '******';
				},
			],
			[
				'name' => 'email',
				'label' => 'ADMIN_EMAIL',
				'type' => 'text',
				'class' => 'dw-col-email',
			],
			[
				'name' => 'activated',
				'label' => 'ADMIN_ACTIVATED',
				'type' => 'text',
				'class' => 'dw-col-activated',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					[
						'name' => 'copy',
						'show' => function ($user, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($user, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
				],
			],
		];
	}
}
