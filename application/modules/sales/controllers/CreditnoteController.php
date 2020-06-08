<?php

class Sales_CreditnoteController extends Zend_Controller_Action
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

		$creditnotes = $this->search($params, $options['categories']);

		$this->view->creditnotes = $creditnotes;
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

		$creditnotes = $this->search($params, $options['categories']);

		$this->view->creditnotes = $creditnotes;
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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$id = $creditnoteDb->addCreditnote($data);

		$this->_helper->redirector->gotoSimple('edit', 'creditnote', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

        //Check if the directory exists
        $dirwritable = $this->checkDirectory($id);

		if($creditnote['creditnoteid']) {
			$this->_helper->redirector->gotoSimple('view', 'creditnote', null, array('id' => $id));
		} elseif($this->isLocked($creditnote['locked'], $creditnote['lockedtime'])) {
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
			$creditnoteDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Sales_Form_Creditnote();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($creditnote['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($creditnote['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($creditnote['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($creditnote['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($creditnote['contactid']);

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
					    $textblockDb->updateTextblock($data, 'creditnote', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'creditnote', 'footer');
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
					if(isset($data['orderdate'])) {
                        if(Zend_Date::isDate($data['orderdate'])) {
                            $orderdate = new Zend_Date($data['orderdate'], Zend_Date::DATES, 'de');
                            $data['orderdate'] = $orderdate->get('yyyy-MM-dd');
					    }
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

					$creditnoteDb->updateCreditnote($id, $data);
					echo Zend_Json::encode($creditnoteDb->getCreditnote($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $creditnote;
					if($creditnote['contactid']) {
						$data['contactinfo'] = $contact['info'];
						$form->contactinfo->setAttrib('data-id', $contact['id']);
						$form->contactinfo->setAttrib('data-controller', 'contact');
						$form->contactinfo->setAttrib('data-module', 'contacts');
						$form->contactinfo->setAttrib('readonly', null);
					}
                    //Convert dates to the display format
                    $orderdate = new Zend_Date($data['orderdate']);
                    if($data['orderdate'] == '0000-00-00') $data['orderdate'] = '';
                    else $data['orderdate'] = $orderdate->get('dd.MM.yyyy');
                    $deliverydate = new Zend_Date($data['deliverydate']);
                    if($data['deliverydate'] == '0000-00-00') $data['deliverydate'] = '';
                    else $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');

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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($creditnote['contactid']);

        //Convert dates to the display format
		$creditnote['creditnotedate'] = date("d.m.Y", strtotime($creditnote['creditnotedate']));
		if($creditnote['orderdate'] != '0000-00-00') $creditnote['orderdate'] = date("d.m.Y", strtotime($creditnote['orderdate']));
		if($creditnote['deliverydate'] != '0000-00-00') $creditnote['deliverydate'] = date("d.m.Y", strtotime($creditnote['deliverydate']));

        //Convert numbers to the display format
		$creditnote['taxes'] = $this->_currency->toCurrency($creditnote['taxes']);
		$creditnote['subtotal'] = $this->_currency->toCurrency($creditnote['subtotal']);
		$creditnote['total'] = $this->_currency->toCurrency($creditnote['total']);

		$positions = $this->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->creditnote = $creditnote;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$data = $creditnoteDb->getCreditnote($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['creditnoteid']);
		$data['title'] = $data['title'].' 2';
		$data['creditnotedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$creditnote = new Sales_Model_DbTable_Creditnote();
		echo $creditnoteid = $creditnote->addCreditnote($data);

		$positions = $this->getPositions($id);
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['creditnoteid'] = $creditnoteid;
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
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$data = $creditnoteDb->getCreditnote($id);

		unset($data['id'], $data['creditnoteid'], $data['creditnotedate']);
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
			unset($dataPosition['id'], $dataPosition['creditnoteid']);
			$positionsSalesorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', null, array('id' => $salesorderid));
	}

	public function generateinvoiceAction()
	{
		$id = $this->_getParam('id', 0);
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$data = $creditnoteDb->getCreditnote($id);

		unset($data['id'], $data['creditnoteid'], $data['creditnotedate']);
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
			unset($dataPosition['id'], $dataPosition['creditnoteid']);
			$positionsInvoiceDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'invoice', null, array('id' => $invoiceid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$data = $creditnoteDb->getCreditnote($id);

		unset($data['id'], $data['creditnoteid'], $data['creditnotedate']);
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
			unset($dataPosition['id'], $dataPosition['creditnoteid']);
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
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$data = $creditnoteDb->getCreditnote($id);

		unset($data['id'], $data['creditnoteid'], $data['creditnotedate']);
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
			unset($dataPosition['id'], $dataPosition['creditnoteid']);
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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Locale', $creditnote['language']);
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

			$creditnote['taxes'] = $this->_currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $this->_currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $this->_currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->creditnote = $creditnote;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($templateid, $this->_user['clientid']);
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		if($creditnote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($creditnote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Locale', $creditnote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positions = $this->getPositions($id);
		if(!$creditnote['creditnoteid']) {
			//Get latest creditnote Id
			$latestCreditnote = $creditnoteDb->fetchRow(
				$creditnoteDb->select()
					->where('clientid = ?', $this->_user['clientid'])
					->order('creditnoteid DESC')
					->limit(1)
			);

			//Set new creditnote Id
			$newCreditnoteId = $latestCreditnote['creditnoteid']+1;
			$creditnoteDb->saveCreditnote($id, $newCreditnoteId, $this->_date, 105, $this->_date, $this->_user['id']);
			$creditnote = $creditnoteDb->getCreditnote($id);

			//Update item data and inventory
			if(count($positions)) {
				$itemsDb = new Items_Model_DbTable_Item();
				foreach($positions as $position) {
					$item = $itemsDb->fetchRow(
						$itemsDb->select()
							->where('sku = ?', $position['sku'])
					);
					if($item && $item['inventory']) {
                        $inventoryDb = new Items_Model_DbTable_Inventory();
						$quantity = $item->quantity + $position->quantity;
                        $inventory = array(
                                    'contactid' => $creditnote['contactid'],
                                    'type' => 'inflow',
                                    'docid' => $creditnote['id'],
                                    'doctype' => 'creditnote',
                                    'creditnoteid' => $creditnote['creditnoteid'],
                                    'date' => $creditnote['creditnotedate'],
                                    'comment' => 'Gutschrift '.$creditnote['creditnoteid'].' vom '.date("d.m.Y", strtotime($creditnote['creditnotedate'])),
                                    'sku' => $position['sku'],
                                    'itemid' => $position['itemid'],
                                    'price' => $position['price'],
                                    'taxrate' => $position['taxrate'],
                                    'quantity' => $position['quantity'],
                                    'total' => $position['total'],
                                    'uom' => $position['uom'],
                                    'clientid' => $creditnote['clientid'],
                                    'warehouseid' => 1,
                                    'created' => $this->_date,
                                    'createdby' => $this->_user['id']
                                    );
                        $inventoryDb->addInventory($inventory);
						$itemsDb->quantityItem($item->id, $quantity);
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

			$creditnote['taxes'] = $this->_currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $this->_currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $this->_currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->creditnote = $creditnote;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($creditnote['templateid'], $this->_user['clientid']);
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		if($creditnote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($creditnote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Locale', $creditnote['language']);
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

			$creditnote['taxes'] = $this->_currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $this->_currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $this->_currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		$this->view->creditnote = $creditnote;
		$this->view->positions = $positions;
		$this->view->footers = $this->_helper->Footer->getFooters($creditnote['templateid'], $this->_user['clientid']);
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$creditnote = new Sales_Model_DbTable_Creditnote();
			$creditnote->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$creditnote = new Sales_Model_DbTable_Creditnote();
			$creditnote->deleteCreditnote($id);

			$positions = $this->getPositions($id);
			$positionsDb = new Sales_Model_DbTable_Creditnotepos();
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
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);
		if($this->isLocked($creditnote['locked'], $creditnote['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($creditnote['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$creditnoteDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnoteDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnoteDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Sales_Form_Creditnote();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function search($params, $categories)
	{
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$columns = array('cr.title', 'cr.creditnoteid', 'cr.contactid', 'cr.billingname1', 'cr.billingname2', 'cr.billingdepartment', 'cr.billingstreet', 'cr.billingpostcode', 'cr.billingcity', 'cr.shippingname1', 'cr.shippingname2', 'cr.shippingdepartment', 'cr.shippingstreet', 'cr.shippingpostcode', 'cr.shippingcity');

		$query = '';
		$schema = 'cr';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $this->_helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $this->_helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['daterange']) {
            $params['from'] = date('Y-m-d', strtotime($params['from']));
            $params['to'] = date('Y-m-d', strtotime($params['to']));
            $query = $this->_helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
        }

		if($params['catid']) {
			$creditnotes = $creditnotesDb->fetchAll(
				$creditnotesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'creditnote'))
					->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($creditnotes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$creditnotes = $creditnotesDb->fetchAll(
					$creditnotesDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'creditnote'))
						->join(array('c' => 'contact'), $schema.'.contactid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$creditnotes = $creditnotesDb->fetchAll(
				$creditnotesDb->select()
					->setIntegrityCheck(false)
					->from(array($schema => 'creditnote'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($creditnotes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$creditnotes = $creditnotesDb->fetchAll(
					$creditnotesDb->select()
						->setIntegrityCheck(false)
						->from(array($schema => 'creditnote'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$creditnotes->subtotal = 0;
		$creditnotes->total = 0;
		foreach($creditnotes as $creditnote) {
			$creditnotes->subtotal += $creditnote->subtotal;
			$creditnotes->total += $creditnote->total;
			$creditnote->subtotal = $this->_currency->toCurrency($creditnote->subtotal);
			$creditnote->taxes = $this->_currency->toCurrency($creditnote->taxes);
			$creditnote->total = $this->_currency->toCurrency($creditnote->total);
		}
		$creditnotes->subtotal = $this->_currency->toCurrency($creditnotes->subtotal);
		$creditnotes->total = $this->_currency->toCurrency($creditnotes->total);

		return $creditnotes;
	}

	protected function getPositions($id)
	{
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('creditnoteid = ?', $id)
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
				->where('controller = ?', 'creditnote')
				->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);
		$textblocks = array();
		foreach($textblocksObject as $textblock)
            $textblocks[$textblock->section] = $textblock->text;
		return $textblocks;
	}

	protected function checkDirectory($id) {
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
