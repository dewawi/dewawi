<?php

class Processes_Model_Get
{
	public function processes($params, $categories, $flashMessenger)
	{
		$client = Zend_Registry::get('Client');
        if($client['parentid']) {
            $client['id'] = $client['modules']['processes'];
        }

		$processesDb = new Processes_Model_DbTable_Process();

		$columns = array('p.title', 'p.customerid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
        $queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $queryHelper->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $queryHelper->getQueryCountry($query, $params['country'], $schema);
		if($params['paymentstatus']) $query = $queryHelper->getQueryPaymentstatus($query, $params['paymentstatus'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $queryHelper->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$processes = $processesDb->fetchAll(
			$processesDb->select()
				->setIntegrityCheck(false)
				->from(array('p' => 'process'))
				->join(array('c' => 'contact'), 'p.customerid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($processes) && $params['keyword']) {
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], $columns);
	        $query = $queryHelper->getQueryClient($query, $client['id'], $schema);
	        $query = $queryHelper->getQueryDeleted($query, $schema);
			$processes = $processesDb->fetchAll(
				$processesDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'process'))
				    ->join(array('c' => 'contact'), 'p.customerid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($processes)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$processes->subtotal = 0;
		$processes->total = 0;
        $currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
        $currency = $currencyHelper->getCurrency();
		foreach($processes as $process) {
			$processes->subtotal += $process->subtotal;
			$processes->total += $process->total;
		    $currency = $currencyHelper->setCurrency($currency, $process->currency, 'USE_SYMBOL');
			if($process->prepayment == 0.0000) $process->prepayment = 0;
			else {
				//$process->stillToPay = $currency->toCurrency($processes->subtotal-$process->prepayment);
				$process->prepayment = $currency->toCurrency($process->prepayment);
			}
			if($process->total == 0.0000) $process->total = 0;
			else $process->total = $currency->toCurrency($process->total);
			if($process->prepaymenttotal == 0.0000) $process->prepaymenttotal = 0;
			else $process->prepaymenttotal = $currency->toCurrency($process->prepaymenttotal);
			if($process->creditnotetotal == 0.0000) $process->creditnotetotal = 0;
			else $process->creditnotetotal = $currency->toCurrency($process->creditnotetotal);
		}
		$processes->total = $currency->toCurrency($processes->total);

		return $processes;
	}
}
