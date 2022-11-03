<?php

class Sales_InvoiceController extends Zend_Controller_Action
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
		$invoices = $get->invoices($params, $options, $this->_flashMessenger);

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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Sales_Model_Get();
		$invoices = $get->invoices($params, $options, $this->_flashMessenger);

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
		$data['title'] = $this->view->translate('INVOICES_NEW_INVOICE');
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

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$id = $invoiceDb->addInvoice($data);

		$this->_helper->redirector->gotoSimple('edit', 'invoice', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		if($invoice['invoiceid']) {
			$this->_helper->redirector->gotoSimple('view', 'invoice', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $invoice['locked'], $invoice['lockedtime']);

			$form = new Sales_Form_Invoice();
			$options = $this->_helper->Options->getOptions($form);

			//Get contact
			if($invoice['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($invoice['contactid']);

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

			//Get currency
			$currencyHelper = $this->_helper->Currency;
			$currency = $currencyHelper->getCurrency();
			$currencyHelper->setCurrency($currency, $invoice['currency'], 'NO_SYMBOL');

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
						$textblockDb->updateTextblock($data, 'invoice', 'header');
					} elseif(strpos($element, 'footer') !== false) {
						$data['text'] = $data['textblockfooter'];
						unset($data['textblockfooter']);
						$textblockDb->updateTextblock($data, 'invoice', 'footer');
					}
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Sales_Model_DbTable_Invoicepos();
						$positions = $positionsDb->getPositions($id);
						foreach($positions as $position) {
							$positionsDb->updatePosition($position->id, array('currency' => $data['currency']));
						}
						//$this->_helper->Currency->convert($id, 'creditnote');
					}
					if(isset($data['salesorderid'])) {
						if($data['salesorderid']) {
							$data['salesorderid'] = str_replace(['+', '-'], '', filter_var($data['salesorderid'], FILTER_SANITIZE_NUMBER_INT));
						} else {
							$data['salesorderid'] = 0;
						}
					}
					if(isset($data['salesorderdate'])) {
						if(Zend_Date::isDate($data['salesorderdate'])) {
							$salesorderdate = new Zend_Date($data['salesorderdate'], Zend_Date::DATES, 'de');
							$data['salesorderdate'] = $salesorderdate->get('yyyy-MM-dd');
						} else {
							$data['salesorderdate'] = NULL;
						}
					}
					if(($element == 'prepayment'))
						$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['row']['subtotal'];
						$data['taxes'] = $calculations['row']['taxes']['total'];
						$data['total'] = $calculations['row']['total'];
					}
					if(isset($data['salesorderid'])) {
						if($data['salesorderid']) {
							$data['salesorderid'] = str_replace(['+', '-'], '', filter_var($data['salesorderid'], FILTER_SANITIZE_NUMBER_INT));
						} else {
							$data['salesorderid'] = 0;
						}
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
					if(!$data['salesorderid']) $data['salesorderid'] = NULL;

					//Convert dates to the display format
					$salesorderdate = new Zend_Date($data['salesorderdate']);
					if($data['salesorderdate']) $data['salesorderdate'] = $salesorderdate->get('dd.MM.yyyy');
					$orderdate = new Zend_Date($data['orderdate']);
					if($data['orderdate']) $data['orderdate'] = $orderdate->get('dd.MM.yyyy');
					$deliverydate = new Zend_Date($data['deliverydate']);
					if($data['deliverydate']) $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');

					$data['prepayment'] = $currency->toCurrency($data['prepayment']);

					$form->populate($data);

					//Toolbar
					$toolbar = new Sales_Form_Toolbar();
					$toolbar->state->setValue($data['state']);

					//Get text blocks
					$textblocksDb = new Sales_Model_DbTable_Textblock();
					$textblocks = $textblocksDb->getTextblocks('invoice');

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
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

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($invoice['contactid']);

		//Convert dates to the display format
		if($invoice['invoicedate']) $invoice['invoicedate'] = date("d.m.Y", strtotime($invoice['invoicedate']));
		if($invoice['orderdate']) $invoice['orderdate'] = date("d.m.Y", strtotime($invoice['orderdate']));
		if($invoice['deliverydate']) $invoice['deliverydate'] = date("d.m.Y", strtotime($invoice['deliverydate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($invoice['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$invoice['taxes'] = $currency->toCurrency($invoice['taxes']);
		$invoice['subtotal'] = $currency->toCurrency($invoice['subtotal']);
		$invoice['total'] = $currency->toCurrency($invoice['total']);

		$positionsDb = new Sales_Model_DbTable_Invoicepos();
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
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('sales', 'invoice')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
			$emailForm->subject->setValue($emailtemplate['subject']);
			$emailForm->body->setValue($emailtemplate['body']);
		}

		//Copy file to attachments
		$filename = $invoice['filename'];
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$contactFilePath = BASE_PATH.'/files/contacts/'.$contactUrl.'/'.$filename;
		$documentUrl = $this->_helper->Directory->getUrl($invoice['id']);
		$documentFilePath = BASE_PATH.'/files/attachments/sales/invoice/'.$documentUrl;
		if(file_exists($documentFilePath) && !file_exists($documentFilePath.'/'.$filename)) {
			if(copy($contactFilePath, $documentFilePath.'/'.$filename)) {
				$data = array();
				$data['documentid'] = $id;
				$data['filename'] = $filename;
				$data['filetype'] = mime_content_type($documentFilePath.'/'.$filename);
				$data['filesize'] = filesize($documentFilePath.'/'.$filename);
				$data['location'] = $documentFilePath.'/'.$filename;
				$data['module'] = 'sales';
				$data['controller'] = 'invoice';
				$data['ordering'] = 1;
			}
		}

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'sales', 'invoice');

		$this->view->invoice = $invoice;
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
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['invoiceid']);
		$data['title'] = $data['title'].' 2';
		$data['invoicedate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$invoice = new Sales_Model_DbTable_Invoice();
		echo $invoiceid = $invoice->addInvoice($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $invoiceid, 'sales', 'invoice', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function generatequoteAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['deliverydate'], $data['prepayment'], $data['ebayorderid']);
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$quote = new Sales_Model_DbTable_Quote();
		$quoteid = $quote->addQuote($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $quoteid, 'sales', 'quote', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_QUOTE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'quote', null, array('id' => $quoteid));
	}

	public function generatesalesorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['deliverydate'], $data['prepayment'], $data['ebayorderid']);
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$salesorder = new Sales_Model_DbTable_Salesorder();
		$salesorderid = $salesorder->addSalesorder($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $salesorderid, 'sales', 'salesorder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SALES_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'salesorder', null, array('id' => $salesorderid));
	}

	public function generatedeliveryorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
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

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $deliveryorderid, 'sales', 'deliveryorder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_DELIVERY_ORDER_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'deliveryorder', null, array('id' => $deliveryorderid));
	}

	public function generatecreditnoteAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$creditnote = new Sales_Model_DbTable_Creditnote();
		$creditnoteid = $creditnote->addCreditnote($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $creditnoteid, 'sales', 'creditnote', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_CREDIT_NOTE_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'creditnote', null, array('id' => $creditnoteid));
	}

	public function generatequoterequestAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
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

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $quoterequestid, 'purchases', 'quoterequest', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_QUOTE_REQUEST_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', 'quoterequest', 'purchases', array('id' => $quoterequestid));
	}


	public function generatepurchaseorderAction()
	{
		$id = $this->_getParam('id', 0);
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$data = $invoiceDb->getInvoice($id);

		unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
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

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $purchaseorderid, 'purchases', 'purchaseorder', $this->_date);

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
		$data['prepaymenttotal'] = $invoice['prepayment'];
		$data['customerid'] = $invoice['contactid'];
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
		unset($data['id'], $data['prepayment']);

		$process = new Processes_Model_DbTable_Process();
		$processID = $process->addProcess($data);

		$positionsDb = new Sales_Model_DbTable_Invoicepos();
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

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($invoice['contactid']);

		//Set language
		if($invoice['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$invoice['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($invoice['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
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
			if($invoice['prepayment']) {
				$invoice['balance'] = $currency->toCurrency($invoice['total']-$invoice['prepayment']);
				$invoice['prepayment'] = $currency->toCurrency($invoice['prepayment']);
			}
			$invoice['taxes'] = $currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->invoice = $invoice;
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

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($invoice['contactid']);

		if($invoice['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($invoice['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($invoice['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$invoice['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($invoice['currency'], 'USE_SYMBOL');

		//Set new document Id and filename
		if(!$invoice['invoiceid']) {
			//Set new invoice Id
			$incrementDb = new Application_Model_DbTable_Increment();
			$increment = $incrementDb->getIncrement('invoiceid');
			$filenameDb = new Application_Model_DbTable_Filename();
			$filename = $filenameDb->getFilename('invoice', $invoice['language']);
			$filename = str_replace('%NUMBER%', $increment, $filename);
			$invoiceDb->saveInvoice($id, $increment, $filename);
			$incrementDb->setIncrement(($increment+1), 'invoiceid');
			$invoice = $invoiceDb->getInvoice($id);
		}

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
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
			if($invoice['prepayment']) {
				$invoice['balance'] = $currency->toCurrency($invoice['total']-$invoice['prepayment']);
				$invoice['prepayment'] = $currency->toCurrency($invoice['prepayment']);
			}
			$invoice['taxes'] = $currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($invoice['templateid']);

		$this->view->invoice = $invoice;
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

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($invoice['contactid']);

		if($invoice['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($invoice['templateid']);
			if($template['filename']) $this->_helper->viewRenderer->setRender($template['filename']);
			$this->view->template = $template;
		}

		//Set language
		if($invoice['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$invoice['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($invoice['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
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
			if($invoice['prepayment']) {
				$invoice['balance'] = $currency->toCurrency($invoice['total']-$invoice['prepayment']);
				$invoice['prepayment'] = $currency->toCurrency($invoice['prepayment']);
			}
			$invoice['taxes'] = $currency->toCurrency($invoice['taxes']);
			$invoice['subtotal'] = $currency->toCurrency($invoice['subtotal']);
			$invoice['total'] = $currency->toCurrency($invoice['total']);
			if($invoice['taxfree']) {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$invoice['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($invoice['templateid']);

		$this->view->invoice = $invoice;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->footers = $footers;
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
				$mail->setSubject($subject);

				if($file) {
					$file = explode("|", $file);
					$att = file_get_contents(APPLICATION_PATH.'/..'.$file[0]);
					$at = $mail->createAttachment($att);
					$at->filename = $file[1];
				}

				$mail->send();

$mail = new Zend_Mail_Storage_Imap(array('host'	 => $this->_config->smtphost,
					'user'	 => $this->_config->smtpuser,
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


/* if ($_FILES["file"]["error"] > 0)
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
			$invoice->setState($id, 106);
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

			$positionsDb = new Sales_Model_DbTable_Invoicepos();
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
