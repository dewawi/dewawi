<?php

class Items_Model_List_Itemlists extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'title',
				'label' => 'ITEM_LISTS_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'description',
				'label' => 'ITEM_LISTS_DESCRIPTION',
				'type' => 'text',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}
}
