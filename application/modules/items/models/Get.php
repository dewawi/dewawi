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

	public function inventory($params, $categories, $clientid, $helper, $currency)
	{
		$inventoryDb = new Items_Model_DbTable_Inventory();

		$columns = array('comment', 'sku', 'contactid');

		$query = '';
		$schema = 'in';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories);

	    $inventories = $inventoryDb->fetchAll(
		    $inventoryDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'inventory'))
				->join(array('i' => 'item'), $schema.'.sku = i.sku', array('catid', 'title'))
				->group($schema.'.id')
			    ->where($query ? $query : 1)
			    ->order($params['order'].' '.$params['sort'])
			    ->limit($params['limit'])
	    );

		foreach($inventories as $inventory) {
			if(strlen($inventory->comment) > 43) $inventory->comment = substr($inventory->comment, 0, 40).'...';
			$inventory->price = $currency->toCurrency($inventory->price);
			$inventory->total = $currency->toCurrency($inventory->total);
		}

		return $inventories;
	}
}
