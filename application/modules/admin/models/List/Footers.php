<?php

class Admin_Model_List_Footers extends DEEC_List
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
				'name' => 'templateid',
				'label' => 'ADMIN_TEMPLATE_ID',
				'type' => 'text',
			],
			[
				'name' => 'column',
				'label' => 'ADMIN_COLUMN',
				'type' => 'text',
			],
			[
				'name' => 'text',
				'label' => 'ADMIN_TEXT',
				'type' => 'editable_note',
				'field' => 'text',
				'editable_name' => 'text',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'width',
				'label' => 'ADMIN_WIDTH',
				'type' => 'text',
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
