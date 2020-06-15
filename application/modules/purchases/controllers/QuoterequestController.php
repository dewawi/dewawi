<?php

class Purchases_QuoterequestController extends Zend_Controller_Action
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
		$form = new Purchases_Form_Toolbar();
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

		$toolbar = new Purchases_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

        $get = new Purchases_Model_Get();
		$quoterequests = $get->quoterequests($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		$this->view->quoterequests = $quoterequests;
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

		$toolbar = new Purchases_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

        $get = new Purchases_Model_Get();
		$quoterequests = $get->quoterequests($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		$this->view->quoterequests = $quoterequests;
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

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$id = $quoterequestDb->addQuoterequest($data);

		$this->_helper->redirector->gotoSimple('edit', 'quoterequest' , null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);

        //Check if the directory exists
        $dirwritable = $this->checkDirectory($quoterequest['contactid']);

		if($quoterequest['quoterequestid']) {
			$this->_helper->redirector->gotoSimple('view', 'quoterequest', null, array('id' => $id));
		} elseif($this->isLocked($quoterequest['locked'], $quoterequest['lockedtime'])) {
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
			$quoterequestDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Purchases_Form_Quoterequest();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($quoterequest['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($quoterequest['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($quoterequest['contactid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($quoterequest['contactid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($quoterequest['contactid']);

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
				    $textblockDb = new Purchases_Model_DbTable_Textblock();
                    if(strpos($element, 'header') !== false) {
					    $data['text'] = $data['textblockheader'];
					    unset($data['textblockheader']);
					    $textblockDb->updateTextblock($data, 'quoterequest', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'quoterequest', 'footer');
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

					$quoterequestDb->updateQuoterequest($id, $data);
					echo Zend_Json::encode($quoterequestDb->getQuoterequest($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $quoterequest;
					if($quoterequest['contactid']) {
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
					$toolbar = new Purchases_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Purchases_Form_ToolbarPositions();

					//Get text blocks
		            $textblocksDb = new Purchases_Model_DbTable_Textblock();
		            $textblocks = $textblocksDb->getTextblocks('quoterequest');

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

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($quoterequest['contactid']);

        //Convert dates to the display format
		$quoterequest['quoterequestdate'] = date("d.m.Y", strtotime($quoterequest['quoterequestdate']));
		if($quoterequest['orderdate'] != '0000-00-00') $quoterequest['orderdate'] = date("d.m.Y", strtotime($quoterequest['orderdate']));
		if($quoterequest['deliverydate'] != '0000-00-00') $quoterequest['deliverydate'] = date("d.m.Y", strtotime($quoterequest['deliverydate']));

        //Convert numbers to the display format
		$quoterequest['taxes'] = $this->_currency->toCurrency($quoterequest['taxes']);
		$quoterequest['subtotal'] = $this->_currency->toCurrency($quoterequest['subtotal']);
		$quoterequest['total'] = $this->_currency->toCurrency($quoterequest['total']);

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Purchases_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->quoterequest = $quoterequest;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$data = $quoterequestDb->getQuoterequest($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['quoterequestid']);
		$data['title'] = $data['title'].' 2';
		$data['quoterequestdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$quoterequest = new Purchases_Model_DbTable_Quoterequest();
		echo $quoterequestid = $quoterequest->addQuoterequest($data);

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
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
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$data = $quoterequestDb->getQuoterequest($id);

		unset($data['id'], $data['quoterequestid'], $data['quoterequestdate']);
		$data['salesorderdate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		$positionsSalesorderDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoterequestid']);
			$positionsSalesorderDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', 'sales', array('id' => $salesorderid));
	}

	public function generateinvoiceAction()
	{
		$id = $this->_getParam('id', 0);
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$data = $quoterequestDb->getQuoterequest($id);

		unset($data['id'], $data['quoterequestid'], $data['quoterequestdate']);
		$data['invoicedate'] = '0000-00-00';
		$data['state'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoterequestid']);
			$positionsInvoiceDb->addPosition($dataPosition);
		}

		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'invoice', 'sales', array('id' => $invoiceid));
	}

	public function generatepurchaseorderAction()
	{
		$id = $this->_getParam('id', 0);
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$data = $quoterequestDb->getQuoterequest($id);

		unset($data['id'], $data['quoterequestid'], $data['quoterequestdate']);
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

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
			$dataPosition['created'] = $this->_date;
			$dataPosition['createdby'] = $this->_user['id'];
			$dataPosition['modified'] = '0000-00-00';
			$dataPosition['modifiedby'] = 0;
			unset($dataPosition['id'], $dataPosition['quoterequestid']);
			$positionsPurchaseorderDb->addPosition($dataPosition);
		}

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

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);

		//Set language
		if($quoterequest['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$quoterequest['language']);
			Zend_Registry::set('Zend_Locale', $quoterequest['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$quoterequest['taxes'] = $this->_currency->toCurrency($quoterequest['taxes']);
			$quoterequest['subtotal'] = $this->_currency->toCurrency($quoterequest['subtotal']);
			$quoterequest['total'] = $this->_currency->toCurrency($quoterequest['total']);
			if($quoterequest['taxfree']) {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->quoterequest = $quoterequest;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);

		if($quoterequest['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($quoterequest['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		if(!$quoterequest['quoterequestid']) {
			//Set new quoterequest Id
			$newQuoterequestId = $quoterequestDb->getLatestQuoterequestID()+1;
			$quoterequestDb->saveQuoterequest($id, $newQuoterequestId, $this->_date, 105, $this->_date, $this->_user['id']);
			$quoterequest = $quoterequestDb->getQuoterequest($id);
		}

		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$quoterequest['taxes'] = $this->_currency->toCurrency($quoterequest['taxes']);
			$quoterequest['subtotal'] = $this->_currency->toCurrency($quoterequest['subtotal']);
			$quoterequest['total'] = $this->_currency->toCurrency($quoterequest['total']);
			if($quoterequest['taxfree']) {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($quoterequest['templateid']);

		$this->view->quoterequest = $quoterequest;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setRender('pdf');

		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);

		if($quoterequest['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($quoterequest['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $this->_currency->toCurrency($position->price*$position->quantity);
				$position->price = $this->_currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$quoterequest['taxes'] = $this->_currency->toCurrency($quoterequest['taxes']);
			$quoterequest['subtotal'] = $this->_currency->toCurrency($quoterequest['subtotal']);
			$quoterequest['total'] = $this->_currency->toCurrency($quoterequest['total']);
			if($quoterequest['taxfree']) {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quoterequest['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($quoterequest['templateid']);

		$this->view->quoterequest = $quoterequest;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$quoterequest = new Purchases_Model_DbTable_Quoterequest();
			$quoterequest->setState($id, 106, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$quoterequest = new Purchases_Model_DbTable_Quoterequest();
			$quoterequest->deleteQuoterequest($id);

		    $positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
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
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequest = $quoterequestDb->getQuoterequest($id);
		if($this->isLocked($quoterequest['locked'], $quoterequest['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($quoterequest['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$quoterequestDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequestDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequestDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Purchases_Form_Quoterequest();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
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
