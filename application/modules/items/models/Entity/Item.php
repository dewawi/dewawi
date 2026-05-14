<?php

class Items_Model_Entity_Item
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Item',
			'alias' => 'i',

			'search' => [
				'title',
				'sku',
				'manufacturersku',
				'description',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
				],
				'quantity' => [
					'type' => 'quantity',
					'column' => 'quantity',
				],
			],

			'orders' => [
				'title',
				'sku',
				'manufacturersku',
				'created',
				'modified',
			],

			'normalizers' => [
				'description' => [
					'type' => 'truncate',
					'length' => 43,
				],
			],
		];
	}
}
