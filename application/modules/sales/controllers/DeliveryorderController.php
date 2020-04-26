<?php

class Sales_DeliveryorderController extends Zend_Controller_Action
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

		$deliveryorders = $this->search($params, $options['categories']);

		$this->view->deliveryorders = $deliveryorders;
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

		$deliveryorders = $this->search($params, $options['categories']);

		$this->view->deliveryorders = $deliveryorders;
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

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$id = $deliveryorderDb->addDeliveryorder($data);

		$this->_helper->redirector->gotoSimple('edit', 'deliveryorder', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		if($deliveryorder['deliveryorderid']) {
			$this->_helper->redirector->gotoSimple('view', 'deliveryorder', null, array('id' => $id));
		} elseif($this->isLocked($deliveryorder['locked'], $deliveryorder['lockedtime'])) {
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
			$deliveryorderDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Sales_Form_Deliveryorder();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($deliveryorder['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($deliveryorder['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($deliveryorder['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($deliveryorder['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($deliveryorder['contactid']);

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
					    $textblockDb->updateTextblock($data, 'deliveryorder', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'deliveryorder', 'footer');
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
					$deliveryorderDb->updateDeliveryorder($id, $data);
					echo Zend_Json::encode($deliveryorderDb->getDeliveryorder($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $deliveryorder;
					if($deliveryorder['contactid']) {
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

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		$deliveryorder['taxes'] = $this->_currency->toCurrency($deliveryorder['taxes']);
		$deliveryorder['subtotal'] = $this->_currency->toCurrency($deliveryorder['subtotal']);
		$deliveryorder['total'] = $this->_currency->toCurrency($deliveryorder['total']);

		$positions = $this->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->deliveryorder = $deliveryorder;
		$this->view->positions = $positions;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$data = $deliveryorderDb->getDeliveryorder($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['deliveryorderid']);
		$data['title'] = $data['title'].' 2';
		$data['deliveryorderdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
		echo $deliveryorderid = $deliveryorder->addDeliveryorder($data);

		$positions = $this->getPositions($id);
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['deliveryorderid'] = $deliveryorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id']);
			$positionsDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function generatesalesorderAction()
	{
		$id = $this->_getParam('id', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$data = $deliveryorderDb->getDeliveryorder($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
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
			unset($dataPosition['id'], $dataPosition['deliveryorderid']);
			$positionsSalesorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', null, array('id' => $salesorderid));
	}

	public function generateinvoiceAction()
	{
		$id = $this->_getParam('id', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$data = $deliveryorderDb->getDeliveryorder($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
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
			unset($dataPosition['id'], $dataPosition['deliveryorderid']);
			$positionsInvoiceDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'invoice', null, array('id' => $invoiceid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$data = $deliveryorderDb->getDeliveryorder($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
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
			unset($dataPosition['id'], $dataPosition['deliveryorderid']);
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
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$data = $deliveryorderDb->getDeliveryorder($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
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
			unset($dataPosition['id'], $dataPosition['deliveryorderid']);
			$positionsPurchaseorderDb->addPosition($dataPosition);
		}

		//Add document relation
		$documentrelationDb = new Application_Model_DbTable_Documentrelation();
		$documentrelationDb->addDocumentrelation($data['contactid'], $purchaseorderid, "purchases", "purchaseorder", $this->_date, $this->_user['id']);

		$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'purchaseorder', 'purchases', array('id' => $purchaseorderid));
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

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Locale', $deliveryorder['language']);
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

			$deliveryorder['taxes'] = $this->_currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $this->_currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $this->_currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->deliveryorder = $deliveryorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($templateid, $this->_user['clientid']);
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		if($deliveryorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($deliveryorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Locale', $deliveryorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positions = $this->getPositions($id);
		if(!$deliveryorder['deliveryorderid']) {
			//Get latest deliveryorder Id
			$latestDeliveryorder = $deliveryorderDb->fetchRow(
				$deliveryorderDb->select()
					->where('clientid = ?', $this->_user['clientid'])
					->order('deliveryorderid DESC')
					->limit(1)
			);

			//Set new deliveryorder Id
			$newDeliveryorderId = $latestDeliveryorder['deliveryorderid']+1;
			$deliveryorderDb->saveDeliveryorder($id, $newDeliveryorderId, $this->_date, 105, $this->_date, $this->_user['id']);
			$deliveryorder = $deliveryorderDb->getDeliveryorder($id);
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$deliveryorder['taxes'] = $this->_currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $this->_currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $this->_currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->deliveryorder = $deliveryorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($deliveryorder['templateid'], $this->_user['clientid']);
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		if($deliveryorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($deliveryorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Locale', $deliveryorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positions = $this->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$deliveryorder['taxes'] = $this->_currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $this->_currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $this->_currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->deliveryorder = $deliveryorder;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($deliveryorder['templateid'], $this->_user['clientid']);
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
			$deliveryorder->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
			$deliveryorder->deleteDeliveryorder($id);

			$positions = $this->getPositions($id);
			$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
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
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);
		if($this->isLocked($deliveryorder['locked'], $deliveryorder['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($deliveryorder['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$deliveryorderDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorderDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorderDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Deliveryorder();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function search($params, $categories)
	{
		$deliveryordersDb = new Sales_Model_DbTable_Deliveryorder();

		$columns = array('d.title', 'd.deliveryorderid', 'd.contactid', 'd.billingname1', 'd.billingname2', 'd.billingdepartment', 'd.billingstreet', 'd.billingpostcode', 'd.billingcity', 'd.shippingname1', 'd.shippingname2', 'd.shippingdepartment', 'd.shippingstreet', 'd.shippingpostcode', 'd.shippingcity');

		$query = '';
		$schema = 'd';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $this->_helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['daterange']) $query = $this->_helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		if($params['country']) $query = $this->_helper->Query->getQueryCountry($query, $params['country'], $schema);

		if($params['catid']) {
			$deliveryorders = $deliveryordersDb->fetchAll(
				$deliveryordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'deliveryorder'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($deliveryorders) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$deliveryorders = $deliveryordersDb->fetchAll(
					$deliveryordersDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'deliveryorder'))
						->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$deliveryorders = $deliveryordersDb->fetchAll(
				$deliveryordersDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'deliveryorder'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($deliveryorders) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$deliveryorders = $deliveryordersDb->fetchAll(
					$deliveryordersDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'deliveryorder'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$deliveryorders->subtotal = 0;
		$deliveryorders->total = 0;
		foreach($deliveryorders as $deliveryorder) {
			$deliveryorders->subtotal += $deliveryorder->subtotal;
			$deliveryorders->total += $deliveryorder->total;
			$deliveryorder->subtotal = $this->_currency->toCurrency($deliveryorder->subtotal);
			$deliveryorder->taxes = $this->_currency->toCurrency($deliveryorder->taxes);
			$deliveryorder->total = $this->_currency->toCurrency($deliveryorder->total);
		}
		$deliveryorders->subtotal = $this->_currency->toCurrency($deliveryorders->subtotal);
		$deliveryorders->total = $this->_currency->toCurrency($deliveryorders->total);

		return $deliveryorders;
	}

	protected function getPositions($id)
	{
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('deliveryorderid = ?', $id)
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
				->where('controller = ?', 'deliveryorder')
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
