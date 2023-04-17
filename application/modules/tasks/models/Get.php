<?php

class Tasks_Model_Get
{
	public function tasks($params, $options, $flashMessenger)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['tasks'];
		}

		$tasksDb = new Tasks_Model_DbTable_Task();

		$columns = array('p.title', 'p.customerid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories'], 'c');
		if($params['states']) $query = $queryHelper->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $queryHelper->getQueryCountry($query, $params['country'], $options['countries'], $schema);
		if($params['paymentstatus']) $query = $queryHelper->getQueryPaymentstatus($query, $params['paymentstatus'], $schema);
		if($params['daterange']) {
			$params['from'] = date('Y-m-d', strtotime($params['from']));
			$params['to'] = date('Y-m-d', strtotime($params['to']));
			$query = $queryHelper->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		}
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$tasks = $tasksDb->fetchAll(
			$tasksDb->select()
				->setIntegrityCheck(false)
				->from(array('p' => 'task'))
				->join(array('c' => 'contact'), 'p.customerid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order(array('pinned desc', $params['order'].' '.$params['sort']))
				->limit($params['limit'])
		);
		if(!count($tasks) && $params['keyword']) {
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], $columns);
			$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
			$query = $queryHelper->getQueryDeleted($query, $schema);
			$tasks = $tasksDb->fetchAll(
				$tasksDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'task'))
					->join(array('c' => 'contact'), 'p.customerid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
					->limit($params['limit'])
			);
		}
		if(!count($tasks)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$tasks->subtotal = 0;
		$tasks->total = 0;
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($tasks as $task) {
			$tasks->subtotal += $task->subtotal;
			$tasks->total += $task->total;
			$currency = $currencyHelper->setCurrency($currency, $task->currency, 'USE_SYMBOL');
			if($task->prepayment == 0.0000) $task->prepayment = 0;
			else {
				//$task->stillToPay = $currency->toCurrency($tasks->subtotal-$task->prepayment);
				$task->prepayment = $currency->toCurrency($task->prepayment);
			}
			if($task->total == 0.0000) $task->total = 0;
			else $task->total = $currency->toCurrency($task->total);
			if($task->prepaymenttotal == 0.0000) $task->prepaymenttotal = 0;
			else $task->prepaymenttotal = $currency->toCurrency($task->prepaymenttotal);
			if($task->creditnotetotal == 0.0000) $task->creditnotetotal = 0;
			else $task->creditnotetotal = $currency->toCurrency($task->creditnotetotal);
		}
		$tasks->total = $currency->toCurrency($tasks->total);

		return $tasks;
	}
}
