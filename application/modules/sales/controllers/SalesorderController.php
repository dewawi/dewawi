<?php

class Sales_SalesorderController extends Zend_Controller_Action
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

		$salesorders = $this->search($params, $options['categories']);

		$this->view->salesorders = $salesorders;
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

		$salesorders = $this->search($params, $options['categories']);

		$this->view->salesorders = $salesorders;
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

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$id = $salesorderDb->addSalesorder($data);

		$this->_helper->redirector->gotoSimple('edit','salesorder',null,array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		if($salesorder['salesorderid']) {
			$this->_helper->redirector->gotoSimple('view','salesorder',null,array('id' => $id));
		} elseif($this->isLocked($salesorder['locked'], $salesorder['lockedtime'])) {
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
			$salesorderDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Sales_Form_Salesorder();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($salesorder['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($salesorder['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($salesorder['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($salesorder['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($salesorder['contactid']);

				$this->view->contact = $contact;
			}

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
                if(($element == 'textblockheader' || $element == 'textblockfooter')) {
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
				    $textblockDb = new Sales_Model_DbTable_Textblock();
                    if(strpos($element, 'header') !== false) {
					    $data['text'] = $data['textblockheader'];
					    unset($data['textblockheader']);
					    $textblockDb->updateTextblock($data, 'salesorder', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'salesorder', 'footer');
                    }
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['subtotal'];
						$data['taxes'] = $calculations['taxes'];
						$data['total'] = $calculations['total'];
					}
					$salesorderDb->updateSalesorder($id, $data);
					echo Zend_Json::encode($salesorderDb->getSalesorder($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $salesorder;
					if($salesorder['contactid']) {
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
					$this->view->textblocks = $this->getTextblocks();
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		$salesorder['taxes'] = $this->_currency->toCurrency($salesorder['taxes']);
		$salesorder['subtotal'] = $this->_currency->toCurrency($salesorder['subtotal']);
		$salesorder['total'] = $this->_currency->toCurrency($salesorder['total']);

		$positions = $this->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->salesorder = $salesorder;
		$this->view->positions = $positions;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['salesorderid']);
		$data['title'] = $data['title'].' 2';
		$data['salesorderdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$salesorder = new Sales_Model_DbTable_Salesorder();
		echo $salesorderid = $salesorder->addSalesorder($data);

		$positions = $this->getPositions($id);
		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
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
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		unset($data['id'], $data['salesorderid'], $data['salesorderdate']);
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
			unset($dataPosition['id'], $dataPosition['salesorderid']);
			$positionsQuoteDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_QUOTE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit','quote',null,array('id' => $quoteid));
	}

	public function generateinvoiceAction()
	{
		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		unset($data['id'], $data['salesorderid'], $data['salesorderdate']);
		$data['invoicedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positions = $this->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['salesorderid']);
			$positionsInvoiceDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit','invoice',null,array('id' => $invoiceid));
	}

	public function generatedeliveryorderAction()
	{
		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		unset($data['id'], $data['salesorderid'], $data['salesorderdate']);
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
			unset($dataPosition['id'], $dataPosition['salesorderid']);
			$positionsDeliveryorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_DELIVERY_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit','deliveryorder',null,array('id' => $deliveryorderid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		unset($data['id'], $data['salesorderid'], $data['salesorderdate']);
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
			unset($dataPosition['id'], $dataPosition['salesorderid']);
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
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$data = $salesorderDb->getSalesorder($id);

		unset($data['id'], $data['salesorderid'], $data['salesorderdate']);
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
			unset($dataPosition['id'], $dataPosition['salesorderid']);
			$positionsPurchaseorderDb->addPosition($dataPosition);
		}

		//Add document relation
		$documentrelationDb = new Application_Model_DbTable_Documentrelation();
		$documentrelationDb->addDocumentrelation($data['contactid'], $purchaseorderid, 'purchases', 'purchaseorder', $this->_date, $this->_user['id']);

		$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'purchaseorder', 'purchases', array('id' => $purchaseorderid));
	}

	public function generateprocessAction()
	{
		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		$data = array();
		$form = new Processes_Form_Process();
		$elements = $form->getElements();
		foreach($elements as $key => $value) {
			if(isset($salesorder[$key])) $data[$key] = $salesorder[$key];
		}
		$data['subtotal'] = $salesorder['subtotal'];
		$data['taxes'] = $salesorder['taxes'];
		$data['total'] = $salesorder['total'];
		$data['customerid'] = $salesorder['contactid'];
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

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Locale', $salesorder['language']);
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

			$salesorder['taxes'] = $this->_currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $this->_currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $this->_currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->salesorder = $salesorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($templateid, $this->_user['clientid']);
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		if($salesorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($salesorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Locale', $salesorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positions = $this->getPositions($id);
		if(!$salesorder['salesorderid']) {
			//Get latest salesorder Id
			$latestSalesorder = $salesorderDb->fetchRow(
				$salesorderDb->select()
					->where('clientid = ?', $this->_user['clientid'])
					->order('salesorderid DESC')
					->limit(1)
			);

			//Set new salesorder Id
			$newSalesorderId = $latestSalesorder['salesorderid']+1;
			$salesorderDb->saveSalesorder($id, $newSalesorderId, $this->_date, 105, $this->_date, $this->_user['id']);
			$salesorder = $salesorderDb->getSalesorder($id);
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$salesorder['taxes'] = $this->_currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $this->_currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $this->_currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->salesorder = $salesorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($salesorder['templateid'], $this->_user['clientid']);
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		if($salesorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($salesorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Locale', $salesorder['language']);
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

			$salesorder['taxes'] = $this->_currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $this->_currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $this->_currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->salesorder = $salesorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($salesorder['templateid'], $this->_user['clientid']);
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$salesorder = new Sales_Model_DbTable_Salesorder();
			$salesorder->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$salesorder = new Sales_Model_DbTable_Salesorder();
			$salesorder->deleteSalesorder($id);

			$positions = $this->getPositions($id);
			$positionsDb = new Sales_Model_DbTable_Salesorderpos();
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
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);
		if($this->isLocked($salesorder['locked'], $salesorder['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($salesorder['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$salesorderDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorderDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorderDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Salesorder();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function search($params, $categories)
	{
		$salesordersDb = new Sales_Model_DbTable_Salesorder();

		$columns = array('s.title', 's.salesorderid', 's.contactid', 's.billingname1', 's.billingname2', 's.billingdepartment', 's.billingstreet', 's.billingpostcode', 's.billingcity', 's.shippingname1', 's.shippingname2', 's.shippingdepartment', 's.shippingstreet', 's.shippingpostcode', 's.shippingcity');

		$query = '';
		$schema = 's';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $this->_helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['daterange']) $query = $this->_helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		if($params['country']) $query = $this->_helper->Query->getQueryCountry($query, $params['country'], $schema);

		if($params['catid']) {
			$salesorders = $salesordersDb->fetchAll(
				$salesordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'salesorder'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($salesorders) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$salesorders = $salesordersDb->fetchAll(
					$salesordersDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'salesorder'))
						->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$salesorders = $salesordersDb->fetchAll(
				$salesordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'salesorder'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($salesorders) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$salesorders = $salesordersDb->fetchAll(
					$salesordersDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'salesorder'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$salesorders->subtotal = 0;
		$salesorders->total = 0;
		foreach($salesorders as $salesorder) {
			$salesorders->subtotal += $salesorder->subtotal;
			$salesorders->total += $salesorder->total;
			$salesorder->subtotal = $this->_currency->toCurrency($salesorder->subtotal);
			$salesorder->taxes = $this->_currency->toCurrency($salesorder->taxes);
			$salesorder->total = $this->_currency->toCurrency($salesorder->total);
		}
		$salesorders->subtotal = $this->_currency->toCurrency($salesorders->subtotal);
		$salesorders->total = $this->_currency->toCurrency($salesorders->total);

		return $salesorders;
	}

	protected function getPositions($id)
	{
		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('salesorderid = ?', $id)
				->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);

		return $positions;
	}

	protected function getTextblocks()
	{
	    $textblocksDb = new Sales_Model_DbTable_Textblock();
		$textblocksObject = $textblocksDb->fetchAll(
			$textblocksDb->select()
				->where('controller = ?', 'salesorder')
				->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);
		$textblocks = array();
		foreach($textblocksObject as $textblock)
            $textblocks[$textblock->section] = $textblock->text;
		return $textblocks;
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
