<?php

class Items_Model_Entity_Stock
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Itemstock',
			'alias' => 's',

			'deletedFilter' => false,

			'columns' => [
				'*',
				'available' => new Zend_Db_Expr(
					's.quantity - s.reserved'
				),
				'stockvalue' => new Zend_Db_Expr(
					's.quantity * i.cost'
				),
			],

			'joins' => [
				[
					'table' => 'item',
					'alias' => 'i',
					'on' => 's.itemid = i.id'
						. ' AND s.clientid = i.clientid',
					'columns' => [
						'sku AS sku',
						'title AS itemtitle',
					],
				],
				[
					'table' => 'warehouse',
					'alias' => 'w',
					'on' => 's.warehouseid = w.id'
						. ' AND s.clientid = w.clientid',
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
			],

			'filters' => [
				'warehouseid' => [
					'type' => 'equals',
					'column' => 'warehouseid',
				],
				'quantity' => [
					'type' => 'quantity',
					'column' => 'quantity',
				],
			],

			'orders' => [
				'id',
				'sku' => 'i.sku',
				'itemtitle' => 'i.title',
				'warehousecode' => 'w.code',
				'warehousetitle' => 'w.title',
				'quantity',
				'reserved',
				'available',
				'incoming',
				'modified',
			],
		];
	}
}
