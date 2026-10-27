<?php

class Items_Model_Entity_Itemlist
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Itemlist',
			'alias' => 'il',

			'search' => [
				'title',
				'subtitle',
				'description',
			],

			'orders' => [
				'title',
				'created',
				'modified',
			],

			'normalizers' => [
				'description' => [
					'type' => 'truncate',
					'length' => 120,
				],
			],

			'tags' => false,
		];
	}
}
