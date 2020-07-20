<?php

class Sales_Model_Get
{
	public function quotes($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$quotesDb = new Sales_Model_DbTable_Quote();

		$columns = array('q.title', 'q.quoteid', 'q.contactid', 'q.billingname1', 'q.billingname2', 'q.billingdepartment', 'q.billingstreet', 'q.billingpostcode', 'q.billingcity', 'q.shippingname1', 'q.shippingname2', 'q.shippingdepartment', 'q.shippingstreet', 'q.shippingpostcode', 'q.shippingcity');

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
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$quotes = $quotesDb->fetchAll(
			$quotesDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'quote'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);


		/*$select = $quotesDb->select()
			->setIntegrityCheck(false)
			->from(array($schema => 'quote'))
			->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
			->group($schema.'.id')
			->where($query ? $query : 1)
			->order($params['order'].' '.$params['sort'])
			->limit($params['limit']);
        error_log($select->__toString());*/


		if(!count($quotes) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$quotes = $quotesDb->fetchAll(
				$quotesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'quote'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($quotes)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$quotes->subtotal = 0;
		$quotes->total = 0;
        $currency = $helper->Currency->getCurrency();
		foreach($quotes as $quote) {
			$quotes->subtotal += $quote->subtotal;
			$quotes->total += $quote->total;
		    $currency = $helper->Currency->setCurrency($currency, $quote->currency, 'USE_SYMBOL');
			$quote->subtotal = $currency->toCurrency($quote->subtotal);
			$quote->taxes = $currency->toCurrency($quote->taxes);
			$quote->total = $currency->toCurrency($quote->total);
		}
		$quotes->subtotal = $currency->toCurrency($quotes->subtotal);
		$quotes->total = $currency->toCurrency($quotes->total);

		return $quotes;
	}

	public function invoices($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$invoicesDb = new Sales_Model_DbTable_Invoice();

		$columns = array('i.title', 'i.invoiceid', 'i.contactid', 'i.billingname1', 'i.billingname2', 'i.billingdepartment', 'i.billingstreet', 'i.billingpostcode', 'i.billingcity', 'i.shippingname1', 'i.shippingname2', 'i.shippingdepartment', 'i.shippingstreet', 'i.shippingpostcode', 'i.shippingcity');

		$query = '';
		$schema = 'i';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$invoices = $invoicesDb->fetchAll(
			$invoicesDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'invoice'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($invoices) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$invoices = $invoicesDb->fetchAll(
				$invoicesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'invoice'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($invoices)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$invoices->subtotal = 0;
		$invoices->total = 0;
        $currency = $helper->Currency->getCurrency();
		foreach($invoices as $invoice) {
			$invoices->subtotal += $invoice->subtotal;
			$invoices->total += $invoice->total;
		    $currency = $helper->Currency->setCurrency($currency, $invoice->currency, 'USE_SYMBOL');
			$invoice->subtotal = $currency->toCurrency($invoice->subtotal);
			$invoice->taxes = $currency->toCurrency($invoice->taxes);
			$invoice->total = $currency->toCurrency($invoice->total);
		}
		$invoices->subtotal = $currency->toCurrency($invoices->subtotal);
		$invoices->total = $currency->toCurrency($invoices->total);

		return $invoices;
	}

	public function salesorders($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$salesordersDb = new Sales_Model_DbTable_Salesorder();

		$columns = array('s.title', 's.salesorderid', 's.contactid', 's.billingname1', 's.billingname2', 's.billingdepartment', 's.billingstreet', 's.billingpostcode', 's.billingcity', 's.shippingname1', 's.shippingname2', 's.shippingdepartment', 's.shippingstreet', 's.shippingpostcode', 's.shippingcity');

		$query = '';
		$schema = 's';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$salesorders = $salesordersDb->fetchAll(
			$salesordersDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'salesorder'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($salesorders) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$salesorders = $salesordersDb->fetchAll(
				$salesordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'salesorder'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($salesorders)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$salesorders->subtotal = 0;
		$salesorders->total = 0;
        $currency = $helper->Currency->getCurrency();
		foreach($salesorders as $salesorder) {
			$salesorders->subtotal += $salesorder->subtotal;
			$salesorders->total += $salesorder->total;
		    $currency = $helper->Currency->setCurrency($currency, $salesorder->currency, 'USE_SYMBOL');
			$salesorder->subtotal = $currency->toCurrency($salesorder->subtotal);
			$salesorder->taxes = $currency->toCurrency($salesorder->taxes);
			$salesorder->total = $currency->toCurrency($salesorder->total);
		}
		$salesorders->subtotal = $currency->toCurrency($salesorders->subtotal);
		$salesorders->total = $currency->toCurrency($salesorders->total);

		return $salesorders;
	}

	public function deliveryorders($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$deliveryordersDb = new Sales_Model_DbTable_Deliveryorder();

		$columns = array('d.title', 'd.deliveryorderid', 'd.contactid', 'd.billingname1', 'd.billingname2', 'd.billingdepartment', 'd.billingstreet', 'd.billingpostcode', 'd.billingcity', 'd.shippingname1', 'd.shippingname2', 'd.shippingdepartment', 'd.shippingstreet', 'd.shippingpostcode', 'd.shippingcity');

		$query = '';
		$schema = 'd';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$deliveryorders = $deliveryordersDb->fetchAll(
			$deliveryordersDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'deliveryorder'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($deliveryorders) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$deliveryorders = $deliveryordersDb->fetchAll(
				$deliveryordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'deliveryorder'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($deliveryorders)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$deliveryorders->subtotal = 0;
		$deliveryorders->total = 0;
        $currency = $helper->Currency->getCurrency();
		foreach($deliveryorders as $deliveryorder) {
			$deliveryorders->subtotal += $deliveryorder->subtotal;
			$deliveryorders->total += $deliveryorder->total;
		    $currency = $helper->Currency->setCurrency($currency, $deliveryorder->currency, 'USE_SYMBOL');
			$deliveryorder->subtotal = $currency->toCurrency($deliveryorder->subtotal);
			$deliveryorder->taxes = $currency->toCurrency($deliveryorder->taxes);
			$deliveryorder->total = $currency->toCurrency($deliveryorder->total);
		}
		$deliveryorders->subtotal = $currency->toCurrency($deliveryorders->subtotal);
		$deliveryorders->total = $currency->toCurrency($deliveryorders->total);

		return $deliveryorders;
	}

	public function creditnotes($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$columns = array('cr.title', 'cr.creditnoteid', 'cr.contactid', 'cr.billingname1', 'cr.billingname2', 'cr.billingdepartment', 'cr.billingstreet', 'cr.billingpostcode', 'cr.billingcity', 'cr.shippingname1', 'cr.shippingname2', 'cr.shippingdepartment', 'cr.shippingstreet', 'cr.shippingpostcode', 'cr.shippingcity');

		$query = '';
		$schema = 'cr';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$creditnotes = $creditnotesDb->fetchAll(
			$creditnotesDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'creditnote'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($creditnotes) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$creditnotes = $creditnotesDb->fetchAll(
				$creditnotesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'creditnote'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($creditnotes)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$creditnotes->subtotal = 0;
		$creditnotes->total = 0;
	    $currency = $helper->Currency->getCurrency();
		foreach($creditnotes as $creditnote) {
			$creditnotes->subtotal += $creditnote->subtotal;
			$creditnotes->total += $creditnote->total;
		    $currency = $helper->Currency->setCurrency($currency, $creditnote->currency, 'USE_SYMBOL');
			$creditnote->subtotal = $currency->toCurrency($creditnote->subtotal);
			$creditnote->taxes = $currency->toCurrency($creditnote->taxes);
			$creditnote->total = $currency->toCurrency($creditnote->total);
		}
		$creditnotes->subtotal = $currency->toCurrency($creditnotes->subtotal);
		$creditnotes->total = $currency->toCurrency($creditnotes->total);

		return $creditnotes;
	}

	public function reminders($params, $categories, $clientid, $helper, $flashMessenger)
	{
		$remindersDb = new Sales_Model_DbTable_Reminder();

		$columns = array('cr.title', 'cr.reminderid', 'cr.contactid', 'cr.billingname1', 'cr.billingname2', 'cr.billingdepartment', 'cr.billingstreet', 'cr.billingpostcode', 'cr.billingcity', 'cr.shippingname1', 'cr.shippingname2', 'cr.shippingdepartment', 'cr.shippingstreet', 'cr.shippingpostcode', 'cr.shippingcity');

		$query = '';
		$schema = 'cr';
		if($params['keyword']) $query = $helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }
		$query = $helper->Query->getQueryClient($query, $clientid, $schema);
		$query = $helper->Query->getQueryDeleted($query, $schema);

		$reminders = $remindersDb->fetchAll(
			$remindersDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'reminder'))
				->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);
		if(!count($reminders) && $params['keyword']) {
			$query = $helper->Query->getQueryKeyword('', $params['keyword'], $columns);
		    $query = $helper->Query->getQueryClient($query, $clientid, $schema);
		    $query = $helper->Query->getQueryDeleted($query, $schema);
			$reminders = $remindersDb->fetchAll(
				$remindersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'reminder'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.contactid', array('catid AS catid', 'id AS cid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
		}
	    if(!count($reminders)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$reminders->subtotal = 0;
		$reminders->total = 0;
	    $currency = $helper->Currency->getCurrency();
		foreach($reminders as $reminder) {
			$reminders->subtotal += $reminder->subtotal;
			$reminders->total += $reminder->total;
		    $currency = $helper->Currency->setCurrency($currency, $reminder->currency, 'USE_SYMBOL');
			$reminder->subtotal = $currency->toCurrency($reminder->subtotal);
			$reminder->taxes = $currency->toCurrency($reminder->taxes);
			$reminder->total = $currency->toCurrency($reminder->total);
		}
		$reminders->subtotal = $currency->toCurrency($reminders->subtotal);
		$reminders->total = $currency->toCurrency($reminders->total);

		return $reminders;
	}
}
