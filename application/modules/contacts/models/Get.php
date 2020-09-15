<?php

class Contacts_Model_Get
{
	public function contact($id)
	{
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->fetchRow(
			$contactDb->select()
				->from(array('c' => 'contact'))
				->join(array('a' => 'address'), 'c.id = a.contactid', array('street', 'postcode', 'city', 'country'))
				->joinLeft(array('p' => 'phone'), 'c.id = p.contactid', array('phones' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT p.phone)')))
				->joinLeft(array('e' => 'email'), 'c.id = e.contactid', array('emails' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT e.email)')))
				->joinLeft(array('i' => 'internet'), 'c.id = i.contactid', array('internets' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT i.internet)')))
				->group('c.id')
				->where('c.id = ?', $id)
				->setIntegrityCheck(false)
		);
		return $contact;
	}

	public function contacts($params, $categories)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['contacts'];
		}

		$contactsDb = new Contacts_Model_DbTable_Contact();

		$columns = array('c.contactid', 'c.name1', 'c.name2', 'a.postcode', 'a.street', 'a.postcode', 'a.city', 'a.country', 'p.phone', 'e.email', 'i.internet');

		$query = '';
		$schema = 'c';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $queryHelper->getQueryCategory($query, $params['catid'], $categories, $schema);
		if($params['country']) $query = $queryHelper->getQueryCountryC($query, $params['country'], 'a');
		if($query) {
			$query .= " AND a.type = 'billing'";
			$query .= ' AND c.clientid = '.$client['id'];
			$query .= ' AND c.deleted = 0';
		} else {
			$query = 'c.clientid = '.$client['id'];
			$query .= ' AND c.deleted = 0';
		}

		$order = $params['order'];
		if(($order == 'street') || ($order == 'postcode') || ($order == 'city') || ($order == 'country')) $order = 'a.'.$order;
		else $order = 'c.'.$order;

		$contacts = $contactsDb->fetchAll(
			$contactsDb->select()
				->setIntegrityCheck(false)
				->from(array($schema => 'contact'))
				->join(array('a' => 'address'), 'c.id = a.contactid', array('street', 'postcode', 'city', 'country'))
				->joinLeft(array('p' => 'phone'), 'c.id = p.contactid', array('phones' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT p.phone)')))
				->joinLeft(array('e' => 'email'), 'c.id = e.contactid', array('emails' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT e.email)')))
				->joinLeft(array('i' => 'internet'), 'c.id = i.contactid', array('internets' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT i.internet)')))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order($order.' '.$params['sort'])
				->limit($params['limit'])
		);

		return $contacts;
	}

	public function history($contactid) {
		$currency = Zend_Registry::get('Zend_Currency');

		// Set client for sales module
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			if(isset($client['modules']['sales'])) {
				$client['id'] = $client['modules']['sales'];
				$client = Zend_Registry::set('Client', $client);
			}
		}

		//Quotes
		$quoteDb = new Sales_Model_DbTable_Quote();
		$history['quotes'] = $quoteDb->getQuotes($contactid);

		foreach($history['quotes'] as $quote) {
			$quote->subtotal = $currency->toCurrency($quote->subtotal);
			$quote->taxes = $currency->toCurrency($quote->taxes);
			$quote->total = $currency->toCurrency($quote->total);
			if($quote->quotedate) $quote->quotedate = date('d.m.Y', strtotime($quote->quotedate));
			if($quote->modified) $quote->modified = date('d.m.Y', strtotime($quote->modified));
			if($quote->deliverydate) $quote->deliverydate = date('d.m.Y', strtotime($quote->deliverydate));
		}

		//Sales orders
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$history['salesorders'] = $salesorderDb->getSalesorders($contactid);

		foreach($history['salesorders'] as $salesorder) {
			$salesorder->subtotal = $currency->toCurrency($salesorder->subtotal);
			$salesorder->taxes = $currency->toCurrency($salesorder->taxes);
			$salesorder->total = $currency->toCurrency($salesorder->total);
			if($salesorder->salesorderdate) $salesorder->salesorderdate = date('d.m.Y', strtotime($salesorder->salesorderdate));
			if($salesorder->modified) $salesorder->modified = date('d.m.Y', strtotime($salesorder->modified));
			if($salesorder->deliverydate) $salesorder->deliverydate = date('d.m.Y', strtotime($salesorder->deliverydate));
		}

		//Invoices
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$history['invoices'] = $invoiceDb->getInvoices($contactid);

		foreach($history['invoices'] as $invoice) {
			$invoice->subtotal = $currency->toCurrency($invoice->subtotal);
			$invoice->taxes = $currency->toCurrency($invoice->taxes);
			$invoice->total = $currency->toCurrency($invoice->total);
			if($invoice->invoicedate) $invoice->invoicedate = date('d.m.Y', strtotime($invoice->invoicedate));
			if($invoice->modified) $invoice->modified = date('d.m.Y', strtotime($invoice->modified));
			if($invoice->deliverydate) $invoice->deliverydate = date('d.m.Y', strtotime($invoice->deliverydate));
		}

		//Delivery orders
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$history['deliveryorders'] = $deliveryorderDb->getDeliveryorders($contactid);

		foreach($history['deliveryorders'] as $deliveryorder) {
			$deliveryorder->subtotal = $currency->toCurrency($deliveryorder->subtotal);
			$deliveryorder->taxes = $currency->toCurrency($deliveryorder->taxes);
			$deliveryorder->total = $currency->toCurrency($deliveryorder->total);
			if($deliveryorder->deliveryorderdate) $deliveryorder->deliveryorderdate = date('d.m.Y', strtotime($deliveryorder->deliveryorderdate));
			if($deliveryorder->modified) $deliveryorder->modified = date('d.m.Y', strtotime($deliveryorder->modified));
			if($deliveryorder->deliverydate) $deliveryorder->deliverydate = date('d.m.Y', strtotime($deliveryorder->deliverydate));
		}

		//Credit notes
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$history['creditnotes'] = $creditnoteDb->getCreditnotes($contactid);

		foreach($history['creditnotes'] as $creditnote) {
			$creditnote->subtotal = $currency->toCurrency($creditnote->subtotal);
			$creditnote->taxes = $currency->toCurrency($creditnote->taxes);
			$creditnote->total = $currency->toCurrency($creditnote->total);
			if($creditnote->creditnotedate) $creditnote->creditnotedate = date('d.m.Y', strtotime($creditnote->creditnotedate));
			if($creditnote->modified) $creditnote->modified = date('d.m.Y', strtotime($creditnote->modified));
			if($creditnote->deliverydate) $creditnote->deliverydate = date('d.m.Y', strtotime($creditnote->deliverydate));
		}

		//Quote requests
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$history['quoterequests'] = $quoterequestDb->getQuoterequests($contactid);

		// Set client for purchases module
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			if(isset($client['modules']['purchases'])) {
				$client['id'] = $client['modules']['purchases'];
				$client = Zend_Registry::set('Client', $client);
			}
		}

		foreach($history['quoterequests'] as $quoterequest) {
			$quoterequest->subtotal = $currency->toCurrency($quoterequest->subtotal);
			$quoterequest->taxes = $currency->toCurrency($quoterequest->taxes);
			$quoterequest->total = $currency->toCurrency($quoterequest->total);
			if($quoterequest->quoterequestdate) $quoterequest->quoterequestdate = date('d.m.Y', strtotime($quoterequest->quoterequestdate));
			if($quoterequest->modified) $quoterequest->modified = date('d.m.Y', strtotime($quoterequest->modified));
			if($quoterequest->deliverydate) $quoterequest->deliverydate = date('d.m.Y', strtotime($quoterequest->deliverydate));
		}

		//Purchase orders
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$history['purchaseorders'] = $purchaseorderDb->getPurchaseorders($contactid);

		foreach($history['purchaseorders'] as $purchaseorder) {
			$purchaseorder->subtotal = $currency->toCurrency($purchaseorder->subtotal);
			$purchaseorder->taxes = $currency->toCurrency($purchaseorder->taxes);
			$purchaseorder->total = $currency->toCurrency($purchaseorder->total);
		}

		//Processes
		$processesDb = new Processes_Model_DbTable_Process();
		$history['processes'] = $processesDb->getProcesses($contactid);

		// Set client back for contacts module
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			if(isset($client['modules']['contacts'])) {
				$client['id'] = $client['modules']['contacts'];
				$client = Zend_Registry::set('Client', $client);
			}
		}

		return $history;
	}
}
