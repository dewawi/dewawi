<?php

class Sales_CreditnoteController extends Zend_Controller_Action
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
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

        $get = new Sales_Model_Get();
		$creditnotes = $get->creditnotes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_flashMessenger);

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

        $get = new Sales_Model_Get();
		$creditnotes = $get->creditnotes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_flashMessenger);

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
		$data['title'] = $this->view->translate('CREDIT_NOTES_NEW_CREDIT_NOTE');
		$data['contactid'] = $contactid;
		$data['currency'] = 'EUR'; // to do get standard currency
		$data['state'] = 100;

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
			$creditnoteDb->lock($id);

			$form = new Sales_Form_Creditnote();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($creditnote['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($creditnote['contactid']);

                //Check if the directory is writable
		        $dirwritable = $this->_helper->Directory->isWritable($contact['id'], 'creditnote', $this->_flashMessenger);

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
					    $textblockDb->updateTextblock($data, 'creditnote', 'header');
                    } elseif(strpos($element, 'footer') !== false) {
					    $data['text'] = $data['textblockfooter'];
					    unset($data['textblockfooter']);
					    $textblockDb->updateTextblock($data, 'creditnote', 'footer');
                    }
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
		                $positionsDb = new Sales_Model_DbTable_Creditnotepos();
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

					//Get text blocks
		            $textblocksDb = new Sales_Model_DbTable_Textblock();
		            $textblocks = $textblocksDb->getTextblocks('creditnote');

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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($creditnote['contactid']);

        //Convert dates to the display format
		$creditnote['creditnotedate'] = date("d.m.Y", strtotime($creditnote['creditnotedate']));
		if($creditnote['orderdate'] != '0000-00-00') $creditnote['orderdate'] = date("d.m.Y", strtotime($creditnote['orderdate']));
		if($creditnote['deliverydate'] != '0000-00-00') $creditnote['deliverydate'] = date("d.m.Y", strtotime($creditnote['deliverydate']));

        //Get currency
		$currency = $this->_helper->Currency->getCurrency($creditnote['currency'], 'USE_SYMBOL');

        //Convert numbers to the display format
		$creditnote['taxes'] = $currency->toCurrency($creditnote['taxes']);
		$creditnote['subtotal'] = $currency->toCurrency($creditnote['subtotal']);
		$creditnote['total'] = $currency->toCurrency($creditnote['total']);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $currency->toCurrency($position->price);
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['clientid'] = $this->_user['clientid'];

		$creditnote = new Sales_Model_DbTable_Creditnote();
		echo $creditnoteid = $creditnote->addCreditnote($data);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['creditnoteid'] = $creditnoteid;
			$dataPosition['created'] = $this->_date;
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsSalesorderDb = new Sales_Model_DbTable_Salesorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;

		$quoterequest = new Purchases_Model_DbTable_Quoterequest();
		$quoterequestid = $quoterequest->addQuoterequest($data);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsQuoterequestDb = new Purchases_Model_DbTable_Quoterequestpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;

		$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorderid = $purchaseorder->addPurchaseorder($data);

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
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

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($creditnote['contactid']);

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

        //Get currency
		$currency = $this->_helper->Currency->getCurrency($creditnote['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
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

			$creditnote['taxes'] = $currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->creditnote = $creditnote;
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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($creditnote['contactid']);

		if($creditnote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($creditnote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

        //Get currency
		$currency = $this->_helper->Currency->getCurrency($creditnote['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		if(!$creditnote['creditnoteid']) {
			//Set new creditnote Id
		    $incrementDb = new Application_Model_DbTable_Increment();
		    $increment = $incrementDb->getIncrement('creditnoteid');
			$creditnoteDb->saveCreditnote($id, $increment);
		    $incrementDb->setIncrement(($increment+1), 'creditnoteid');
			$creditnote = $creditnoteDb->getCreditnote($id);

			//Update item data and inventory
			if(count($positions)) {
				$itemsDb = new Items_Model_DbTable_Item();
				foreach($positions as $position) {
                    $item = $itemsDb->getItemBySKU($position['sku']);
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
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$creditnote['taxes'] = $currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($creditnote['templateid']);

		$this->view->creditnote = $creditnote;
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

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($creditnote['contactid']);

		if($creditnote['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($creditnote['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($creditnote['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$creditnote['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

        //Get currency
		$currency = $this->_helper->Currency->getCurrency($creditnote['currency'], 'USE_SYMBOL');

		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$creditnote['taxes'] = $currency->toCurrency($creditnote['taxes']);
			$creditnote['subtotal'] = $currency->toCurrency($creditnote['subtotal']);
			$creditnote['total'] = $currency->toCurrency($creditnote['total']);
			if($creditnote['taxfree']) {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$creditnote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($creditnote['templateid']);

		$this->view->creditnote = $creditnote;
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
			$creditnote = new Sales_Model_DbTable_Creditnote();
			$creditnote->setState($id, 106);
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

		    $positionsDb = new Sales_Model_DbTable_Creditnotepos();
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
		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnote = $creditnoteDb->getCreditnote($id);
		if($this->isLocked($creditnote['locked'], $creditnote['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($creditnote['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$creditnoteDb->lock($id);
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
		$creditnoteDb->lock($id);
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
