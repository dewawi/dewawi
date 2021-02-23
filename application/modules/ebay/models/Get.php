<?php

class Ebay_Model_Get
{
	public function accounts($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['ebay'];
		}

		$accountsDb = new Ebay_Model_DbTable_Account();

		$columns = array('userid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$accounts = $accountsDb->fetchAll(
			$accountsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $accounts;
	}

	public function listings($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['ebay'];
		}

		$listingsDb = new Ebay_Model_DbTable_Listing();

		$columns = array('e.accountid', 'e.itemid', 'i.sku');

		$query = '';
		$schema = 'e';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if(isset($params['accountid'])) $query = $queryHelper->getQueryAccountID($query, $params['accountid']);
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$listings = $listingsDb->fetchAll(
			$listingsDb->select()
				->setIntegrityCheck(false)
				->from(array('e' => 'ebaylisting'))
				->join(array('i' => 'item'), 'i.id = e.itemid', array('sku', 'title', 'ebaytitle'))
				->group($schema.'.id')
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $listings;
	}

	public function orders($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['ebay'];
		}

		$ordersDb = new Ebay_Model_DbTable_Order();

		$columns = array('accountid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$orders = $ordersDb->fetchAll(
			$ordersDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $orders;
	}

	public function items($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['ebay'];
		}

		$itemsDb = new Ebay_Model_DbTable_Item();

		$columns = array('itemid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
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
			//if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
			//$currency = $currencyHelper->setCurrency($currency, $item->currency, 'USE_SYMBOL');
			//$item->cost = $currency->toCurrency($item->cost);
			//$item->price = $currency->toCurrency($item->price);
		}

		return $items;
	}
}
