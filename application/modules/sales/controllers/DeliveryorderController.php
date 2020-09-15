<?php

class Sales_DeliveryorderController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Sales_Model_Get();
		$deliveryorders = $get->deliveryorders($params, $options['categories'], $this->_flashMessenger);

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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Sales_Model_Get();
		$deliveryorders = $get->deliveryorders($params, $options['categories'], $this->_flashMessenger);

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

		//Get primary currency
		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		//Get primary language
		$languages = new Application_Model_DbTable_Language();
		$language = $languages->getPrimaryLanguage();

		//Get primary template
		$templates = new Application_Model_DbTable_Template();
		$template = $templates->getPrimaryTemplate();

		$data = array();
		$data['title'] = $this->view->translate('DELIVERY_ORDERS_NEW_DELIVERY_ORDER');
		$data['contactid'] = $contactid;
		$data['currency'] = $currency['code'];
		$data['templateid'] = $template['id'];
		$data['language'] = $language['code'];
		$data['state'] = 100;

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
			$deliveryorderDb->lock($id);

			$form = new Sales_Form_Deliveryorder();
			$options = $this->_helper->Options->getOptions($form);

			//Get contact
			if($deliveryorder['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($deliveryorder['contactid']);

				//Check if the directory is writable
				$dirwritable = $this->_helper->Directory->isWritable($contact['id'], 'deliveryorder', $this->_flashMessenger);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($contact['id']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($contact['id']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($contact['id']);

				$this->view->contact = $contact;
			}

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(($element == 'textblockheader' || $element == 'textblockfooter')) {
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
					if(isset($data['currency'])) {
						$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
						$positions = $positionsDb->getPositions($id);
						foreach($positions as $position) {
							$positionsDb->updatePosition($position->id, array('currency' => $data['currency']));
						}
						//$this->_helper->Currency->convert($id, 'creditnote');
					}
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['row']['subtotal'];
						$data['taxes'] = $calculations['row']['taxes']['total'];
						$data['total'] = $calculations['row']['total'];
					}
					if(isset($data['orderdate'])) {
						if(Zend_Date::isDate($data['orderdate'])) {
							$orderdate = new Zend_Date($data['orderdate'], Zend_Date::DATES, 'de');
							$data['orderdate'] = $orderdate->get('yyyy-MM-dd');
						} else {
							$data['orderdate'] = NULL;
						}
					}
					if(isset($data['deliverydate'])) {
						if(Zend_Date::isDate($data['deliverydate'])) {
							$deliverydate = new Zend_Date($data['deliverydate'], Zend_Date::DATES, 'de');
							$data['deliverydate'] = $deliverydate->get('yyyy-MM-dd');
						} else {
							$data['deliverydate'] = NULL;
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
					//Convert dates to the display format
					$orderdate = new Zend_Date($data['orderdate']);
					if($data['orderdate']) $data['orderdate'] = $orderdate->get('dd.MM.yyyy');
					$deliverydate = new Zend_Date($data['deliverydate']);
					if($data['deliverydate']) $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');

					$form->populate($data);

					//Toolbar
					$toolbar = new Sales_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Sales_Form_ToolbarPositions();

					//Get text blocks
					$textblocksDb = new Sales_Model_DbTable_Textblock();
					$textblocks = $textblocksDb->getTextblocks('deliveryorder');

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

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($deliveryorder['contactid']);

		//Convert dates to the display format
		if($deliveryorder['deliveryorderdate']) $deliveryorder['deliveryorderdate'] = date("d.m.Y", strtotime($deliveryorder['deliveryorderdate']));
		if($deliveryorder['orderdate']) $deliveryorder['orderdate'] = date("d.m.Y", strtotime($deliveryorder['orderdate']));
		if($deliveryorder['deliverydate']) $deliveryorder['deliverydate'] = date("d.m.Y", strtotime($deliveryorder['deliverydate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($deliveryorder['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$deliveryorder['taxes'] = $currency->toCurrency($deliveryorder['taxes']);
		$deliveryorder['subtotal'] = $currency->toCurrency($deliveryorder['subtotal']);
		$deliveryorder['total'] = $currency->toCurrency($deliveryorder['total']);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->deliveryorder = $deliveryorder;
		$this->view->contact = $contact;
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
		$data['deliveryorderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
		echo $deliveryorderid = $deliveryorder->addDeliveryorder($data);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['deliveryorderid'] = $deliveryorderid;
			$dataPosition['modified'] = NULL;
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
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsSalesorderDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
			$dataPosition['modified'] = NULL;
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
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['modified'] = NULL;
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
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$quoterequest = new Purchases_Model_DbTable_Quoterequest();
		$quoterequestid = $quoterequest->addQuoterequest($data);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsQuoterequestDb = new Purchases_Model_DbTable_Quoterequestpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
			$dataPosition['modified'] = NULL;
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
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorderid = $purchaseorder->addPurchaseorder($data);

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
			$dataPosition['modified'] = NULL;
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

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($deliveryorder['contactid']);

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($deliveryorder['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$price = $position->price;
				if($position->priceruleamount && $position->priceruleaction) {
					if($position->priceruleaction == 'bypercent')
						$price = $price*(100-$position->priceruleamount)/100;
					elseif($position->priceruleaction == 'byfixed')
						$price = ($price-$position->priceruleamount);
					elseif($position->priceruleaction == 'topercent')
						$price = $price*(100+$position->priceruleamount)/100;
					elseif($position->priceruleaction == 'tofixed')
						$price = ($price+$position->priceruleamount);
				}
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($price*$position->quantity);
				$position->price = $currency->toCurrency($price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$deliveryorder['taxes'] = $currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->deliveryorder = $deliveryorder;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($deliveryorder['contactid']);

		if($deliveryorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($deliveryorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($deliveryorder['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		if(!$deliveryorder['deliveryorderid']) {
			//Set new deliveryorder Id
			$incrementDb = new Application_Model_DbTable_Increment();
			$increment = $incrementDb->getIncrement('deliveryorderid');
			$deliveryorderDb->saveDeliveryorder($id, $increment);
			$incrementDb->setIncrement(($increment+1), 'deliveryorderid');
			$deliveryorder = $deliveryorderDb->getDeliveryorder($id);
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$deliveryorder['taxes'] = $currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($deliveryorder['templateid']);

		$this->view->deliveryorder = $deliveryorder;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($deliveryorder['contactid']);

		if($deliveryorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($deliveryorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($deliveryorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$deliveryorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($deliveryorder['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$deliveryorder['taxes'] = $currency->toCurrency($deliveryorder['taxes']);
			$deliveryorder['subtotal'] = $currency->toCurrency($deliveryorder['subtotal']);
			$deliveryorder['total'] = $currency->toCurrency($deliveryorder['total']);
			if($deliveryorder['taxfree']) {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$deliveryorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($deliveryorder['templateid']);

		$this->view->deliveryorder = $deliveryorder;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
			$deliveryorder->setState($id, 106);
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

			$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
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
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($id);
		if($this->isLocked($deliveryorder['locked'], $deliveryorder['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($deliveryorder['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$deliveryorderDb->lock($id);
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
		$deliveryorderDb->lock($id);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Deliveryorder();
		$options = $this->_helper->Options->getOptions($form);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
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
