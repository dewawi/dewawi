<?php

class Shops_Model_Get
{
	public function accounts($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$accountsDb = new Shops_Model_DbTable_Account();

		$columns = array('title');

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

	public function items($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$itemsDb = new Shops_Model_DbTable_Item();

		$columns = array('shopid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if(isset($params['shopid'])) $query = $queryHelper->getQueryShopID($query, $params['shopid']);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $items;
	}

	public function orders($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$ordersDb = new Shops_Model_DbTable_Order();

		$columns = array('userid');

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

	/*public function items($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$itemsDb = new Shops_Model_DbTable_Item();

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
	}*/
}
