<?php

class Items_Model_Entity_Inventory
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Inventory',
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
					'categoryType' => 'item',
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
