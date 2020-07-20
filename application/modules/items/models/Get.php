<?php

class Items_Model_Get
{
	public function items($params, $categories, $clientid, $helper)
	{
		$itemsDb = new Items_Model_DbTable_Item();

		$columns = array('title', 'sku', 'description');

		$query = '';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories);
		$query = $helper->Query->getQueryClient($query, $clientid);
		$query = $helper->Query->getQueryDeleted($query);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

        $currency = $helper->Currency->getCurrency();
		foreach($items as $item) {
			if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
		    $currency = $helper->Currency->setCurrency($currency, $item->currency, 'USE_SYMBOL');
			$item->cost = $currency->toCurrency($item->cost);
			$item->price = $currency->toCurrency($item->price);
		}

		return $items;
	}

	public function inventory($params, $categories, $clientid, $helper)
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

        $currency = $helper->Currency->getCurrency();
		foreach($inventories as $inventory) {
			if(strlen($inventory->comment) > 43) $inventory->comment = substr($inventory->comment, 0, 40).'...';
		    $currency = $helper->Currency->setCurrency($currency, $inventory->currency, 'USE_SYMBOL');
			$inventory->price = $currency->toCurrency($inventory->price);
			$inventory->total = $currency->toCurrency($inventory->total);
		}

		return $inventories;
	}

	public function pricerules($params, $categories, $clientid, $helper)
	{
		$pricerulesDb = new Items_Model_DbTable_Pricerule();

		$columns = array('title');

		$query = '';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		//if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories);
		$query = $helper->Query->getQueryClient($query, $clientid);
		$query = $helper->Query->getQueryDeleted($query);

		$pricerules = $pricerulesDb->fetchAll(
			$pricerulesDb->select()
				->where($query ? $query : 0)
				//->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

        $currency = $helper->Currency->getCurrency();
		foreach($pricerules as $pricerule) {
		    $currency = $helper->Currency->setCurrency($currency, $pricerule->currency, 'USE_SYMBOL');
			$pricerule['amount'] = $currency->toCurrency($pricerule['amount']);
            $from = new Zend_Date($pricerule['from']);
            if($pricerule['from'] == '0000-00-00 00:00:00') $pricerule['from'] = '';
            else $pricerule['from'] = $from->get('dd.MM.yyyy');
            $to = new Zend_Date($pricerule['to']);
            if($pricerule['to'] == '0000-00-00 00:00:00') $pricerule['to'] = '';
            else $pricerule['to'] = $to->get('dd.MM.yyyy');
		}

		return $pricerules;
	}
}
