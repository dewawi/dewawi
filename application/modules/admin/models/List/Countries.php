<?php

class Admin_Model_List_Countries extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'code',
				'label' => 'ADMIN_COUNTRY_CODE',
				'type' => 'text',
			],
			[
				'name' => 'name',
				'label' => 'ADMIN_COUNTRY',
				'type' => 'text',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}
}
