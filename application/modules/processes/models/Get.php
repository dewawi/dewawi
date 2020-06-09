<?php

class Processes_Model_Get
{
	public function processes($params, $categories, $clientid, $helper, $currency)
	{
		$processesDb = new Processes_Model_DbTable_Process();

		$columns = array('p.title', 'p.customerid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['paymentstatus']) $query = $helper->Query->getQueryPaymentstatus($query, $params['paymentstatus'], $schema);
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

		if($params['catid']) {
			$processes = $processesDb->fetchAll(
				$processesDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'process'))
					->join(array('c' => 'contact'), 'p.customerid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($processes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$processes = $processesDb->fetchAll(
					$processesDb->select()
						->setIntegrityCheck(false)
						->from(array('p' => 'process'))
						->join(array('c' => 'contact'), 'p.customerid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$processes = $processesDb->fetchAll(
				$processesDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'process'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($processes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$processes = $processesDb->fetchAll(
					$processesDb->select()
						->setIntegrityCheck(false)
						->from(array('p' => 'process'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$processes->subtotal = 0;
		$processes->total = 0;
		foreach($processes as $process) {
			$processes->subtotal += $process->subtotal;
			$processes->total += $process->total;
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
