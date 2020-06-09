<?php

class Items_Model_Get
{
	public function items($params, $categories, $clientid, $helper, $currency)
	{
		$itemsDb = new Items_Model_DbTable_Item();

		$columns = array('title', 'sku', 'description');

		$query = '';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories);
		if($query) {
			$query .= ' AND clientid = '.$clientid;
			$query .= ' AND deleted = 0';
		} else {
			$query = 'clientid = '.$clientid;
			$query .= ' AND deleted = 0';
        }


		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		foreach($items as $item) {
			if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
			$item->cost = $currency->toCurrency($item->cost);
			$item->price = $currency->toCurrency($item->price);
		}

		return $items;
	}
}
