<?php

class Admin_Model_List_Warehouses extends DEEC_List
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
				'label' => 'ADMIN_CODE',
				'type' => 'text',
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'activated',
				'label' => 'ADMIN_ACTIVATED',
				'type' => 'checkbox',
			],
			[
				'name' => 'default',
				'label' => 'ADMIN_DEFAULT',
				'type' => 'checkbox',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}
}
