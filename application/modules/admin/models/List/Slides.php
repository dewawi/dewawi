<?php

class Admin_Model_List_Slides extends DEEC_List
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
				'name' => 'shopid',
				'label' => 'ADMIN_SHOP',
				'type' => 'text',
				'class' => 'dw-col-shopid',
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
				'fallback_field' => 'image',
			],
			[
				'name' => 'image',
				'label' => 'ADMIN_IMAGE',
				'type' => 'text',
				'class' => 'dw-col-image',
			],
			[
				'name' => 'url',
				'label' => 'ADMIN_URL',
				'type' => 'text',
				'class' => 'dw-col-url',
			],
			[
				'name' => 'ordering',
				'label' => 'ADMIN_ORDERING',
				'type' => 'text',
				'class' => 'dw-col-ordering',
			],
			[
				'name' => 'activated',
				'label' => 'ADMIN_ACTIVATED',
				'type' => 'checkbox',
				'class' => 'dw-col-activated',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					['name' => 'delete'],
					['name' => 'sortup'],
					['name' => 'sortdown'],
				],
			],
		];
	}
}
