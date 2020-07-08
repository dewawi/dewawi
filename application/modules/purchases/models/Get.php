<?php

class Purchases_Model_Get
{
	public function quoterequests($params, $categories, $clientid, $helper, $currency, $flashMessenger)
	{
		$quoterequestsDb = new Purchases_Model_DbTable_Quoterequest();

		$columns = array('q.title', 'q.quoterequestid', 'q.contactid', 'q.billingname1', 'q.billingname2', 'q.billingdepartment', 'q.billingstreet', 'q.billingpostcode', 'q.billingcity', 'q.shippingname1', 'q.shippingname2', 'q.shippingdepartment', 'q.shippingstreet', 'q.shippingpostcode', 'q.shippingcity');

		$query = '';
		$schema = 'q';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		if($query) {
			$query .= ' AND q.clientid = '.$clientid;
			$query .= ' AND q.deleted = 0';
		} else {
			$query = 'q.clientid = '.$clientid;
			$query .= ' AND q.deleted = 0';
        }

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
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    if($query) {
			    $query .= ' AND q.clientid = '.$clientid;
			    $query .= ' AND q.deleted = 0';
		    } else {
			    $query = 'q.clientid = '.$clientid;
			    $query .= ' AND q.deleted = 0';
            }
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
		    if(!count($quoterequests)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
		}

		$quoterequests->subtotal = 0;
		$quoterequests->total = 0;
		foreach($quoterequests as $quoterequest) {
			$quoterequests->subtotal += $quoterequest->subtotal;
			$quoterequests->total += $quoterequest->total;
			$quoterequest->subtotal = $currency->toCurrency($quoterequest->subtotal);
			$quoterequest->taxes = $currency->toCurrency($quoterequest->taxes);
			$quoterequest->total = $currency->toCurrency($quoterequest->total);
		}
		$quoterequests->subtotal = $currency->toCurrency($quoterequests->subtotal);
		$quoterequests->total = $currency->toCurrency($quoterequests->total);

		return $quoterequests;
	}

	public function purchaseorders($params, $categories, $clientid, $helper, $currency, $flashMessenger)
	{
		$purchaseordersDb = new Purchases_Model_DbTable_Purchaseorder();

		$columns = array('p.title', 'p.purchaseorderid', 'p.contactid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		if($query) {
			$query .= ' AND p.clientid = '.$clientid;
			$query .= ' AND p.deleted = 0';
		} else {
			$query = 'p.clientid = '.$clientid;
			$query .= ' AND p.deleted = 0';
        }

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
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    if($query) {
			    $query .= ' AND q.clientid = '.$clientid;
			    $query .= ' AND q.deleted = 0';
		    } else {
			    $query = 'q.clientid = '.$clientid;
			    $query .= ' AND q.deleted = 0';
            }
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
		    if(!count($purchaseorders)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
		}

		$purchaseorders->subtotal = 0;
		$purchaseorders->total = 0;
		foreach($purchaseorders as $purchaseorder) {
			$purchaseorders->subtotal += $purchaseorder->subtotal;
			$purchaseorders->total += $purchaseorder->total;
			$purchaseorder->subtotal = $currency->toCurrency($purchaseorder->subtotal);
			$purchaseorder->taxes = $currency->toCurrency($purchaseorder->taxes);
			$purchaseorder->total = $currency->toCurrency($purchaseorder->total);
		}
		$purchaseorders->subtotal = $currency->toCurrency($purchaseorders->subtotal);
		$purchaseorders->total = $currency->toCurrency($purchaseorders->total);

		return $purchaseorders;
	}
}
