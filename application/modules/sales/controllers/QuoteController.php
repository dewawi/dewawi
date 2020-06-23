<?php

class Sales_QuoteController extends Zend_Controller_Action
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

        $get = new Sales_Model_Get();
		$quotes = $get->quotes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		$this->view->quotes = $quotes;
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

        $get = new Sales_Model_Get();
		$quotes = $get->quotes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		$this->view->quotes = $quotes;
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

		//Get default template
		$templateDb = new Application_Model_DbTable_Template();
		$template = $templateDb->getDefaultTemplate();
        if(isset($template)) $data['templateid'] = $template['id'];

		$quoteDb = new Sales_Model_DbTable_Quote();
		$id = $quoteDb->addQuote($data);

		$this->_helper->redirector->gotoSimple('edit', 'quote', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);

        //Check if the directory exists
        $dirwritable = $this->checkDirectory($quote['contactid']);

		if($quote['quoteid']) {
			$this->_helper->redirector->gotoSimple('view', 'quote', null, array('id' => $id));
		} elseif($this->isLocked($quote['locked'], $quote['lockedtime'])) {
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
			$quoteDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Sales_Form_Quote();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($quote['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($quote['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($quote['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($quote['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($quote['contactid']);

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
					    $textblockDb->updateTextblock($data, 'quote', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'quote', 'footer');
                    }
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['row']['subtotal'];
						$data['taxes'] = $calculations['row']['taxes']['total'];
						$data['total'] = $calculations['row']['total'];
					}
					if(isset($data['deliverydate'])) {
                        if(Zend_Date::isDate($data['deliverydate'])) {
                            $deliverydate = new Zend_Date($data['deliverydate'], Zend_Date::DATES, 'de');
                            $data['deliverydate'] = $deliverydate->get('yyyy-MM-dd');
					    }
					}

                    //Update file manager subfolder if contact is changed
                    if(isset($data['contactid']) && $data['contactid']) {
                        $dir1 = substr($data['contactid'], 0, 1).'/';
                        if(strlen($data['contactid']) > 1) $dir2 = substr($data['contactid'], 1, 1).'/';
                        else $dir2 = '0/';
                        $defaultNamespace = new Zend_Session_Namespace('RF');
                        $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$data['contactid'].'/';
                    }

					$quoteDb->updateQuote($id, $data);
					echo Zend_Json::encode($quoteDb->getQuote($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $quote;
					if($quote['contactid']) {
						$data['contactinfo'] = $contact['info'];
						$form->contactinfo->setAttrib('data-id', $contact['id']);
						$form->contactinfo->setAttrib('data-controller', 'contact');
						$form->contactinfo->setAttrib('data-module', 'contacts');
						$form->contactinfo->setAttrib('readonly', null);
					}
                    //Convert dates to the display format
                    $deliverydate = new Zend_Date($data['deliverydate']);
                    if($data['deliverydate'] == '0000-00-00') $data['deliverydate'] = '';
                    else $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');

					$form->populate($data);

					//Toolbar
					$toolbar = new Sales_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Sales_Form_ToolbarPositions();

					//Get text blocks
		            $textblocksDb = new Sales_Model_DbTable_Textblock();
		            $textblocks = $textblocksDb->getTextblocks('quote');

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
					$this->view->toolbarPositions = $toolbarPositions;
					$this->view->textblocks = $textblocks;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($quote['contactid']);

        //Convert dates to the display format
		$quote['quotedate'] = date("d.m.Y", strtotime($quote['quotedate']));
		if($quote['deliverydate'] != '0000-00-00') $quote['deliverydate'] = date("d.m.Y", strtotime($quote['deliverydate']));

        //Convert numbers to the display format
		$quote['taxes'] = $this->_currency->toCurrency($quote['taxes']);
		$quote['subtotal'] = $this->_currency->toCurrency($quote['subtotal']);
		$quote['total'] = $this->_currency->toCurrency($quote['total']);

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->quote = $quote;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['quoteid']);
		$data['title'] = $data['title'].' 2';
		$data['quotedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$quote = new Sales_Model_DbTable_Quote();
		echo $quoteid = $quote->addQuote($data);

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoteid'] = $quoteid;
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
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		unset($data['id'], $data['quoteid'], $data['quotedate']);
		$data['salesorderdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsSalesorderDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoteid']);
			$positionsSalesorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', null, array('id' => $salesorderid));
	}

	public function generateinvoiceAction()
	{
		$id = $this->_getParam('id', 0);
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		unset($data['id'], $data['quoteid'], $data['quotedate']);
		$data['invoicedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoteid']);
			$positionsInvoiceDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'invoice', null, array('id' => $invoiceid));
	}

	public function generatedeliveryorderAction()
	{
		$id = $this->_getParam('id', 0);
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		unset($data['id'], $data['quoteid'], $data['quotedate']);
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

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsDeliveryorderDb = new Sales_Model_DbTable_Deliveryorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['deliveryorderid'] = $deliveryorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoteid']);
			$positionsDeliveryorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_DELIVERY_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit','deliveryorder',null,array('id' => $deliveryorderid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		unset($data['id'], $data['quoteid'], $data['quotedate']);
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

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsQuoterequestDb = new Purchases_Model_DbTable_Quoterequestpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoteid']);
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
		$quoteDb = new Sales_Model_DbTable_Quote();
		$data = $quoteDb->getQuote($id);

		unset($data['id'], $data['quoteid'], $data['quotedate']);
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

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoteid']);
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

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);

		//Set language
		if($quote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$quote['language']);
			Zend_Registry::set('Zend_Locale', $quote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$quote['taxes'] = $this->_currency->toCurrency($quote['taxes']);
			$quote['subtotal'] = $this->_currency->toCurrency($quote['subtotal']);
			$quote['total'] = $this->_currency->toCurrency($quote['total']);
			if($quote['taxfree']) {
				$quote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->quote = $quote;
		$this->view->positions = $positions;
		$this->view->calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $quote['taxfree']);
		$this->view->footers = $footers;
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);

		if($quote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($quote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($quote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$quote['language']);
			Zend_Registry::set('Zend_Locale', $quote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		if(!$quote['quoteid']) {
			//Set new quote Id
		    $incrementDb = new Application_Model_DbTable_Increment();
		    $increment = $incrementDb->getIncrement('quoteid');
			$quoteDb->saveQuote($id, $increment);
		    $incrementDb->setIncrement(($increment+1), 'quoteid');
			$quote = $quoteDb->getQuote($id);
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$quote['taxes'] = $this->_currency->toCurrency($quote['taxes']);
			$quote['subtotal'] = $this->_currency->toCurrency($quote['subtotal']);
			$quote['total'] = $this->_currency->toCurrency($quote['total']);
			if($quote['taxfree']) {
				$quote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($quote['templateid']);

		$this->view->quote = $quote;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);

		if($quote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($quote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($quote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$quote['language']);
			Zend_Registry::set('Zend_Locale', $quote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$quote['taxes'] = $this->_currency->toCurrency($quote['taxes']);
			$quote['subtotal'] = $this->_currency->toCurrency($quote['subtotal']);
			$quote['total'] = $this->_currency->toCurrency($quote['total']);
			if($quote['taxfree']) {
				$quote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($quote['templateid']);

		$this->view->quote = $quote;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$quote = new Sales_Model_DbTable_Quote();
			$quote->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$quote = new Sales_Model_DbTable_Quote();
			$quote->deleteQuote($id);

		    $positionsDb = new Sales_Model_DbTable_Quotepos();
		    $positions = $positionsDb->getPositions($id);
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
		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($id);
		if($this->isLocked($quote['locked'], $quote['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($quote['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$quoteDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$quoteDb = new Sales_Model_DbTable_Quote();
		$quoteDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quoteDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Quote();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function checkDirectory($id) {
		//Create cache folder if does not already exists
        $cache = BASE_PATH.'/cache/';
        if(!file_exists($cache.'quote/') && is_writable($cache)) mkdir($cache.'quote/', 0777, true);
		//Create contact folder if does not already exists
        $path = BASE_PATH.'/files/contacts/';
        $dir1 = substr($id, 0, 1).'/';
        if(strlen($id) > 1) $dir2 = substr($id, 1, 1).'/';
        else $dir2 = '0/';
        if(file_exists($path.$dir1.$dir2.$id) && is_dir($path.$dir1.$dir2.$id) && is_writable($path.$dir1.$dir2.$id)) {
            return true;
        } elseif(is_writable($path)) {
            $response = mkdir($path.$dir1.$dir2.$id, 0777, true);
            if($response === false) $this->_flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			return $response;
        } else {
            $this->_flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			return false;
        }
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
