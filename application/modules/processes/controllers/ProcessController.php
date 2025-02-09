<?php

class Processes_ProcessController extends Zend_Controller_Action
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
		$form = new Processes_Form_Toolbar();
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

		$toolbar = new Processes_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Processes_Model_Get();
		$processes = $get->processes($params, $options, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);

			if($process['deliverydate']) {
				$deliverydate = new Zend_Date($process['deliverydate']);
							$process['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
						}
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
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

		$toolbar = new Processes_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Processes_Model_Get();
		$processes = $get->processes($params, $options, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function addAction()
	{
		$customerid = $this->_getParam('customerid', 0);

		//Get primary currency
		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		$data = array();
		$data['title'] = $this->view->translate('PROCESSES_NEW_PROCESS');
		$data['deliverystatus'] = 'deliveryIsWaiting';
		$data['paymentstatus'] = 'waitingForPayment';
		$data['currency'] = $currency['code'];
		$data['state'] = 100;

		//Get contact data
		if($customerid) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContact($customerid);

			//Get basic data
			$data['customerid'] = $contact['contactid'];
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

		$processDb = new Processes_Model_DbTable_Process();
		$id = $processDb->addProcess($data);

		$this->_helper->redirector->gotoSimple('edit', 'process', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		//$element = $this->_getParam('element', null);
		$activeTab = $request->getCookie('tab', null);

		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		if($process['completed'] || $process['cancelled']) {
			$this->_helper->redirector->gotoSimple('view', 'process', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $process['locked'], $process['lockedtime']);

			$form = new Processes_Form_Process();
			$options = $this->_helper->Options->getOptions($form);

			//Get contact
			if($process['customerid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($process['customerid']);

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
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Processes_Model_DbTable_Processpos();
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
					if(isset($data['salesorderid']) && !$data['salesorderid']) $data['salesorderid'] = 0;
					if(isset($data['invoiceid']) && !$data['invoiceid']) $data['invoiceid'] = 0;
					if(isset($data['prepaymentinvoiceid']) && !$data['prepaymentinvoiceid']) $data['prepaymentinvoiceid'] = 0;
					if(isset($data['deliveryorderid']) && !$data['deliveryorderid']) $data['deliveryorderid'] = 0;
					if(isset($data['creditnoteid']) && !$data['creditnoteid']) $data['creditnoteid'] = 0;
					if(isset($data['purchaseorderid']) && !$data['purchaseorderid']) $data['purchaseorderid'] = 0;
					if(isset($data['supplierid']) && !$data['supplierid']) $data['supplierid'] = 0;
					if(isset($data['total'])) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['total'] =  Zend_Locale_Format::getNumber($data['total'], array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['supplierinvoicetotal'])) {
						if($data['supplierinvoicetotal']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['supplierinvoicetotal'] =  Zend_Locale_Format::getNumber($data['supplierinvoicetotal'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['supplierinvoicetotal'] = NULL;
						}
					}
					if(isset($data['prepaymenttotal'])) {
						if($data['prepaymenttotal']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['prepaymenttotal'] =  Zend_Locale_Format::getNumber($data['prepaymenttotal'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['prepaymenttotal'] = NULL;
						}
					}
					if(isset($data['creditnotetotal'])) {
						if($data['creditnotetotal']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['creditnotetotal'] =  Zend_Locale_Format::getNumber($data['creditnotetotal'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['creditnotetotal'] = NULL;
						}
					}
					if(isset($data['paymentdate'])) {
						if(Zend_Date::isDate($data['paymentdate'])) {
							$paymentdate = new Zend_Date($data['paymentdate'], Zend_Date::DATES, 'de');
							$data['paymentdate'] = $paymentdate->get('yyyy-MM-dd');
						} else {
							$data['paymentdate'] = NULL;
						}
					}
					if(isset($data['invoicedate'])) {
						if(Zend_Date::isDate($data['invoicedate'])) {
							$invoicedate = new Zend_Date($data['invoicedate'], Zend_Date::DATES, 'de');
							$data['invoicedate'] = $invoicedate->get('yyyy-MM-dd');
						} else {
							$data['invoicedate'] = NULL;
						}
					}
					if(isset($data['prepaymentdate'])) {
						if(Zend_Date::isDate($data['prepaymentdate'])) {
							$prepaymentdate = new Zend_Date($data['prepaymentdate'], Zend_Date::DATES, 'de');
							$data['prepaymentdate'] = $prepaymentdate->get('yyyy-MM-dd');
						} else {
							$data['prepaymentdate'] = NULL;
						}
					}
					if(isset($data['prepaymentinvoicedate'])) {
						if(Zend_Date::isDate($data['prepaymentinvoicedate'])) {
							$prepaymentinvoicedate = new Zend_Date($data['prepaymentinvoicedate'], Zend_Date::DATES, 'de');
							$data['prepaymentinvoicedate'] = $prepaymentinvoicedate->get('yyyy-MM-dd');
						} else {
							$data['prepaymentinvoicedate'] = NULL;
						}
					}
					if(isset($data['creditnotedate'])) {
						if(Zend_Date::isDate($data['creditnotedate'])) {
							$creditnotedate = new Zend_Date($data['creditnotedate'], Zend_Date::DATES, 'de');
							$data['creditnotedate'] = $creditnotedate->get('yyyy-MM-dd');
						} else {
							$data['creditnotedate'] = NULL;
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
					if(isset($data['deliveryorderdate'])) {
						if(Zend_Date::isDate($data['deliveryorderdate'])) {
							$deliveryorderdate = new Zend_Date($data['deliveryorderdate'], Zend_Date::DATES, 'de');
							$data['deliveryorderdate'] = $deliveryorderdate->get('yyyy-MM-dd');
						} else {
							$data['deliveryorderdate'] = NULL;
						}
					}
					if(isset($data['purchaseorderdate'])) {
						if(Zend_Date::isDate($data['purchaseorderdate'])) {
							$purchaseorderdate = new Zend_Date($data['purchaseorderdate'], Zend_Date::DATES, 'de');
							$data['purchaseorderdate'] = $purchaseorderdate->get('yyyy-MM-dd');
						} else {
							$data['purchaseorderdate'] = NULL;
						}
					}
					if(isset($data['suppliersalesorderdate'])) {
						if(Zend_Date::isDate($data['suppliersalesorderdate'])) {
							$suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate'], Zend_Date::DATES, 'de');
							$data['suppliersalesorderdate'] = $suppliersalesorderdate->get('yyyy-MM-dd');
						} else {
							$data['suppliersalesorderdate'] = NULL;
						}
					}
					if(isset($data['supplierinvoicedate'])) {
						if(Zend_Date::isDate($data['supplierinvoicedate'])) {
							$supplierinvoicedate = new Zend_Date($data['supplierinvoicedate'], Zend_Date::DATES, 'de');
							$data['supplierinvoicedate'] = $supplierinvoicedate->get('yyyy-MM-dd');
						} else {
							$data['supplierinvoicedate'] = NULL;
						}
					}
					if(isset($data['supplierpaymentdate'])) {
						if(Zend_Date::isDate($data['supplierpaymentdate'])) {
							$supplierpaymentdate = new Zend_Date($data['supplierpaymentdate'], Zend_Date::DATES, 'de');
							$data['supplierpaymentdate'] = $supplierpaymentdate->get('yyyy-MM-dd');
						} else {
							$data['supplierpaymentdate'] = NULL;
						}
					}

					//Update file manager subfolder if contact is changed
					if(isset($data['customerid']) && $data['customerid']) {
						$dir1 = substr($data['customerid'], 0, 1).'/';
						if(strlen($data['customerid']) > 1) $dir2 = substr($data['customerid'], 1, 1).'/';
						else $dir2 = '0/';
						$defaultNamespace = new Zend_Session_Namespace('RF');
						$defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$data['customerid'].'/';
					}

					$processDb->updateProcess($id, $data);
					echo Zend_Json::encode($processDb->getProcess($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $process;
					if($process['customerid']) {
						$data['customerinfo'] = $contact['info'];
						$form->customerinfo->setAttrib('data-id', $contact['id']);
						$form->customerinfo->setAttrib('data-controller', 'contact');
						$form->customerinfo->setAttrib('data-module', 'contacts');
						$form->customerinfo->setAttrib('readonly', null);
					}
					//Get currency
					$currency = $this->_helper->Currency->getCurrency($data['currency']);
					$data['total'] = $currency->toCurrency($data['total']);
					if($data['prepaymenttotal']) $data['prepaymenttotal'] = $currency->toCurrency($data['prepaymenttotal']);
					if($data['creditnotetotal']) $data['creditnotetotal'] = $currency->toCurrency($data['creditnotetotal']);
					if($data['supplierinvoicetotal']) $data['supplierinvoicetotal'] = $currency->toCurrency($data['supplierinvoicetotal']);
					if($process['editpositionsseparately']) {
						$form->deliverystatus->setAttrib('disabled', 'disabled');
						$form->shippingmethod->setAttrib('disabled', 'disabled');
						$form->deliverydate->setAttrib('disabled', 'disabled');
						$form->shipmentdate->setAttrib('disabled', 'disabled');
						$form->shipmentnumber->setAttrib('disabled', 'disabled');
						$form->deliveryorderid->setAttrib('disabled', 'disabled');
						$form->deliveryorderdate->setAttrib('disabled', 'disabled');
						$form->supplierid->setAttrib('disabled', 'disabled');
						$form->purchaseorderid->setAttrib('disabled', 'disabled');
						$form->suppliersalesorderid->setAttrib('disabled', 'disabled');
						$form->supplierinvoiceid->setAttrib('disabled', 'disabled');
						$form->supplierinvoicetotal->setAttrib('disabled', 'disabled');
						$form->supplierorderstatus->setAttrib('disabled', 'disabled');
						$form->suppliername->setAttrib('disabled', 'disabled');
						$form->purchaseorderdate->setAttrib('disabled', 'disabled');
						$form->suppliersalesorderdate->setAttrib('disabled', 'disabled');
						$form->supplierinvoicedate->setAttrib('disabled', 'disabled');
						$form->supplierpaymentdate->setAttrib('disabled', 'disabled');
					}
					if($data['salesorderid'] == 0) $data['salesorderid'] = NULL;
					if($data['invoiceid'] == 0) $data['invoiceid'] = NULL;
					if($data['prepaymentinvoiceid'] == 0) $data['prepaymentinvoiceid'] = NULL;
					if($data['deliveryorderid'] == 0) $data['deliveryorderid'] = NULL;
					if($data['creditnoteid'] == 0) $data['creditnoteid'] = NULL;
					if($data['purchaseorderid'] == 0) $data['purchaseorderid'] = NULL;
					if($data['supplierid'] == 0) $data['supplierid'] = NULL;
					//Convert dates to the display format
					$paymentdate = new Zend_Date($data['paymentdate']);
					if($data['paymentdate']) $data['paymentdate'] = $paymentdate->get('dd.MM.yyyy');
					$invoicedate = new Zend_Date($data['invoicedate']);
					if($data['invoicedate']) $data['invoicedate'] = $invoicedate->get('dd.MM.yyyy');
					$prepaymentdate = new Zend_Date($data['prepaymentdate']);
					if($data['prepaymentdate']) $data['prepaymentdate'] = $prepaymentdate->get('dd.MM.yyyy');
					$prepaymentinvoicedate = new Zend_Date($data['prepaymentinvoicedate']);
					if($data['prepaymentinvoicedate']) $data['prepaymentinvoicedate'] = $prepaymentinvoicedate->get('dd.MM.yyyy');
					$creditnotedate = new Zend_Date($data['creditnotedate']);
					if($data['creditnotedate']) $data['creditnotedate'] = $creditnotedate->get('dd.MM.yyyy');
					$deliverydate = new Zend_Date($data['deliverydate']);
					if($data['deliverydate']) $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
					$deliveryorderdate = new Zend_Date($data['deliveryorderdate']);
					if($data['deliveryorderdate']) $data['deliveryorderdate'] = $deliveryorderdate->get('dd.MM.yyyy');
					$purchaseorderdate = new Zend_Date($data['purchaseorderdate']);
					if($data['purchaseorderdate']) $data['purchaseorderdate'] = $purchaseorderdate->get('dd.MM.yyyy');
					$suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate']);
					if($data['suppliersalesorderdate']) $data['suppliersalesorderdate'] = $suppliersalesorderdate->get('dd.MM.yyyy');
					$supplierinvoicedate = new Zend_Date($data['supplierinvoicedate']);
					if($data['supplierinvoicedate']) $data['supplierinvoicedate'] = $supplierinvoicedate->get('dd.MM.yyyy');
					$supplierpaymentdate = new Zend_Date($data['supplierpaymentdate']);
					if($data['supplierpaymentdate']) $data['supplierpaymentdate'] = $supplierpaymentdate->get('dd.MM.yyyy');

					$form->populate($data);

					//Toolbar
					$toolbar = new Processes_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Processes_Form_ToolbarPositions();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
					$this->view->toolbarPositions = $toolbarPositions;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($process['customerid']);

		//Convert dates to the display format
		if($process['processdate']) $process['processdate'] = date('d.m.Y', strtotime($process['processdate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($process['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$process['taxes'] = $currency->toCurrency($process['taxes']);
		$process['subtotal'] = $currency->toCurrency($process['subtotal']);
		$process['total'] = $currency->toCurrency($process['total']);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Processes_Form_Toolbar();
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
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('processes', 'process')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
			$emailForm->subject->setValue($emailtemplate['subject']);
			$emailForm->body->setValue($emailtemplate['body']);
		}

		//Copy file to attachments
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$documentUrl = $this->_helper->Directory->getUrl($process['id']);

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'processes', 'process');

		$this->view->process = $process;
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
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		$data = $process;
		unset($data['id'], $data['processid']);
		$data['title'] = $process['title'].' 2';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		echo $newID = $processDb->addProcess($data);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$positionData = $position->toArray();
			unset($positionData['id']);
			$positionData['parentid'] = $newID;
			$positionData['modified'] = NULL;
			$positionData['modifiedby'] = 0;
			$positionsDb->addPosition($positionData);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$process = new Processes_Model_DbTable_Process();
			$process->setState($id, 7);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$process = new Processes_Model_DbTable_Process();
			$process->deleteProcess($id);

			$positionsDb = new Processes_Model_DbTable_Processpos();
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

	protected function getPositions($processIDs)
	{
		$positions = array();
		if(empty($processIDs)) {
			return $positions;
		}

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positionsObject = $positionsDb->getPositions($processIDs);
		$previous = array();

		foreach($positionsObject as $position) {
			$parentId = $position->parentid;
			$ordering = $position->ordering ? $position->ordering : 0;

			// Initialize the parent array if not already
			if (!isset($previous[$parentId])) {
				$previous[$parentId] = [
					'ordering' => 0,
					'quantity' => 1,
					'deliverystatus' => '',
					'deliverydate' => null,
					'supplierorderstatus' => '',
				];
			}

			// Determine if the current position should be grouped with the previous
			$shouldMerge = $previous[$parentId]['ordering'] &&
						   $previous[$parentId]['deliverystatus'] === $position->deliverystatus &&
						   $previous[$parentId]['deliverydate'] === $position->deliverydate &&
						   $previous[$parentId]['supplierorderstatus'] === $position->supplierorderstatus;

			if($shouldMerge) {
				$positions[$parentId][$ordering] = $positions[$parentId][$previous[$parentId]['ordering']];
				$positions[$parentId][$ordering]['quantity'] = ($previous[$parentId]['quantity'] + 1);
				unset($positions[$parentId][$previous[$parentId]['ordering']]);
				$previous[$parentId]['ordering'] = $ordering ? $ordering : 0;
				$previous[$parentId]['quantity'] = $positions[$parentId][$ordering]['quantity'];
				$previous[$parentId]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
				$previous[$parentId]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
				$previous[$parentId]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
			} else {
				$positions[$parentId][$ordering]['deliverystatus'] = $position->deliverystatus;
				if($position->deliverydate)
					//$deliverydate = new Zend_Date($position->deliverydate);
					//if($position->deliverydate) $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
					$positions[$parentId][$ordering]['deliverydate'] = $position->deliverydate;
				if($position->itemtype == 'deliveryItem')
					$positions[$parentId][$ordering]['supplierorderstatus'] = $position->supplierorderstatus;
			}

			// Update the previous information for the current parent
			$previous[$parentId] = array();
			$previous[$parentId]['ordering'] = $ordering;
			$previous[$parentId]['quantity'] = 1;
			$previous[$parentId]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
			$previous[$parentId]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
			$previous[$parentId]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
		}

		return $positions;
	}
}
