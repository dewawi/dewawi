<?php

class Admin_Model_Entity_Category
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Category',
			'alias' => 'c',

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
