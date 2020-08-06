<?php

class Purchases_Model_Get
{
	public function quoterequests($params, $categories, $flashMessenger)
	{
		$client = Zend_Registry::get('Client');
        if($client['parentid']) {
            $client['id'] = $client['modules']['purchases'];
        }

		$quoterequestsDb = new Purchases_Model_DbTable_Quoterequest();

		$columns = array('q.title', 'q.quoterequestid', 'q.contactid', 'q.billingname1', 'q.billingname2', 'q.billingdepartment', 'q.billingstreet', 'q.billingpostcode', 'q.billingcity', 'q.shippingname1', 'q.shippingname2', 'q.shippingdepartment', 'q.shippingstreet', 'q.shippingpostcode', 'q.shippingcity');

		$query = '';
		$schema = 'q';
        $queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $queryHelper->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $queryHelper->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $queryHelper->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$quoterequests = $quoterequestsDb->fetchAll(
			$quoterequestsDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'quoterequest'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($quoterequests) && $params['keyword']) {
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		    $query = $queryHelper->getQueryDeleted($query, $schema);
			$quoterequests = $quoterequestsDb->fetchAll(
				$quoterequestsDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'quoterequest'))
				    ->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($quoterequests)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$quoterequests->subtotal = 0;
		$quoterequests->total = 0;
        $currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
        $currency = $currencyHelper->getCurrency();
		foreach($quoterequests as $quoterequest) {
			$quoterequests->subtotal += $quoterequest->subtotal;
			$quoterequests->total += $quoterequest->total;
		    $currency = $currencyHelper->setCurrency($currency, $quoterequest->currency, 'USE_SYMBOL');
			$quoterequest->subtotal = $currency->toCurrency($quoterequest->subtotal);
			$quoterequest->taxes = $currency->toCurrency($quoterequest->taxes);
			$quoterequest->total = $currency->toCurrency($quoterequest->total);
		}
		$quoterequests->subtotal = $currency->toCurrency($quoterequests->subtotal);
		$quoterequests->total = $currency->toCurrency($quoterequests->total);

		return $quoterequests;
	}

	public function purchaseorders($params, $categories, $flashMessenger)
	{
		$client = Zend_Registry::get('Client');
        if($client['parentid']) {
            $client['id'] = $client['modules']['purchases'];
        }

		$purchaseordersDb = new Purchases_Model_DbTable_Purchaseorder();

		$columns = array('p.title', 'p.purchaseorderid', 'p.contactid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
        $queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $queryHelper->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $queryHelper->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $queryHelper->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$purchaseorders = $purchaseordersDb->fetchAll(
			$purchaseordersDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'purchaseorder'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($purchaseorders) && $params['keyword']) {
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		    $query = $queryHelper->getQueryDeleted($query, $schema);
			$purchaseorders = $purchaseordersDb->fetchAll(
				$purchaseordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'purchaseorder'))
				    ->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($purchaseorders)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$purchaseorders->subtotal = 0;
		$purchaseorders->total = 0;
        $currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
        $currency = $currencyHelper->getCurrency();
		foreach($purchaseorders as $purchaseorder) {
			$purchaseorders->subtotal += $purchaseorder->subtotal;
			$purchaseorders->total += $purchaseorder->total;
		    $currency = $currencyHelper->setCurrency($currency, $purchaseorder->currency, 'USE_SYMBOL');
			$purchaseorder->subtotal = $currency->toCurrency($purchaseorder->subtotal);
			$purchaseorder->taxes = $currency->toCurrency($purchaseorder->taxes);
			$purchaseorder->total = $currency->toCurrency($purchaseorder->total);
		}
		$purchaseorders->subtotal = $currency->toCurrency($purchaseorders->subtotal);
		$purchaseorders->total = $currency->toCurrency($purchaseorders->total);

		return $purchaseorders;
	}
}
