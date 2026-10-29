<?php

class Items_Model_Entity_Stockplanning
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Stockplanning',
			'alias' => 'sp',
			'tags' => false,
			'columns' => [
				'*',
				'remaining' => new Zend_Db_Expr('sp.quantity - sp.fulfilled'),
			],
			'joins' => [
				[
					'type' => 'left',
					'table' => 'item',
					'clientModule' => 'items',
					'alias' => 'i',
					'on' => 'sp.itemid = i.id AND sp.clientid = i.clientid AND i.deleted = 0',
					'columns' => ['sku AS sku', 'title AS itemtitle'],
				],
				[
					'type' => 'left',
					'table' => 'warehouse',
					'clientModule' => 'items',
					'alias' => 'w',
					'on' => 'sp.warehouseid = w.id AND sp.clientid = w.clientid',
					'columns' => ['title AS warehousetitle'],
				],
			],
			'search' => ['i.sku', 'i.title', 'w.title', 'sp.externalnumber', 'sp.comment'],
			'filters' => [
				'type' => ['type' => 'equals', 'column' => 'type'],
				'status' => ['type' => 'equals', 'column' => 'status'],
				'warehouseid' => ['type' => 'equals', 'column' => 'warehouseid'],
				'planningdate' => ['type' => 'equals', 'column' => 'planningdate'],
			],
			'orders' => [
				'id', 'planningdate', 'type', 'status',
				'sku' => 'i.sku',
				'itemtitle' => 'i.title',
				'warehousetitle' => 'w.title',
				'quantity', 'fulfilled',
				'remaining' => new Zend_Db_Expr('remaining'),
				'modified',
			],
		];
	}
}
