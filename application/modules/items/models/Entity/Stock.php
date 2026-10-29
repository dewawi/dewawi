<?php

class Items_Model_Entity_Stock
{
	public static function forecastAvailableExpression(string $date): Zend_Db_Expr
	{
		if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
			throw new InvalidArgumentException('Invalid availability date.');
		}

		return new Zend_Db_Expr(
			'COALESCE(s.quantity, 0)'
			. ' + COALESCE((SELECT SUM(spi.quantity - spi.fulfilled) FROM stockplanning AS spi'
			. ' WHERE spi.itemid = s.itemid AND spi.warehouseid = s.warehouseid'
			. ' AND spi.clientid = s.clientid AND spi.type = "incoming"'
			. ' AND spi.status = "planned" AND spi.dateconfirmed = 1'
			. ' AND spi.planningdate <= ' . "'" . $date . "'"
			. ' AND spi.deleted = 0), 0)'
			. ' - COALESCE((SELECT SUM(spr.quantity - spr.fulfilled) FROM stockplanning AS spr'
			. ' WHERE spr.itemid = s.itemid AND spr.warehouseid = s.warehouseid'
			. ' AND spr.clientid = s.clientid AND spr.type = "reservation"'
			. ' AND spr.status = "planned"'
			. ' AND spr.planningdate <= ' . "'" . $date . "'"
			. ' AND spr.deleted = 0), 0)'
		);
	}

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

	public static function listConfig(?string $availabilityDate = null): array
	{
		$forecast = self::forecastAvailableExpression($availabilityDate ?: date('Y-m-d'));

		return [
			'tableClass' => 'Items_Model_DbTable_Itemstock',
			'alias' => 's',

			'tags' => false,
			'summaryBaseFilters' => ['warehouseid'],

			'columns' => [
				'*',
				'available' => new Zend_Db_Expr(
					'COALESCE(s.quantity, 0) - COALESCE(s.reserved, 0)'
				),
				'forecastavailable' => $forecast,
				'cost' => self::costExpression(),
				'stockvalue' => self::stockValueExpression(),
			],

			'joins' => [
				[
					'table' => 'item',
					'clientModule' => 'items',
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
					'clientModule' => 'items',
					'alias' => 'p',
					'on' => 'i.parentid = p.id'
						. ' AND i.clientid = p.clientid',
					'columns' => [],
					'type' => 'left',
				],
				[
					'table' => 'warehouse',
					'clientModule' => 'items',
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
				'catid' => [
					'type' => 'category',
					'alias' => 'i',
					'column' => 'catid',
					'categoryType' => 'item',
				],
				'manufacturerid' => [
					'type' => 'equals',
					'alias' => 'i',
					'column' => 'manufacturerid',
				],
				'warehouseid' => [
					'type' => 'equals',
					'column' => 'warehouseid',
				],
				'stockstatus' => [
					'type' => 'stockstatus',
					'column' => 'quantity',
				],
				'availabilitystatus' => [
					'type' => 'callback',
					'callback' => static function($select, $value) use ($forecast): void {
						$operators = ['positive' => '>', 'zero' => '=', 'negative' => '<'];
						if(!isset($operators[$value])) {
							$select->where('1 = 0');
							return;
						}
						$select->where('(' . (string)$forecast . ') ' . $operators[$value] . ' ?', 0);
					},
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
				'forecastavailable' => new Zend_Db_Expr('forecastavailable'),
				'cost' => new Zend_Db_Expr('cost'),
				'stockvalue' => new Zend_Db_Expr('stockvalue'),
				'modified',
			],
		];
	}
}
