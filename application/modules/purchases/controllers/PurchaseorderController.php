<?php

class Purchases_PurchaseorderController extends Zend_Controller_Action
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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Purchases_Model_Get();
		$purchaseorders = $get->purchaseorders($params, $options, $this->_flashMessenger);

		$this->view->purchaseorders = $purchaseorders;
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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Purchases_Model_Get();
		$purchaseorders = $get->purchaseorders($params, $options, $this->_flashMessenger);

		$this->view->purchaseorders = $purchaseorders;
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
		$data['title'] = $this->view->translate('PURCHASE_ORDERS_NEW_PURCHASE_ORDER');
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

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$id = $purchaseorderDb->addPurchaseorder($data);

		$this->_helper->redirector->gotoSimple('edit', 'purchaseorder', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($id);

		if($purchaseorder['purchaseorderid'] && !$request->isPost()) {
			$this->_helper->redirector->gotoSimple('view', 'purchaseorder', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $purchaseorder['locked'], $purchaseorder['lockedtime']);

			$form = new Purchases_Form_Purchaseorder();
			$options = $this->_helper->Options->getOptions($form);

			//Get contact
			if($purchaseorder['contactid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($purchaseorder['contactid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($contact['id']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmails($contact['id']);

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
					$textblockDb = new Purchases_Model_DbTable_Textblock();
					if(strpos($element, 'header') !== false) {
						$data['text'] = $data['textblockheader'];
						unset($data['textblockheader']);
						$textblockDb->updateTextblock($data, 'purchaseorder', 'header');
					} elseif(strpos($element, 'footer') !== false) {
						$data['text'] = $data['textblockfooter'];
						unset($data['textblockfooter']);
						$textblockDb->updateTextblock($data, 'purchaseorder', 'footer');
					}
				} elseif(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Sales_Model_DbTable_Purchaseorderpos();
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
						$contactUrl = $this->_helper->Directory->getUrl($data['contactid']);
						$defaultNamespace = new Zend_Session_Namespace('RF');
						$defaultNamespace->view_type = '1'; //detailed list
						$defaultNamespace->subfolder = 'contacts/'.$contactUrl;
					}

					$purchaseorderDb->updatePurchaseorder($id, $data);
					echo Zend_Json::encode($purchaseorderDb->getPurchaseorder($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $purchaseorder;
					if($purchaseorder['contactid']) {
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
					$toolbar = new Purchases_Form_Toolbar();
					$toolbar->state->setValue($data['state']);

					//Get text blocks
					$textblocksDb = new Purchases_Model_DbTable_Textblock();
					$textblocks = $textblocksDb->getTextblocks('purchaseorder');

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

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($purchaseorder['contactid']);

		//Convert dates to the display format
		if($purchaseorder['purchaseorderdate']) $purchaseorder['purchaseorderdate'] = date("d.m.Y", strtotime($purchaseorder['purchaseorderdate']));
		if($purchaseorder['orderdate']) $purchaseorder['orderdate'] = date("d.m.Y", strtotime($purchaseorder['orderdate']));
		if($purchaseorder['deliverydate']) $purchaseorder['deliverydate'] = date("d.m.Y", strtotime($purchaseorder['deliverydate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($purchaseorder['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$purchaseorder['taxes'] = $currency->toCurrency($purchaseorder['taxes']);
		$purchaseorder['subtotal'] = $currency->toCurrency($purchaseorder['subtotal']);
		$purchaseorder['total'] = $currency->toCurrency($purchaseorder['total']);

		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			//Use price rules on all positions
			$price = $this->_helper->PriceRule->usePriceRulesOnPositions($positions, 'purchases', 'purchaseorderpos');
			foreach($positions as $position) {
				$position->description = str_replace("\n", '<br>', $position->description);
				$position->total = $currency->toCurrency($price['calculated'][$position->id]*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
				if(isset($price['rules'][$position->id])) $price['rules'][$position->id] = $this->_helper->PriceRule->formatPriceRules($price['rules'][$position->id], $currency, $locale);
			}
			$this->view->pricerules = $price['rules'];

			//Get price rule actions
			$priceruleactionDb = new Application_Model_DbTable_Priceruleaction();
			$priceruleactions = $priceruleactionDb->getPriceruleactions();
			$this->view->priceruleactions = $priceruleactions;
		}

		$toolbar = new Purchases_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		//Get email
		$emailDb = new Contacts_Model_DbTable_Email();
		$contact['email'] = $emailDb->getEmails($contact['id']);

		//Get email form
		$emailForm = new Contacts_Form_Emailmessage();
		if($contact['email']) {
			foreach($contact['email'] as $option) {
				$emailForm->recipient->addMultiOption($option['id'], $option['email']);
			}
		}

		//Get email templates
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('purchases', 'purchaseorder')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);

			//Search and replace placeholders
			$searchArray = array('[DOCID]', '[CONTACTID]');
			$replaceArray = array($purchaseorder['purchaseorderid'], $purchaseorder['contactid']);
			$emailBody = str_replace($searchArray, $replaceArray, $emailtemplate['body']);
			$emailSubject = str_replace($searchArray, $replaceArray, $emailtemplate['subject']);
			$emailForm->body->setValue($emailBody);
			$emailForm->subject->setValue($emailSubject);
		}

		//Copy file to attachments
		$filename = $purchaseorder['filename'];
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$contactFilePath = BASE_PATH.'/files/contacts/'.$contactUrl.'/'.$filename;
		$documentUrl = $this->_helper->Directory->getUrl($purchaseorder['id']);
		$documentFilePath = BASE_PATH.'/files/attachments/purchases/purchaseorder/'.$documentUrl;
		if(file_exists($documentFilePath) && !file_exists($documentFilePath.'/'.$filename)) {
			if(copy($contactFilePath, $documentFilePath.'/'.$filename)) {
				$data = array();
				$data['documentid'] = $id;
				$data['filename'] = $filename;
				$data['filetype'] = mime_content_type($documentFilePath.'/'.$filename);
				$data['filesize'] = filesize($documentFilePath.'/'.$filename);
				$data['location'] = $documentFilePath;
				$data['module'] = 'purchases';
				$data['controller'] = 'purchaseorder';
				$data['ordering'] = 1;
			}
		}

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'purchases', 'purchaseorder');

		$this->view->purchaseorder = $purchaseorder;
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
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$data = $purchaseorderDb->getPurchaseorder($id);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['purchaseorderid']);
		$data['title'] = $data['title'].' 2';
		$data['purchaseorderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
		echo $purchaseorderid = $purchaseorder->addPurchaseorder($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $purchaseorderid, 'purchases', 'purchaseorder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function generateAction()
	{
		$id = $this->_getParam('id', 0);
		$target = $this->_getParam('target', 0);
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$data = $purchaseorderDb->getPurchaseorder($id);

		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		if($target == 'salesorder') {
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'quoterequest') {
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
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('purchases', $module), array('purchaseorder', $target), $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_DOCUMENT_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', $target, $module, array('id' => $newid));
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
			$this->view->template = $template;
		}

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($purchaseorder['contactid']);

		//Set language
		if($purchaseorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$purchaseorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($purchaseorder['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			//Use price rules on all positions
			$price = $this->_helper->PriceRule->usePriceRulesOnPositions($positions, 'purchases', 'purchaseorderpos');

			//Set precision and currency
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($price['calculated'][$position->id]*$position->quantity);
				$position->price = $currency->toCurrency($price['calculated'][$position->id]);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$purchaseorder['taxes'] = $currency->toCurrency($purchaseorder['taxes']);
			$purchaseorder['subtotal'] = $currency->toCurrency($purchaseorder['subtotal']);
			$purchaseorder['total'] = $currency->toCurrency($purchaseorder['total']);
			if($purchaseorder['taxfree']) {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($templateid);

		$this->view->purchaseorder = $purchaseorder;
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

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($purchaseorder['contactid']);

		if($purchaseorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($purchaseorder['templateid']);
			$this->view->template = $template;
		}

		//Set language
		if($purchaseorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$purchaseorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($purchaseorder['currency'], 'USE_SYMBOL');

		//Get positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);

		//Set new document Id and filename
		if(!$purchaseorder['purchaseorderid']) {
			//Set new purchaseorder Id
			$incrementDb = new Application_Model_DbTable_Increment();
			$increment = $incrementDb->getIncrement('purchaseorderid');
			$filenameDb = new Application_Model_DbTable_Filename();
			$filename = $filenameDb->getFilename('purchaseorder', $purchaseorder['language']);
			$filename = str_replace('%NUMBER%', $increment, $filename);
			$purchaseorderDb->savePurchaseorder($id, $increment, $filename);
			$incrementDb->setIncrement(($increment), 'purchaseorderid');
			$purchaseorder = $purchaseorderDb->getPurchaseorder($id);
		}

		if(count($positions)) {
			//Use price rules on all positions
			$price = $this->_helper->PriceRule->usePriceRulesOnPositions($positions, 'purchases', 'purchaseorderpos');

			//Set precision and currency
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity, array('precision' => $precision, 'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$purchaseorder['taxes'] = $currency->toCurrency($purchaseorder['taxes']);
			$purchaseorder['subtotal'] = $currency->toCurrency($purchaseorder['subtotal']);
			$purchaseorder['total'] = $currency->toCurrency($purchaseorder['total']);
			if($purchaseorder['taxfree']) {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($purchaseorder['templateid']);

		$this->view->purchaseorder = $purchaseorder;
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

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($purchaseorder['contactid']);

		if($purchaseorder['templateid']) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate($purchaseorder['templateid']);
			$this->view->template = $template;
		}

		//Set language
		if($purchaseorder['language']) {
			$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$purchaseorder['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($purchaseorder['currency'], 'USE_SYMBOL');

		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			//Use price rules on all positions
			$price = $this->_helper->PriceRule->usePriceRulesOnPositions($positions, 'purchases', 'purchaseorderpos');

			//Set precision and currency
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($position->price*$position->quantity);
				$position->price = $currency->toCurrency($position->price);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity, array('precision' => $precision, 'locale' => Zend_Registry::get('Zend_Locale')));
			}

			$purchaseorder['taxes'] = $currency->toCurrency($purchaseorder['taxes']);
			$purchaseorder['subtotal'] = $currency->toCurrency($purchaseorder['subtotal']);
			$purchaseorder['total'] = $currency->toCurrency($purchaseorder['total']);
			if($purchaseorder['taxfree']) {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$purchaseorder['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}
		}

		//Get footers
		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters($purchaseorder['templateid']);

		$this->view->purchaseorder = $purchaseorder;
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
			$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
			$purchaseorder->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
			$purchaseorder->deletePurchaseorder($id);

			$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
			$positions = $positionsDb->getPositions($id);
			foreach($positions as $position) {
				$positionsDb->deletePosition($position->id);
			}
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function pinAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Pin->toogle($id);
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
