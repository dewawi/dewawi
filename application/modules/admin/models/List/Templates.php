<?php

class Admin_Model_List_Templates extends DEEC_List
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
				'name' => 'description',
				'label' => 'ADMIN_NAME',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'logo',
				'label' => 'ADMIN_LOGO',
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
				'class' => 'dw-col-activated',
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
