<?php

class Admin_Model_List_Pageblocks extends DEEC_List
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
				'name' => 'type',
				'label' => 'ADMIN_TYPE',
				'type' => 'text',
				'field' => 'typelabel',
				'class' => 'dw-col-type',
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'link',
				'fallback_field' => 'type',
				'class' => 'dw-col-title',
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
