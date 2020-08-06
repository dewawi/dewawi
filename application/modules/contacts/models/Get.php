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
		$this->_currency = new Zend_Currency();
		$this->_currency->setFormat(array('display' => Zend_Currency::USE_SYMBOL));

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
			$quote->subtotal = $this->_currency->toCurrency($quote->subtotal);
			$quote->taxes = $this->_currency->toCurrency($quote->taxes);
			$quote->total = $this->_currency->toCurrency($quote->total);
			if($quote->quotedate && ($quote->quotedate != '0000-00-00'))
				$quote->quotedate = date('d.m.Y', strtotime($quote->quotedate));
			if($quote->modified && ($quote->modified != '0000-00-00'))
				$quote->modified = date('d.m.Y', strtotime($quote->modified));
			if($quote->deliverydate && ($quote->deliverydate != '0000-00-00'))
				$quote->deliverydate = date('d.m.Y', strtotime($quote->deliverydate));
		}

		//Sales orders
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$history['salesorders'] = $salesorderDb->getSalesorders($contactid);

		foreach($history['salesorders'] as $salesorder) {
			$salesorder->subtotal = $this->_currency->toCurrency($salesorder->subtotal);
			$salesorder->taxes = $this->_currency->toCurrency($salesorder->taxes);
			$salesorder->total = $this->_currency->toCurrency($salesorder->total);
			if($salesorder->salesorderdate && ($salesorder->salesorderdate != '0000-00-00'))
				$salesorder->salesorderdate = date('d.m.Y', strtotime($salesorder->salesorderdate));
			if($salesorder->modified && ($salesorder->modified != '0000-00-00'))
				$salesorder->modified = date('d.m.Y', strtotime($salesorder->modified));
			if($salesorder->deliverydate && ($salesorder->deliverydate != '0000-00-00'))
				$salesorder->deliverydate = date('d.m.Y', strtotime($salesorder->deliverydate));
		}

		//Invoices
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$history['invoices'] = $invoiceDb->getInvoices($contactid);

		foreach($history['invoices'] as $invoice) {
			$invoice->subtotal = $this->_currency->toCurrency($invoice->subtotal);
			$invoice->taxes = $this->_currency->toCurrency($invoice->taxes);
			$invoice->total = $this->_currency->toCurrency($invoice->total);
			if($invoice->invoicedate && ($invoice->invoicedate != '0000-00-00'))
				$invoice->invoicedate = date('d.m.Y', strtotime($invoice->invoicedate));
			if($invoice->modified && ($invoice->modified != '0000-00-00'))
				$invoice->modified = date('d.m.Y', strtotime($invoice->modified));
			if($invoice->deliverydate && ($invoice->deliverydate != '0000-00-00'))
				$invoice->deliverydate = date('d.m.Y', strtotime($invoice->deliverydate));
		}

		//Delivery orders
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$history['deliveryorders'] = $deliveryorderDb->getDeliveryorders($contactid);

		foreach($history['deliveryorders'] as $deliveryorder) {
			$deliveryorder->subtotal = $this->_currency->toCurrency($deliveryorder->subtotal);
			$deliveryorder->taxes = $this->_currency->toCurrency($deliveryorder->taxes);
			$deliveryorder->total = $this->_currency->toCurrency($deliveryorder->total);
			if($deliveryorder->deliveryorderdate && ($deliveryorder->deliveryorderdate != '0000-00-00'))
				$deliveryorder->deliveryorderdate = date('d.m.Y', strtotime($deliveryorder->deliveryorderdate));
			if($deliveryorder->modified && ($deliveryorder->modified != '0000-00-00'))
				$deliveryorder->modified = date('d.m.Y', strtotime($deliveryorder->modified));
			if($deliveryorder->deliverydate && ($deliveryorder->deliverydate != '0000-00-00'))
				$deliveryorder->deliverydate = date('d.m.Y', strtotime($deliveryorder->deliverydate));
		}

		//Credit notes
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$history['creditnotes'] = $creditnoteDb->getCreditnotes($contactid);

		foreach($history['creditnotes'] as $creditnote) {
			$creditnote->subtotal = $this->_currency->toCurrency($creditnote->subtotal);
			$creditnote->taxes = $this->_currency->toCurrency($creditnote->taxes);
			$creditnote->total = $this->_currency->toCurrency($creditnote->total);
			if($creditnote->creditnotedate && ($creditnote->creditnotedate != '0000-00-00'))
				$creditnote->creditnotedate = date('d.m.Y', strtotime($creditnote->creditnotedate));
			if($creditnote->modified && ($creditnote->modified != '0000-00-00'))
				$creditnote->modified = date('d.m.Y', strtotime($creditnote->modified));
			if($creditnote->deliverydate && ($creditnote->deliverydate != '0000-00-00'))
				$creditnote->deliverydate = date('d.m.Y', strtotime($creditnote->deliverydate));
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
			$quoterequest->subtotal = $this->_currency->toCurrency($quoterequest->subtotal);
			$quoterequest->taxes = $this->_currency->toCurrency($quoterequest->taxes);
			$quoterequest->total = $this->_currency->toCurrency($quoterequest->total);
			if($quoterequest->quoterequestdate && ($quoterequest->quoterequestdate != '0000-00-00'))
				$quoterequest->quoterequestdate = date('d.m.Y', strtotime($quoterequest->quoterequestdate));
			if($quoterequest->modified && ($quoterequest->modified != '0000-00-00'))
				$quoterequest->modified = date('d.m.Y', strtotime($quoterequest->modified));
			if($quoterequest->deliverydate && ($quoterequest->deliverydate != '0000-00-00'))
				$quoterequest->deliverydate = date('d.m.Y', strtotime($quoterequest->deliverydate));
		}

		//Purchase orders
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$history['purchaseorders'] = $purchaseorderDb->getPurchaseorders($contactid);

		foreach($history['purchaseorders'] as $purchaseorder) {
			$purchaseorder->subtotal = $this->_currency->toCurrency($purchaseorder->subtotal);
			$purchaseorder->taxes = $this->_currency->toCurrency($purchaseorder->taxes);
			$purchaseorder->total = $this->_currency->toCurrency($purchaseorder->total);
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
