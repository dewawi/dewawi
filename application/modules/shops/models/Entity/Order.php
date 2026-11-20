<?php

class Shops_Model_Entity_Order
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Shops_Model_DbTable_Order', 'alias' => 'o', 'module' => 'shops', 'controller' => 'order', 'clientModule' => 'shops', 'tags' => false,
			'columns' => ['id', 'orderid', 'orderdate', 'created', 'customername', 'email', 'shopid', 'total', 'grosstotal', 'taxes', 'currency', 'status', 'salesorderid', 'salesclientid', 'snapshot'],
			'joins' => [['type' => 'left', 'table' => 'shop', 'alias' => 's', 'clientModule' => 'shops', 'on' => 'o.shopid = s.id', 'columns' => ['website_title' => 'title']]],
			'search' => ['o.orderid', 'o.customername', 'o.email', 'o.snapshot', 's.title'],
			'filters' => ['shopid' => ['type' => 'equals', 'column' => 'shopid'], 'status' => ['type' => 'callback', 'callback' => [self::class, 'filterStatus']]],
			'orders' => ['orderid', 'orderdate', 'created', 'modified', 'customername', 'total', 'grosstotal', 'status', 'website_title' => 's.title'],
		];
	}

	public static function filterStatus($select, $value): void
	{
		if (!is_string($value)) $select->where('1 = 0');
		elseif ($value === 'unknown') $select->where('o.status IS NULL');
		elseif (isset(Shops_Model_DbTable_Order::STATUSES[$value])) $select->where('o.status = ?', $value);
		else $select->where('1 = 0');
	}
}
