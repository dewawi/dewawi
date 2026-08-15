<?php

class Items_Model_Entity_Ledger
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Ledger',
			'alias' => 'l',

			'joins' => [
				[
					'table' => 'item',
					'alias' => 'i',
					'on' => 'l.itemid = i.id',
					'columns' => [
						'sku AS sku',
						'title AS itemtitle',
					],
				],
				[
					'table' => 'warehouse',
					'alias' => 'w',
					'on' => 'l.warehouseid = w.id',
					'columns' => [
						'code AS warehousecode',
						'title AS warehousetitle',
					],
				],
			],

			'search' => [
				'i.sku',
				'i.title',
				'w.code',
				'w.title',
				'type',
				'reason',
				'comment',
			],

			'filters' => [
				'quantity' => [
					'type' => 'quantity',
					'column' => 'quantity',
				],
			],

			'orders' => [
				'id',
				'ledgerdate',
				'sku' => 'i.sku',
				'itemtitle' => 'i.title',
				'warehousecode' => 'w.code',
				'warehousetitle' => 'w.title',
				'type',
				'reason',
				'quantity',
				'created',
			],
		];
	}
}
