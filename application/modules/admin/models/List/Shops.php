<?php

class Admin_Model_List_Shops extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_SHOP_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'url',
				'label' => 'ADMIN_URL',
				'type' => 'text',
				'class' => 'dw-col-url',
			],
			[
				'name' => 'logo',
				'label' => 'ADMIN_LOGO',
				'type' => 'text',
				'class' => 'dw-col-logo',
			],
			[
				'name' => 'footer',
				'label' => 'ADMIN_FOOTER',
				'type' => 'text',
				'class' => 'dw-col-footer',
			],
			[
				'name' => 'emailsender',
				'label' => 'ADMIN_EMAIL',
				'type' => 'text',
				'class' => 'dw-col-email',
			],
			[
				'name' => 'timezone',
				'label' => 'ADMIN_TIMEZONE',
				'type' => 'text',
				'class' => 'dw-col-timezone',
			],
			[
				'name' => 'language',
				'label' => 'ADMIN_LANGUAGE',
				'type' => 'text',
				'class' => 'dw-col-language',
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
						'show' => function ($shop, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($shop, $element, $list) {
							return $list->hasPermission('admin');
						},
					],
				],
			],
		];
	}
}
