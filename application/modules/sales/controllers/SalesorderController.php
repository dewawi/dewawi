<?php

class Sales_SalesorderController extends Zend_Controller_Action
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

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'attachment', $this->_flashMessenger);
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
		$salesorders = $get->salesorders($params, $options, $this->_flashMessenger);

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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Sales_Model_Get();
		$salesorders = $get->salesorders($params, $options, $this->_flashMessenger);

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
		$data['title'] = $this->view->translate('SALES_ORDERS_NEW_SALES_ORDER');
		$data['currency'] = $currency['code'];
		$data['templateid'] = $template['id'];
		$data['language'] = $language['code'];
		$data['state'] = 100;

		//Get contact data
		if($contactid) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContact($contactid);

			//Get basic data
			$data['contactid'] = $contact['contactid'];
			$data['billingname1'] = $contact['name1'];
			$data['billingname2'] = $contact['name2'];
			$data['billingdepartment'] = $contact['department'];

			//Get addresses
			$addressDb = new Contacts_Model_DbTable_Address();
			$addresses = $addressDb->getAddress($contact['id']);
			if(count($addresses)) {
				$data['billingstreet'] = $addresses[0]['street'];
				$data['billingpostcode'] = $addresses[0]['postcode'];
				$data['billingcity'] = $addresses[0]['city'];
				$data['billingcountry'] = $addresses[0]['country'];
			}

			//Get additonal data
			if($contact['vatin']) $data['vatin'] = $contact['vatin'];
			if($contact['currency']) $data['currency'] = $contact['currency'];
			if($contact['taxfree']) $data['taxfree'] = $contact['taxfree'];
		}

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
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $salesorder['locked'], $salesorder['lockedtime']);

			$form = new Sales_Form_Salesorder();
			$options = $this->_helper->Options->getOptions($form);

			//Get contact
			if($salesorder['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($salesorder['contactid']);

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
						$textblockDb->updateTextblock($data, 'salesorder', 'header');
					} elseif(strpos($element, 'footer') !== false) {
						$data['text'] = $data['textblockfooter'];
						unset($data['textblockfooter']);
						$textblockDb->updateTextblock($data, 'salesorder', 'footer');
					}
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Sales_Model_DbTable_Salesorderpos();
						$positions = $positionsDb->getPositions($id);
						foreach($positions as $position) {
							$positionsDb->updatePosition($position->id, array('currency' => $data['currency']));
						}
						//$this->_helper->Currency->convert($id, 'creditnote');
					}
					if(isset($data['quoteid'])) {
						if($data['quoteid']) {
							$data['quoteid'] = str_replace(['+', '-'], '', filter_var($data['quoteid'], FILTER_SANITIZE_NUMBER_INT));
						} else {
							$data['quoteid'] = 0;
						}
					}
					if(isset($data['quotedate'])) {
						if(Zend_Date::isDate($data['quotedate'])) {
							$quotedate = new Zend_Date($data['quotedate'], Zend_Date::DATES, 'de');
							$data['quotedate'] = $quotedate->get('yyyy-MM-dd');
						} else {
							$data['quotedate'] = NULL;
						}
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
						$contactUrl = $this->_helper->Directory->getUrl($data['contactid']);
						$defaultNamespace = new Zend_Session_Namespace('RF');
						$defaultNamespace->view_type = '1'; //detailed list
						$defaultNamespace->subfolder = 'contacts/'.$contactUrl;
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
					if(!$data['quoteid']) $data['quoteid'] = NULL;

					//Convert dates to the display format
					$quotedate = new Zend_Date($data['quotedate']);
					if($data['quotedate']) $data['quotedate'] = $quotedate->get('dd.MM.yyyy');
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
					$textblocks = $textblocksDb->getTextblocks('salesorder');

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

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($salesorder['contactid']);

		//Convert dates to the display format
		if($salesorder['salesorderdate']) $salesorder['salesorderdate'] = date("d.m.Y", strtotime($salesorder['salesorderdate']));
		if($salesorder['orderdate']) $salesorder['orderdate'] = date("d.m.Y", strtotime($salesorder['orderdate']));
		if($salesorder['deliverydate']) $salesorder['deliverydate'] = date("d.m.Y", strtotime($salesorder['deliverydate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($salesorder['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$salesorder['taxes'] = $currency->toCurrency($salesorder['taxes']);
		$salesorder['subtotal'] = $currency->toCurrency($salesorder['subtotal']);
		$salesorder['total'] = $currency->toCurrency($salesorder['total']);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->description = str_replace("\n", '<br>', $position->description);
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Sales_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		//Get email
		$emailDb = new Contacts_Model_DbTable_Email();
		$contact['email'] = $emailDb->getEmail($contact['id']);

		//Get email form
		$emailForm = new Contacts_Form_Emailmessage();
		if(isset($contact['email'][0])) $emailForm->recipient->setValue($contact['email'][0]['email']);

		//Get email templates
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('sales', 'salesorder')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
			$emailForm->subject->setValue($emailtemplate['subject']);
			$emailForm->body->setValue($emailtemplate['body']);
		}

		//Copy file to attachments
		$filename = $salesorder['filename'];
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$contactFilePath = BASE_PATH.'/files/contacts/'.$contactUrl.'/'.$filename;
		$documentUrl = $this->_helper->Directory->getUrl($salesorder['id']);
		$documentFilePath = BASE_PATH.'/files/attachments/sales/salesorder/'.$documentUrl;
		if(file_exists($documentFilePath) && !file_exists($documentFilePath.'/'.$filename)) {
			if(copy($contactFilePath, $documentFilePath.'/'.$filename)) {
				$data = array();
				$data['documentid'] = $id;
				$data['filename'] = $filename;
				$data['filetype'] = mime_content_type($documentFilePath.'/'.$filename);
				$data['filesize'] = filesize($documentFilePath.'/'.$filename);
				$data['location'] = $documentFilePath.'/'.$filename;
				$data['module'] = 'sales';
				$data['controller'] = 'salesorder';
				$data['ordering'] = 1;
			}
		}

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'sales', 'salesorder');

		$this->view->salesorder = $salesorder;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->emailForm = $emailForm;
		$this->view->contactUrl = $contactUrl;
		$this->view->documentUrl = $documentUrl;
		$this->view->attachments = $attachments;
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
		$data['salesorderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$salesorder = new Sales_Model_DbTable_Salesorder();
		echo $salesorderid = $salesorder->addSalesorder($data);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['salesorderid'] = $salesorderid;
			$dataPosition['modified'] = NULL;
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
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$quote = new Sales_Model_DbTable_Quote();
		$quoteid = $quote->addQuote($data);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsQuoteDb = new Sales_Model_DbTable_Quotepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoteid'] = $quoteid;
			$dataPosition['modified'] = NULL;
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

		unset($data['id']);
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$invoice = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoice->addInvoice($data);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsInvoiceDb = new Sales_Model_DbTable_Invoicepos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['invoiceid'] = $invoiceid;
			$dataPosition['modified'] = NULL;
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

		unset($data['id']);
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

		$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorderid = $deliveryorder->addDeliveryorder($data);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsDeliveryorderDb = new Sales_Model_DbTable_Deliveryorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['deliveryorderid'] = $deliveryorderid;
			$dataPosition['modified'] = NULL;
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

		unset($data['id']);
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

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsQuoterequestDb = new Purchases_Model_DbTable_Quoterequestpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['quoterequestid'] = $quoterequestid;
			$dataPosition['modified'] = NULL;
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

		unset($data['id']);
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

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
		$positionsPurchaseorderDb = new Purchases_Model_DbTable_Purchaseorderpos();
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			$dataPosition['purchaseorderid'] = $purchaseorderid;
			$dataPosition['modified'] = NULL;
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
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		unset($data['id']);

		$process = new Processes_Model_DbTable_Process();
		$processID = $process->addProcess($data);

		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
		$positions = $positionsDb->getPositions($id);
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
			$dataPosition['modified'] = NULL;
			$positionData['modifiedby'] = 0;
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

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($salesorder['contactid']);

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($salesorder['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
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

			$salesorder['taxes'] = $currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->salesorder = $salesorder;
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

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($salesorder['contactid']);

		if($salesorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($salesorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($salesorder['currency'], 'USE_SYMBOL');

		//Set new document Id and filename
		if(!$salesorder['salesorderid']) {
			//Set new salesorder Id
			$incrementDb = new Application_Model_DbTable_Increment();
			$increment = $incrementDb->getIncrement('salesorderid');
			$filenameDb = new Application_Model_DbTable_Filename();
			$filename = $filenameDb->getFilename('salesorder', $salesorder['language']);
			$filename = str_replace('%NUMBER%', $increment, $filename);
			$salesorderDb->saveSalesorder($id, $increment, $filename);
			$incrementDb->setIncrement(($increment+1), 'salesorderid');
			$salesorder = $salesorderDb->getSalesorder($id);
		}

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
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

			$salesorder['taxes'] = $currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($salesorder['templateid']);

		$this->view->salesorder = $salesorder;
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

		$salesorderDb = new Sales_Model_DbTable_Salesorder();
		$salesorder = $salesorderDb->getSalesorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($salesorder['contactid']);

		if($salesorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($salesorder['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($salesorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$salesorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($salesorder['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Salesorderpos();
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

			$salesorder['taxes'] = $currency->toCurrency($salesorder['taxes']);
			$salesorder['subtotal'] = $currency->toCurrency($salesorder['subtotal']);
			$salesorder['total'] = $currency->toCurrency($salesorder['total']);
			if($salesorder['taxfree']) {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$salesorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($salesorder['templateid']);

		$this->view->salesorder = $salesorder;
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
			$salesorder = new Sales_Model_DbTable_Salesorder();
			$salesorder->setState($id, 106);
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

			$positionsDb = new Sales_Model_DbTable_Salesorderpos();
			$positions = $positionsDb->getPositions($id);
			foreach($positions as $position) {
				$positionsDb->deletePosition($position->id);
			}
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->_helper->Validate();
	}
}
