<?php

class Sales_InvoiceController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	protected $_currency = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_currency = new Zend_Currency();
		if(($this->view->action != 'index') && ($this->view->action != 'select') && ($this->view->action != 'search') && ($this->view->action != 'download') && ($this->view->action != 'save') && ($this->view->action != 'preview') && ($this->view->action != 'get'))
			$this->_currency->setFormat(array('display' => Zend_Currency::NO_SYMBOL));

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Sales_Form_Toolbar();
		if(isset($form->$element)) {
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Sales_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$invoices = $this->search($params, $options['categories']);

		$this->view->invoices = $invoices;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Sales_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$invoices = $this->search($params, $options['categories']);

		$this->view->invoices = $invoices;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function addAction()
	{
		$contactid = $this->_getParam('contactid', 0);

		$data = array();
		$data['contactid'] = $contactid;
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_user['clientid'];

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$id = $invoiceDb->addInvoice($data);

		$this->_helper->redirector->gotoSimple('edit', 'invoice', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		if($invoice['invoiceid']) {
			$this->_helper->redirector->gotoSimple('view', 'invoice', null, array('id' => $id));
		} elseif($this->isLocked($invoice['locked'], $invoice['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$invoiceDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Sales_Form_Invoice();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($invoice['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($invoice['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($invoice['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($invoice['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($invoice['contactid']);

				$this->view->contact = $contact;
			}

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['subtotal'];
						$data['taxes'] = $calculations['taxes'];
						$data['total'] = $calculations['total'];
					}
					$invoiceDb->updateInvoice($id, $data);
					echo Zend_Json::encode($invoiceDb->getInvoice($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $invoice;
					if($invoice['contactid']) {
						$data['contactinfo'] = $contact['info'];
						$form->contactinfo->setAttrib('data-id', $contact['id']);
						$form->contactinfo->setAttrib('data-controller', 'contact');
						$form->contactinfo->setAttrib('data-module', 'contacts');
						$form->contactinfo->setAttrib('readonly', null);
					}
					$form->populate($data);

					//Toolbar
					$toolbar = new Sales_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Sales_Form_ToolbarPositions();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
					$this->view->toolbarPositions = $toolbarPositions;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$invoice['taxes'] = $this->_currency->toCurrency($invoice['taxes']);
		$invoice['subtotal'] = $this->_currency->toCurrency($invoice['subtotal']);
		$invoice['total'] = $this->_currency->toCurrency($invoice['total']);

		$positions = $this->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		//E-Mail form
		$email = new Application_Form_Email();
		$files = array(0 => "/cache/invoice/".$id.".pdf");

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->files = $files;
		$this->view->invoice = $invoice;
		$this->view->positions = $positions;
		$this->view->email = $email;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['invoiceid']);
		$data['title'] = $data['title'].' 2';
		$data['invoicedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$invoice = new Sales_Model_DbTable_Invoice();
		echo $invoiceid = $invoice->addInvoice($data);

		$positions = $this->getPositions($id);
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id']);
			$positionsDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function generatequoteAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['quotedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$quote = new Sales_Model_DbTable_Quote();
		$quoteid = $quote->addQuote($data);

		$positions = $this->getPositions($id);
		$positionsQuoteDb = new Sales_Model_DbTable_Quotepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoteid'] = $quoteid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsQuoteDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_QUOTE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'quote', null, array('id' => $quoteid));
	}

	public function generatesalesorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['salesorderdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		$positions = $this->getPositions($id);
		$positionsSalesorderDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsSalesorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', null, array('id' => $salesorderid));
	}

	public function generatedeliveryorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['deliveryorderdate'] = '0000-00-00';
		$data['billingname1'] = '';
		$data['billingname2'] = '';
		$data['billingdepartment'] = '';
		$data['billingstreet'] = '';
		$data['billingpostcode'] = '';
		$data['billingcity'] = '';
		$data['billingcountry'] = '';
		if(!$data['shippingname1']) {
			$data['shippingname1'] = $data['billingname1'];
			$data['shippingname2'] = $data['billingname2'];
			$data['shippingdepartment'] = $data['billingdepartment'];
			$data['shippingstreet'] = $data['billingstreet'];
			$data['shippingpostcode'] = $data['billingpostcode'];
			$data['shippingcity'] = $data['billingcity'];
			$data['shippingcountry'] = $data['billingcountry'];
			$data['shippingphone'] = '';
		}
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorderid = $deliveryorder->addDeliveryorder($data);

		$positions = $this->getPositions($id);
		$positionsDeliveryorderDb = new Sales_Model_DbTable_Deliveryorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['deliveryorderid'] = $deliveryorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsDeliveryorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_DELIVERY_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'deliveryorder', null, array('id' => $deliveryorderid));
	}

	public function generatecreditnoteAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['creditnotedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$creditnote = new Sales_Model_DbTable_Creditnote();
		$creditnoteid = $creditnote->addCreditnote($data);

		$positions = $this->getPositions($id);
		$positionsCreditnoteDb = new Sales_Model_DbTable_Creditnotepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['creditnoteid'] = $creditnoteid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsCreditnoteDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_CREDIT_NOTE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'creditnote', null, array('id' => $creditnoteid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['quoterequestdate'] = '0000-00-00';
		$data['billingname1'] = '';
		$data['billingname2'] = '';
		$data['billingdepartment'] = '';
		$data['billingstreet'] = '';
		$data['billingpostcode'] = '';
		$data['billingcity'] = '';
		$data['billingcountry'] = '';
		if(!$data['shippingname1']) {
			$data['shippingname1'] = $data['billingname1'];
			$data['shippingname2'] = $data['billingname2'];
			$data['shippingdepartment'] = $data['billingdepartment'];
			$data['shippingstreet'] = $data['billingstreet'];
			$data['shippingpostcode'] = $data['billingpostcode'];
			$data['shippingcity'] = $data['billingcity'];
			$data['shippingcountry'] = $data['billingcountry'];
			$data['shippingphone'] = '';
		}
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$quoterequest = new Purchases_Model_DbTable_Quoterequest();
		$quoterequestid = $quoterequest->addQuoterequest($data);

		$positions = $this->getPositions($id);
		$positionsQuoterequestDb = new Purchases_Model_DbTable_Quoterequestpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsQuoterequestDb->addPosition($dataPosition);
		}

		//Add document relation
		$documentrelationDb = new Application_Model_DbTable_Documentrelation();
		$documentrelationDb->addDocumentrelation($data['contactid'], $quoterequestid, "purchases", "quoterequest", $this->_date, $this->_user['id']);

		$this->_flashMessenger->addMessage('MESSAGES_QUOTE_REQUEST_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'quoterequest', 'purchases', array('id' => $quoterequestid));
	}


	public function generatepurchaseorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['ebayorderid']);
		$data['purchaseorderdate'] = '0000-00-00';
		$data['billingname1'] = '';
		$data['billingname2'] = '';
		$data['billingdepartment'] = '';
		$data['billingstreet'] = '';
		$data['billingpostcode'] = '';
		$data['billingcity'] = '';
		$data['billingcountry'] = '';
		if(!$data['shippingname1']) {
			$data['shippingname1'] = $data['billingname1'];
			$data['shippingname2'] = $data['billingname2'];
			$data['shippingdepartment'] = $data['billingdepartment'];
			$data['shippingstreet'] = $data['billingstreet'];
			$data['shippingpostcode'] = $data['billingpostcode'];
			$data['shippingcity'] = $data['billingcity'];
			$data['shippingcountry'] = $data['billingcountry'];
			$data['shippingphone'] = '';
		}
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorderid = $purchaseorder->addPurchaseorder($data);

		$positions = $this->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['invoiceid']);
			$positionsPurchaseorderDb->addPosition($dataPosition);
		}

		//Add document relation
		$documentrelationDb = new Application_Model_DbTable_Documentrelation();
		$documentrelationDb->addDocumentrelation($data['contactid'], $purchaseorderid, "purchases", "purchaseorder", $this->_date, $this->_user['id']);

		$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'purchaseorder', 'purchases', array('id' => $purchaseorderid));
	}

	public function generateprocessAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$data = array();
		$form = new Processes_Form_Process();
		$elements = $form->getElements();
		foreach($elements as $key => $value) {
			if(isset($invoice[$key])) $data[$key] = $invoice[$key];
		}
		$data['subtotal'] = $invoice['subtotal'];
		$data['taxes'] = $invoice['taxes'];
		$data['total'] = $invoice['total'];
		$data['customerid'] = $invoice['contactid'];
		$data['deliverystatus'] = 'deliveryIsWaiting';
		$data['supplierorderstatus'] = 'supplierNotOrdered';
		$data['paymentstatus'] = 'waitingForPayment';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data["created"] = $this->_date;
		$data["createdby"] = $this->_user['id'];
		$data["modified"] = "0000-00-00";
		$data["modifiedby"] = 0;
		unset($data["id"]);

		$process = new Processes_Model_DbTable_Process();
		$processID = $process->addProcess($data);

		$positions = $this->getPositions($id);
		$processposDb = new Processes_Model_DbTable_Processpos();
		foreach($positions as $position) {
			$positionData = array();
			$positionForm = new Processes_Form_Processpos();
			$positionElements = $positionForm->getElements();
			foreach($positionElements as $key => $value) {
				if(isset($position->$key)) $positionData[$key] = $position->$key;
			}
			$positionData['processid'] = $processID;
			$positionData['taxrate'] = $position->taxrate;
			$positionData['deliverystatus'] = 'deliveryIsWaiting';
			$positionData['supplierorderstatus'] = 'supplierNotOrdered';
			$positionData["created"] = $this->_date;
			$positionData["createdby"] = $this->_user['id'];
			$positionData["modified"] = "0000-00-00";
			$positionData["modifiedby"] = 0;
			unset($positionData['id']);
			$processposDb->addPosition($positionData);
		}

		$this->_flashMessenger->addMessage('MESSAGES_PROCESS_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('index','process','processes');
	}

	public function previewAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$templateid = $this->_getParam('templateid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		if($templateid) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($templateid);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		//Set language
		if($invoice['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$invoice['language']);
			Zend_Registry::set('Zend_Locale', $invoice['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positions = $this->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$invoice['taxes'] = $this->_currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $this->_currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $this->_currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->invoice = $invoice;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($templateid, $this->_user['clientid']);
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		if($invoice['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($invoice['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		$positions = $this->getPositions($id);
		if(!$invoice['invoiceid']) {
			//Get latest invoice Id
			$latestInvoice = $invoiceDb->fetchRow(
				$invoiceDb->select()
					->where('clientid = ?', $this->_user['clientid'])
					->order('invoiceid DESC')
					->limit(1)
			);

			//Set new invoice Id
			$newInvoiceId = $latestInvoice['invoiceid']+1;
			$invoiceDb->saveInvoice($id, $newInvoiceId, $this->_date, 105, $this->_date, $this->_user['id']);
			$invoice = $invoiceDb->getInvoice($id);

			//Update item data
			if(count($positions)) {
				$itemsDb = new Items_Model_DbTable_Item();
				foreach($positions as $position) {
					$item = $itemsDb->fetchRow(
						$itemsDb->select()
							->where('sku = ?', $position['sku'])
					);
					if($item) {
						$quantity = $item->quantity - $position->quantity;
						$itemsDb->quantityItem($item->id, $quantity, $this->_date, $this->_user['id']);
					}
				}
			}
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$invoice['taxes'] = $this->_currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $this->_currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $this->_currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->invoice = $invoice;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($invoice['templateid'], $this->_user['clientid']);
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		if($invoice['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($invoice['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		$positions = $this->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity, array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$invoice['taxes'] = $this->_currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $this->_currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $this->_currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->invoice = $invoice;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($invoice['templateid'], $this->_user['clientid']);
	}

	protected function emailAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Application_Form_Email();

		$smtpconfig = array('auth' => $this->_config->smtpauth,
				'username' => $this->_config->smtpuser,
				'password' => $this->_config->smtppass);

		$tr = new Zend_Mail_Transport_Smtp($this->_config->smtphost, $smtpconfig);
		Zend_Mail::setDefaultTransport($tr);

		$mail = new Zend_Mail();

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$email = $form->getValue('email');
				$subject = $form->getValue('subject');
				$body = $form->getValue('body');
				$file = $formData['file'];

				$mail->setBodyHtml($body);
				$mail->setFrom($this->_config->mailfrom, $this->_config->fromname);
				$mail->addTo($email);
				$mail->addBcc("remzi.demen@deec.de");
				$mail->setSubject($subject);

				if($file) {
					$file = explode("|", $file);
					$att = file_get_contents(APPLICATION_PATH.'/..'.$file[0]);
					$at = $mail->createAttachment($att);
					$at->filename = $file[1];
				}

				$mail->send();

$mail = new Zend_Mail_Storage_Imap(array('host'     => $this->_config->smtphost,
					'user'     => $this->_config->smtpuser,
					'password' => $this->_config->smtppass));
print_r($mail);
			} else {
				$form->populate($formData);
			}
		}
	}

	protected function uploadAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();
		$form->file->setDestination('/var/www/dewawi/files/');

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$file = $form->getValue('file');

 /* Uploading Document File on Server */
 $upload = new Zend_File_Transfer_Adapter_Http();
 //$upload->setDestination("/var/www/dewawi/files/");
 try {
 // upload received file(s)
 $upload->receive();
 } catch (Zend_File_Transfer_Exception $e) {
 $e->getMessage();
 }

 // so, Finally lets See the Data that we received on Form Submit
// $uploadedData = $form->getValues();
// Zend_Debug::dump($uploadedData, 'Form Data:');

 // you MUST use following functions for knowing about uploaded file
 # Returns the file name for 'file' named file element
// $name = $upload->getFileName('file');

 # Returns the size for 'file' named file element
 # Switches of the SI notation to return plain numbers
// $upload->setOptions(array('useByteString' => false));
// $size = $upload->getFileSize('file');

 # Returns the mimetype for the 'file' form element
// $mimeType = $upload->getMimeType('file');

 // following lines are just for being sure that we got data
// print "Name of uploaded file: $name";
// print "File Size: $size";
// print "File's Mime Type: $mimeType";

 // New Code For Zend Framework :: Rename Uploaded File
// $renameFile = 'newName.jpg';

// $fullFilePath = '/var/www/dewawi/files/'.$renameFile;

 // Rename uploaded file using Zend Framework
// $filterFileRename = new Zend_Filter_File_Rename(array('target' => $fullFilePath, 'overwrite' => true));

// $filterFileRename -> filter($name);


/*  if ($_FILES["file"]["error"] > 0)
    {
    echo "Return Code: " . $_FILES["file"]["error"] . "<br />";
    }
  else
    {
    echo "Upload: " . $_FILES["file"]["name"] . "<br />";
    echo "Type: " . $_FILES["file"]["type"] . "<br />";
    echo "Size: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
    echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br />";

    if (file_exists("/var/www/dewawi/upload/invoice/" . $_FILES["file"]["name"]))
      {
      echo $_FILES["file"]["name"] . " already exists. ";
      }
    else
      {
      move_uploaded_file($_FILES["file"]["tmp_name"],
      "/var/www/dewawi/upload/invoice/" . $_FILES["file"]["name"]);
      echo "Stored in: " . "/var/www/dewawi/upload/invoice/" . $_FILES["file"]["name"];
      }
    }
print_r($_FILES);*/
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$invoice = new Sales_Model_DbTable_Invoice();
			$invoice->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$invoice = new Sales_Model_DbTable_Invoice();
			$invoice->deleteInvoice($id);

			$positions = $this->getPositions($id);
			$positionsDb = new Sales_Model_DbTable_Invoicepos();
			foreach($positions as $position) {
				$positionsDb->deletePosition($position->id);
			}
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);
		if($this->isLocked($invoice['locked'], $invoice['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($invoice['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$invoiceDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoiceDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoiceDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Invoice();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	/*public function testAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$invoices = $invoicesDb->fetchAll(
			$invoicesDb->select()
				->where('id BETWEEN 49501 AND 50500')
		);

		foreach($invoices as $invoice) {
			$positionsDb = new Sales_Model_DbTable_Invoicepos();
			$positions = $positionsDb->fetchAll(
				$positionsDb->select()
					->where('invoiceid = ?', $invoice->id)
			);
			$subtotal = 0;
			foreach($positions as $position) {
				$subtotal += ($position->quantity*$position->price);
			}
			$subtotal = round($subtotal, 2);
			$taxes = round($subtotal*19/100, 2);
			if($invoice->taxfree) $taxes = 0;
			$total = round($taxes+$subtotal, 2);
			echo '<pre>';
			echo $subtotal.' '.$taxes.' '.$total;
			echo '</pre>';

			$invoicesDb->updateTotal($invoice->id, $subtotal, $taxes, $total, null, null);
		}

		/*$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('id BETWEEN 0 AND 400')
		);

		foreach($positions as $position) {
			$total = 0;
			foreach($positions as $position) {
				$subtotal += $position;
			}
			echo '';
		}*/
	//}

	protected function search($params, $categories)
	{
		$invoicesDb = new Sales_Model_DbTable_Invoice();

		$columns = array('i.title', 'i.invoiceid', 'i.contactid', 'i.billingname1', 'i.billingname2', 'i.billingdepartment', 'i.billingstreet', 'i.billingpostcode', 'i.billingcity', 'i.shippingname1', 'i.shippingname2', 'i.shippingdepartment', 'i.shippingstreet', 'i.shippingpostcode', 'i.shippingcity');

		$query = '';
		$schema = 'i';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $this->_helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['daterange']) $query = $this->_helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		if($params['country']) $query = $this->_helper->Query->getQueryCountry($query, $params['country'], $schema);

		if($params['catid']) {
			$invoices = $invoicesDb->fetchAll(
				$invoicesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'invoice'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($invoices) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$invoices = $invoicesDb->fetchAll(
					$invoicesDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'invoice'))
						->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$invoices = $invoicesDb->fetchAll(
				$invoicesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'invoice'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($invoices) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$invoices = $invoicesDb->fetchAll(
					$invoicesDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'invoice'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$invoices->subtotal = 0;
		$invoices->total = 0;
		foreach($invoices as $invoice) {
			$invoices->subtotal += $invoice->subtotal;
			$invoices->total += $invoice->total;
			$invoice->subtotal = $this->_currency->toCurrency($invoice->subtotal);
			$invoice->taxes = $this->_currency->toCurrency($invoice->taxes);
			$invoice->total = $this->_currency->toCurrency($invoice->total);
		}
		$invoices->subtotal = $this->_currency->toCurrency($invoices->subtotal);
		$invoices->total = $this->_currency->toCurrency($invoices->total);

		return $invoices;
	}

	protected function getPositions($id)
	{
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('invoiceid = ?', $id)
				->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);

		return $positions;
	}

	protected function isLocked($locked, $lockedtime)
	{
		if($locked && ($locked != $this->_user['id'])) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime($this->_date);
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}
}
