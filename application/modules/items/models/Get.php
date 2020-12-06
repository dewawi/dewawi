<?php

class Items_Model_Get
{
	public function items($params, $categories)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$itemsDb = new Items_Model_DbTable_Item();

		$columns = array('title', 'sku', 'description');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($items as $item) {
			if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
			$currency = $currencyHelper->setCurrency($currency, $item->currency, 'USE_SYMBOL');
			$item->cost = $currency->toCurrency($item->cost);
			$item->price = $currency->toCurrency($item->price);
		}

		return $items;
	}

	public function inventory($params, $categories)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$inventoryDb = new Items_Model_DbTable_Inventory();

		$columns = array('comment', 'sku', 'contactid');

		$query = '';
		$schema = 'in';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories);

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

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($inventories as $inventory) {
			if(strlen($inventory->comment) > 43) $inventory->comment = substr($inventory->comment, 0, 40).'...';
			$currency = $currencyHelper->setCurrency($currency, $inventory->currency, 'USE_SYMBOL');
			$inventory->price = $currency->toCurrency($inventory->price);
			$inventory->total = $currency->toCurrency($inventory->total);
		}

		return $inventories;
	}

	public function pricerules($params, $categories)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$pricerulesDb = new Items_Model_DbTable_Pricerule();

		$columns = array('title');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		//if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$pricerules = $pricerulesDb->fetchAll(
			$pricerulesDb->select()
				->where($query ? $query : 0)
				//->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($pricerules as $pricerule) {
			$currency = $currencyHelper->setCurrency($currency, $pricerule->currency);
			$pricerule['amount'] = $currency->toCurrency($pricerule['amount']);
			$datefrom = new Zend_Date($pricerule['datefrom']);
			if($pricerule['datefrom']) $pricerule['datefrom'] = $datefrom->get('dd.MM.yyyy');
			$dateto = new Zend_Date($pricerule['dateto']);
			if($pricerule['dateto']) $pricerule['dateto'] = $dateto->get('dd.MM.yyyy');
		}

		return $pricerules;
	}
}
