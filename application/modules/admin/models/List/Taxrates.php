<?php

class Admin_Model_List_Taxrates extends DEEC_List
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
				'name' => 'rate',
				'label' => 'ADMIN_RATE',
				'type' => 'text',
				'class' => 'dw-col-rate',
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
