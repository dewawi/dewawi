<?php

class Items_Model_Entity_Stock
{
	public static function costExpression(): Zend_Db_Expr
	{
		return new Zend_Db_Expr(
			'CASE
				WHEN i.parentid > 0
					AND (i.cost IS NULL OR i.cost = 0)
					THEN COALESCE(p.cost, 0)
				ELSE COALESCE(i.cost, 0)
			END'
		);
	}

	public static function stockValueExpression(): Zend_Db_Expr
	{
		return new Zend_Db_Expr(
			'COALESCE(s.quantity, 0) * ('
				. (string)self::costExpression()
				. ')'
		);
	}

	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Itemstock',
			'alias' => 's',

			'tags' => false,

			'columns' => [
				'*',
				'available' => new Zend_Db_Expr(
					's.quantity - s.reserved'
				),
				'cost' => self::costExpression(),
				'stockvalue' => self::stockValueExpression(),
			],

			'joins' => [
				[
					'table' => 'item',
					'alias' => 'i',
					'on' => 's.itemid = i.id'
						. ' AND s.clientid = i.clientid'
						. ' AND i.deleted = 0',
					'columns' => [
						'sku AS sku',
						'title AS itemtitle',
					],
				],
				[
					'table' => 'item',
					'alias' => 'p',
					'on' => 'i.parentid = p.id'
						. ' AND i.clientid = p.clientid',
					'columns' => [],
					'type' => 'left',
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
				'available' => new Zend_Db_Expr('available'),
				'incoming',
				'cost' => new Zend_Db_Expr('cost'),
				'stockvalue' => new Zend_Db_Expr('stockvalue'),
				'modified',
			],
		];
	}
}
