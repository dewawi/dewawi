<?php

class Items_Model_Get
{
	public function items($params, $options, $row = false)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$itemsDb = new Items_Model_DbTable_Item();

		$columns = array('title', 'sku', 'manufacturersku', 'description');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories']);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		if(isset($params['quantity']) && $params['quantity']) $query = $queryHelper->getQueryQuantity($query, $params['quantity']);
		$query = $queryHelper->getQueryDeleted($query);

		// Get total records count
		if($params['tagid']) {
			$records = $itemsDb->fetchAll(
				$itemsDb->select()
					->where($query ? $query : 0)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
			);
		} else {
			$records = $itemsDb->fetchAll(
				$itemsDb->select()
					->where($query ? $query : 0)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
			);
		}

		//Pagination
		$params['offset'] = 0;
		if($params['limit'] == 0) $params['limit'] = 1000;
		if($params['page']) $params['offset'] = ($params['page']-1)*$params['limit'];

		// Get records
		if($params['tagid']) {
			$items = $itemsDb->fetchAll(
				$itemsDb->select()
					->where($query ? $query : 0)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
					->limit($params['limit'], $params['offset'])
			);
		} else {
			$items = $itemsDb->fetchAll(
				$itemsDb->select()
					->where($query ? $query : 0)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
					->limit($params['limit'], $params['offset'])
			);
		}

		if($row == false) {
			$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
			$currency = $currencyHelper->getCurrency();
			foreach($items as $item) {
				if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
				$currency = $currencyHelper->setCurrency($currency, $item->currency, 'USE_SYMBOL');
				$item->cost = $currency->toCurrency($item->cost);
				$item->price = $currency->toCurrency($item->price);

				// format quantity for display
				$locale = Zend_Registry::get('Zend_Locale');
				if(isset($item->quantity)) {
					$locale = Zend_Registry::get('Zend_Locale');
					$item->quantity = Zend_Locale_Format::toNumber(
						$item->quantity,
						array(
							'precision' => 2,
							'locale'    => $locale
						)
					);
				}
			}
		}

		return array($items, count($records));
	}

	public function ledger($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$ledgerDb = new Items_Model_DbTable_Ledger();

		$columns = array('comment', 'sku', 'contactid');

		$query = '';
		$schema = 'in';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories']);
		$query = $queryHelper->getQueryClient($query, $client['id'], 'i');

		$ledgers = $ledgerDb->fetchAll(
			$ledgerDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'ledger'))
				->join(array('i' => 'item'), $schema.'.sku = i.sku', array('catid', 'title'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($ledgers as $ledger) {
			if(strlen($ledger->comment) > 43) $ledger->comment = substr($ledger->comment, 0, 40).'...';
			$currency = $currencyHelper->setCurrency($currency, $ledger->currency, 'USE_SYMBOL');
			$ledger->price = $currency->toCurrency($ledger->price);
			$ledger->total = $currency->toCurrency($ledger->total);
		}

		return $ledgers;
	}

	public function pricerules($params, $options)
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
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories']);
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

	public function tags($module, $controller, $id = null) {
		if($id) {
			$client = Zend_Registry::get('Client');
			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tags = $tagEntityDb->fetchAll(
				$tagEntityDb->select()
					->setIntegrityCheck(false)
					->from(array('t' => 'tagentity'))
					->join(array('tag' => 'tag'), 't.tagid = tag.id', array('title as tag', 'module', 'controller'))
					->group('t.id')
					->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.clientid = "'.$client['id'].'") AND (t.deleted = 0)')
					//->order($order.' '.$params['sort'])
					//->limit($params['limit'], $params['offset'])
			);
			$tags = $tags->toArray();
		} else {
			$tagsDb = new Application_Model_DbTable_Tag();
			$tags = $tagsDb->getTags($module, $controller);
		}

		return $tags;
	}

	public function itemlists($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['items'];
		}

		$itemlistDb = new Items_Model_DbTable_Itemlist();

		$columns = array('title', 'description');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories']);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		if($params['tagid']) {
			$items = $itemlistDb->fetchAll(
				$itemlistDb->select()
					->where($query ? $query : 0)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		} else {
			$items = $itemlistDb->fetchAll(
				$itemlistDb->select()
					->where($query ? $query : 0)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}

		return $items;
	}
}
