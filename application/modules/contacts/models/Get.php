<?php

class Contacts_Model_Get
{
	public function contact($id)
	{
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->fetchRow(
			$contactDb->select()
				->from(array('c' => 'contact'))
				->join(array('a' => 'address'), 'c.id = a.parentid AND a.deleted = 0', array('street', 'postcode', 'city', 'country'))
				->joinLeft(array('p' => 'phone'), 'c.id = p.parentid AND p.deleted = 0', array('phones' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT p.phone)')))
				->joinLeft(array('e' => 'email'), 'c.id = e.parentid AND e.deleted = 0', array('emails' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT e.email)')))
				->joinLeft(array('i' => 'internet'), 'c.id = i.parentid AND i.deleted = 0', array('internets' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT i.internet)')))
				->group('c.id')
				->where('c.id = ?', $id)
				->setIntegrityCheck(false)
		);
		return $contact;
	}

	public function emailmessages($params, $options)
	{
		if(!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) return [];
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		if(empty($user['id']) || empty($user['clientid'])) return [];

		$permission = new DEEC_Permission($user);
		if(!$permission->hasActionPermission('contacts', 'contact', 'view')) return [];

		$documentId = filter_var($params['documentid'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$contactId = filter_var($params['contactid'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$module = $params['module'] ?? '';
		$controller = $params['controller'] ?? '';
		if($documentId === false || $contactId === false || !is_string($module) || !is_string($controller)) return [];
		if(($module === '') !== ($controller === '') || ($documentId > 0 && $module === '')) return [];
		if($module !== '' && (!preg_match('/^[a-z][a-z0-9-]*$/D', $module) || !preg_match('/^[a-z][a-z0-9-]*$/D', $controller))) return [];

		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$user['clientid'];
		if($contactClientId <= 0) return [];

		// Contact pages use internal contact IDs; document pages pass contact numbers.
		if($documentId === 0 && $contactId > 0) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contactDb->setClientId($contactClientId);
			if(!$contactDb->getById($contactId)) return [];
		}

		$emailmessagesDb = new Contacts_Model_DbTable_Emailmessage();
		$db = $emailmessagesDb->getAdapter();
		$select = $emailmessagesDb->select()
			->setIntegrityCheck(false)
			->from(['m' => 'emailmessage'])
			->joinLeft(['t' => 'emailtracking'], 'm.id = t.emailmessageid', ['accesstime' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT t.accesstime)')])
			->where('m.clientid = ?', $contactClientId)
			->where('m.deleted = ?', 0)
			->where($this->getEmailHistoryAccess($db, $permission, (int)$user['clientid']))
			->group('m.id')
			->order('m.id DESC')
			->limit($params['limit']);

		if($documentId > 0) $select->where('m.documentid = ?', $documentId);
		elseif($contactId > 0) $select->where('m.contactid = ?', $contactId);
		if($module !== '') $select->where('m.module = ?', $module)->where('m.controller = ?', $controller);
		if(!empty($params['keyword'])) {
			if(!is_string($params['keyword'])) return [];
			$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], ['m.subject', 'm.body']);
			if($query !== '') $select->where($query);
		}

		return $emailmessagesDb->fetchAll($select)->toArray();
	}

	private function getEmailHistoryAccess($db, DEEC_Permission $permission, int $clientId): string
	{
		// Missing document IDs on document messages must never turn them into contact mail.
		$access = ["(COALESCE(m.documentid, 0) = 0 AND COALESCE(m.campaignid, 0) = 0 AND m.module = 'contacts' AND m.controller = 'contact')"];
		$contexts = [
			'sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'],
			'purchases' => ['quoterequest', 'purchaseorder'],
			'processes' => ['process'],
			'tasks' => ['task'],
			'campaigns' => ['campaign'],
		];

		foreach($contexts as $module => $controllers) {
			foreach($controllers as $controller) {
				// Preserve the ACL behavior for existing modules without a permission column.
				if($permission->hasModule($module) && !$permission->hasActionPermission($module, $controller, 'view')) continue;
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
				$documentDb = new $class();
				$documentDb->setClientId($clientId);
				$field = $module === 'campaigns' ? 'campaignid' : 'documentid';
				$document = $documentDb->select()->from(['history_document' => $documentDb->info('name')], ['id'])
					->where('history_document.id = m.' . $field)
					->where('history_document.clientid = ?', $documentDb->getClientId())
					->where('history_document.deleted = ?', 0);
				$access[] = '(' . $db->quoteInto('m.module = ?', $module)
					. ' AND ' . $db->quoteInto('m.controller = ?', $controller)
					. ($module === 'campaigns' ? ' AND COALESCE(m.documentid, 0) = 0' : '')
					. ' AND m.' . $field . ' > 0 AND EXISTS (' . $document . '))';
			}
		}

		return '(' . implode(' OR ', $access) . ')';
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
		$history['quotes'] = $quoteDb->getByContactId($contactid);

		foreach($history['quotes'] as $quote) {
			if($quote->quotedate) $quote->quotedate = date('d.m.Y', strtotime($quote->quotedate));
			if($quote->modified) $quote->modified = date('d.m.Y', strtotime($quote->modified));
			if($quote->deliverydate) $quote->deliverydate = date('d.m.Y', strtotime($quote->deliverydate));
		}

		//Sales orders
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$history['salesorders'] = $salesorderDb->getByContactId($contactid);

		foreach($history['salesorders'] as $salesorder) {
			if($salesorder->salesorderdate) $salesorder->salesorderdate = date('d.m.Y', strtotime($salesorder->salesorderdate));
			if($salesorder->modified) $salesorder->modified = date('d.m.Y', strtotime($salesorder->modified));
			if($salesorder->deliverydate) $salesorder->deliverydate = date('d.m.Y', strtotime($salesorder->deliverydate));
		}

		//Invoices
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$history['invoices'] = $invoiceDb->getByContactId($contactid);

		foreach($history['invoices'] as $invoice) {
			if($invoice->invoicedate) $invoice->invoicedate = date('d.m.Y', strtotime($invoice->invoicedate));
			if($invoice->modified) $invoice->modified = date('d.m.Y', strtotime($invoice->modified));
			if($invoice->deliverydate) $invoice->deliverydate = date('d.m.Y', strtotime($invoice->deliverydate));
		}

		//Delivery orders
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$history['deliveryorders'] = $deliveryorderDb->getByContactId($contactid);

		foreach($history['deliveryorders'] as $deliveryorder) {
			if($deliveryorder->deliveryorderdate) $deliveryorder->deliveryorderdate = date('d.m.Y', strtotime($deliveryorder->deliveryorderdate));
			if($deliveryorder->modified) $deliveryorder->modified = date('d.m.Y', strtotime($deliveryorder->modified));
			if($deliveryorder->deliverydate) $deliveryorder->deliverydate = date('d.m.Y', strtotime($deliveryorder->deliverydate));
		}

		//Reminders
		$reminderDb = new Sales_Model_DbTable_Reminder();
		$history['reminders'] = $reminderDb->getByContactId($contactid);

		foreach($history['reminders'] as $reminder) {
			if($reminder->reminderdate) $reminder->reminderdate = date('d.m.Y', strtotime($reminder->reminderdate));
			if($reminder->modified) $reminder->modified = date('d.m.Y', strtotime($reminder->modified));
			if($reminder->deliverydate) $reminder->deliverydate = date('d.m.Y', strtotime($reminder->deliverydate));
		}

		//Credit notes
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$history['creditnotes'] = $creditnoteDb->getByContactId($contactid);

		foreach($history['creditnotes'] as $creditnote) {
			if($creditnote->creditnotedate) $creditnote->creditnotedate = date('d.m.Y', strtotime($creditnote->creditnotedate));
			if($creditnote->modified) $creditnote->modified = date('d.m.Y', strtotime($creditnote->modified));
			if($creditnote->deliverydate) $creditnote->deliverydate = date('d.m.Y', strtotime($creditnote->deliverydate));
		}

		//Quote requests
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$history['quoterequests'] = $quoterequestDb->getByContactId($contactid);

		// Set client for purchases module
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			if(isset($client['modules']['purchases'])) {
				$client['id'] = $client['modules']['purchases'];
				$client = Zend_Registry::set('Client', $client);
			}
		}

		foreach($history['quoterequests'] as $quoterequest) {
			if($quoterequest->quoterequestdate) $quoterequest->quoterequestdate = date('d.m.Y', strtotime($quoterequest->quoterequestdate));
			if($quoterequest->modified) $quoterequest->modified = date('d.m.Y', strtotime($quoterequest->modified));
			if($quoterequest->deliverydate) $quoterequest->deliverydate = date('d.m.Y', strtotime($quoterequest->deliverydate));
		}

		//Purchase orders
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$history['purchaseorders'] = $purchaseorderDb->getByContactId($contactid);

		foreach($history['purchaseorders'] as $purchaseorder) {
			if($purchaseorder->purchaseorderdate) $purchaseorder->purchaseorderdate = date('d.m.Y', strtotime($purchaseorder->purchaseorderdate));
			if($purchaseorder->modified) $purchaseorder->modified = date('d.m.Y', strtotime($purchaseorder->modified));
			if($purchaseorder->deliverydate) $purchaseorder->deliverydate = date('d.m.Y', strtotime($purchaseorder->deliverydate));
		}

		//Processes
		$processesDb = new Processes_Model_DbTable_Process();
		$history['processes'] = $processesDb->getByContactId($contactid);

		// Set client back for contacts module
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			if(isset($client['modules']['contacts'])) {
				$client['id'] = $client['modules']['contacts'];
				$client = Zend_Registry::set('Client', $client);
			}
		}

		//Get states
		$stateDb = new Application_Model_DbTable_State();
		$states = $stateDb->getStates();
		$history['states'] = $states + [
			'105' => 'STATES_COMPLETED',
			'106' => 'STATES_CANCELLED',
		];

		return $history;
	}

	public function attachments($module, $controller, $id) {
		$attachments = array();
		$directoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory');
		$url = $directoryHelper->getUrl($id);
		$path = BASE_PATH.'/files/attachments/'.$module.'/'.$controller.'/'.$url;
		if(file_exists($path)) {
			if($handle = opendir($path)) {
			while(false !== ($entry = readdir($handle))) {
				if($entry != "." && $entry != "..") {
					array_push($attachments, $entry);
				}
			}
			closedir($handle);
			}
		}
		return $attachments;
	}

	public function tags($module, $controller, $id = null) {
		if($id) {
			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tags = $tagEntityDb->getTagEntities($module, $controller, (int)$id);
		} else {
			$tagsDb = new Application_Model_DbTable_Tag();
			$tags = $tagsDb->getTags($module, $controller);
		}

		return $tags;
	}

	protected function normalizeContactListValues($contacts)
	{
		foreach ($contacts as $contact) {
			$contact->phones = $this->splitListValue($contact->phones ?? '');
			$contact->emails = $this->splitListValue($contact->emails ?? '');
			$contact->internets = $this->splitListValue($contact->internets ?? '');
		}

		return $contacts;
	}

	protected function splitListValue($value): array
	{
		if (is_array($value)) {
			return $value;
		}

		$value = trim((string)$value);

		if ($value === '') {
			return [];
		}

		return array_values(array_filter(array_map('trim', explode(',', $value))));
	}
}
