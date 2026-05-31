<?php

class Admin_Model_List_Modules extends DEEC_List
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
				'name' => 'name',
				'label' => 'ADMIN_NAME',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'menu',
				'label' => 'ADMIN_MENU',
				'type' => 'editable_note',
				'field' => 'menu',
				'editable_name' => 'menu',
				'empty_label' => 'TOOLBAR_NEW',
				'class' => 'dw-col-menu',
			],
			[
				'name' => 'ordering',
				'label' => 'ADMIN_ORDERING',
				'type' => 'text',
				'class' => 'dw-col-ordering',
			],
			[
				'name' => 'active',
				'label' => 'ADMIN_ACTIVE',
				'type' => 'text',
				'class' => 'dw-col-active',
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
