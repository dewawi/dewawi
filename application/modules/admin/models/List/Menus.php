<?php

class Admin_Model_List_Menus extends DEEC_List
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
				'name' => 'shop',
				'label' => 'ADMIN_SHOP',
				'type' => 'text',
				'field' => 'shoptitle',
				'class' => 'dw-col-shop',
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
				'fallback_field' => 'id',
			],
			[
				'name' => 'position',
				'label' => 'ADMIN_POSITION',
				'type' => 'text',
				'class' => 'dw-col-position',
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
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
					['name' => 'sortup'],
					['name' => 'sortdown'],
				],
			],
		];
	}
}
