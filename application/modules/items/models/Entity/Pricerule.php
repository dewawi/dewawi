<?php

class Items_Model_Entity_Pricerule
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Pricerule',
			'alias' => 'p',

			'search' => [
				'title',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
					'categoryType' => 'item',
				],
			],

			'orders' => [
				'title',
				'amount',
				'action',
				'datefrom',
				'dateto',
				'priority',
				'activated',
				'created',
				'modified',
			],
		];
	}
}
