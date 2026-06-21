<?php

class Admin_Model_List_Pages extends DEEC_List
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
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
				'fallback_field' => 'id',
			],
			[
				'name' => 'parentid',
				'label' => 'ADMIN_PARENT_CATEGORY',
				'type' => 'text',
				'class' => 'dw-col-parentid',
			],
			[
				'name' => 'shop',
				'label' => 'ADMIN_SHOP',
				'type' => 'text',
				'field' => 'shoptitle',
				'class' => 'dw-col-shop',
			],
			[
				'name' => 'ordering',
				'label' => 'ADMIN_ORDERING',
				'type' => 'text',
				'class' => 'dw-col-ordering',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					['name' => 'copy'],
					['name' => 'delete'],
					['name' => 'sortup'],
					['name' => 'sortdown'],
				],
			],
		];
	}
}
