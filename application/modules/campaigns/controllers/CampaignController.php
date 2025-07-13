<?php

class Campaigns_CampaignController extends Zend_Controller_Action
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

		//Check if the directory is writable
		$id = 0;
		if($this->view->contactid) $id = $this->view->contactid;
		elseif($this->view->id) $id = $this->view->id;
		if($id) $this->view->dirwritable = $this->_helper->Directory->isWritable($id, 'campaign', $this->_flashMessenger);
		if($id) $this->view->dirwritable = $this->_helper->Directory->isWritable($id, 'attachment', $this->_flashMessenger);
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Campaigns_Form_Toolbar();
		if(isset($form->$element)) {
			$this->_helper->Options->getOptions($form);
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Campaigns_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Campaigns_Model_Get();
		$campaigns = $get->campaigns($params, $options, $this->_flashMessenger);

		//Get positions
		$campaignIDs = array();
		foreach($campaigns as $campaign) {
			array_push($campaignIDs, $campaign['id']);
		}

		$this->view->campaigns = $campaigns;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		//$this->view->positions = $this->getPositions($campaignIDs);
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

		$toolbar = new Campaigns_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Campaigns_Model_Get();
		$campaigns = $get->campaigns($params, $options, $this->_flashMessenger);

		//Get positions
		$campaignIDs = array();
		foreach($campaigns as $campaign) {
			array_push($campaignIDs, $campaign['id']);
		}

		$this->view->campaigns = $campaigns;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($campaignIDs);
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
		$data['title'] = $this->view->translate('CAMPAIGNS_NEW_CAMPAIGN');
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

		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$id = $campaignDb->addCampaign($data);

		$this->_helper->redirector->gotoSimple('edit', 'campaign', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		//$element = $this->_getParam('element', null);
		$activeTab = $request->getCookie('tab', null);

		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);

		if($campaign['completed'] || $campaign['cancelled']) {
			$this->_helper->redirector->gotoSimple('view', 'campaign', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $campaign['locked'], $campaign['lockedtime']);

			$form = new Campaigns_Form_Campaign();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data) || true) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
						/*$positions = $positionsDb->getPositions($id);
						foreach($positions as $position) {
							$positionsDb->updatePosition($position->id, array('currency' => $data['currency']));
						}*/
						//$this->_helper->Currency->convert($id, 'creditnote');
					}
					if(isset($data['expectedrevenue'])) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['expectedrevenue'] =  Zend_Locale_Format::getNumber($data['expectedrevenue'], array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['budgetedcost'])) {
						if($data['budgetedcost']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['budgetedcost'] =  Zend_Locale_Format::getNumber($data['budgetedcost'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['budgetedcost'] = NULL;
						}
					}
					if(isset($data['actualcost'])) {
						if($data['actualcost']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['actualcost'] =  Zend_Locale_Format::getNumber($data['actualcost'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['actualcost'] = NULL;
						}
					}
					if(isset($data['startdate'])) {
						if(Zend_Date::isDate($data['startdate'])) {
							$startdate = new Zend_Date($data['startdate'], Zend_Date::DATES, 'de');
							$data['startdate'] = $startdate->get('yyyy-MM-dd');
						} else {
							$data['startdate'] = NULL;
						}
					}
					if(isset($data['duedate'])) {
						if(Zend_Date::isDate($data['duedate'])) {
							$duedate = new Zend_Date($data['duedate'], Zend_Date::DATES, 'de');
							$data['duedate'] = $duedate->get('yyyy-MM-dd');
						} else {
							$data['duedate'] = NULL;
						}
					}
					if(isset($data['cc'])) {
						$data['emailcc'] = $data['cc'];
						unset($data['cc']);
					}
					if(isset($data['bcc'])) {
						$data['emailbcc'] = $data['bcc'];
						unset($data['bcc']);
					}
					if(isset($data['replyto'])) {
						$data['emailreplyto'] = $data['replyto'];
						unset($data['replyto']);
					}
					if(isset($data['subject'])) {
						$data['emailsubject'] = $data['subject'];
						unset($data['subject']);
					}
					if(isset($data['body'])) {
						$data['emailbody'] = $data['body'];
						unset($data['body']);
					}

					$campaignDb->updateCampaign($id, $data);
					echo Zend_Json::encode($campaignDb->getCampaign($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $campaign;

					//Toolbar
					$toolbar = new Campaigns_Form_Toolbar();
					$options = $this->_helper->Options->getOptions($toolbar);
					$params = $this->_helper->Params->getParams($toolbar, $options);
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Campaigns_Form_ToolbarPositions();

					//Get contacts
					$get = new Contacts_Model_Get();
					$params['limit'] = 0;
					$params['catid'] = $data['contactcatid'];
					list($contacts, $records) = $get->contacts($params, $options, 1000);

					//Get already sent emails on champaign
					$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
					$emailmessageArray = $emailmessageDb->getEmailmessages(NULL, $id, 'campaigns', 'campaign');
					$emailmessages = array();
					foreach($emailmessageArray as $emailmessage) {
						$emailmessages[$emailmessage['contactid']][] = $emailmessage;
					}
//print_r($options);
//print_r($params);

					//Get currency
					$currency = $this->_helper->Currency->getCurrency($data['currency']);
					$data['expectedrevenue'] = $currency->toCurrency($data['expectedrevenue']);
					if($data['budgetedcost']) $data['budgetedcost'] = $currency->toCurrency($data['budgetedcost']);
					if($data['actualcost']) $data['actualcost'] = $currency->toCurrency($data['actualcost']);
					//Convert dates to the display format
					$startdate = new Zend_Date($data['startdate']);
					if($data['startdate']) $data['startdate'] = $startdate->get('dd.MM.yyyy');
					$duedate = new Zend_Date($data['duedate']);
					if($data['duedate']) $data['duedate'] = $duedate->get('dd.MM.yyyy');

					foreach($contacts as $contact) {
						//Email
						$emailDb = new Contacts_Model_DbTable_Email();
						$email = $emailDb->getEmails($contact['id']);
					}

					//Get email form
					$emailForm = new Contacts_Form_Emailmessage();

					//Get email templates
					/*$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
					if($emailtemplate = $emailtemplateDb->getEmailtemplate('contacts', 'contact')) {
						if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
						if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
						if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
						$emailForm->subject->setValue($emailtemplate['subject']);
						$emailForm->body->setValue($emailtemplate['body']);
					}*/

					if($data['emailcc']) $emailForm->cc->setValue($data['emailcc']);
					if($data['emailbcc']) $emailForm->bcc->setValue($data['emailbcc']);
					if($data['emailreplyto']) $emailForm->replyto->setValue($data['emailreplyto']);
					$emailForm->subject->setValue($data['emailsubject']);
					$emailForm->body->setValue($data['emailbody']);

					$this->view->emailForm = $emailForm;
					$this->view->url = $this->_helper->Directory->getUrl($contact['contactid']);

					//Get email attachments
					$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
					$attachments = $emailattachmentDb->getEmailattachments($id, 'campaigns', 'campaign');

					$form->populate($data);

					$userDb = new Users_Model_DbTable_User();
					$users = $userDb->getUsers();

					$this->view->form = $form;
					$this->view->users = $users;
					$this->view->activeTab = $activeTab;
					$this->view->contacts = $contacts;
					$this->view->attachments = $attachments;
					$this->view->emailmessages = $emailmessages;
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

		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($campaign['customerid']);

		//Convert dates to the display format
		if($campaign['campaigndate']) $campaign['campaigndate'] = date('d.m.Y', strtotime($campaign['campaigndate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($campaign['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$campaign['taxes'] = $currency->toCurrency($campaign['taxes']);
		$campaign['subtotal'] = $currency->toCurrency($campaign['subtotal']);
		$campaign['total'] = $currency->toCurrency($campaign['total']);

		$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Campaigns_Form_Toolbar();
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
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('campaigns', 'campaign')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
			$emailForm->subject->setValue($emailtemplate['subject']);
			$emailForm->body->setValue($emailtemplate['body']);
		}

		//Copy file to attachments
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$documentUrl = $this->_helper->Directory->getUrl($campaign['id']);

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'campaigns', 'campaign');

		$this->view->campaign = $campaign;
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
		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);

		$data = $campaign;
		unset($data['id'], $data['campaignid']);
		$data['title'] = $campaign['title'].' 2';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		echo $newID = $campaignDb->addCampaign($data);

		$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
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
			$campaign = new Campaigns_Model_DbTable_Campaign();
			$campaign->setState($id, 7);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$campaign = new Campaigns_Model_DbTable_Campaign();
			$campaign->deleteCampaign($id);

			$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
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

	protected function getPositions($campaignIDs)
	{
		$positions = array();
		if(!empty($campaignIDs)) {
			$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
			$positionsObject = $positionsDb->getPositions($campaignIDs);

			foreach($positionsObject as $position) {
				if(!isset($previous[$position->parentid])) {
					$previous[$position->parentid] = array();
					$previous[$position->parentid]['ordering'] = 0;
					$previous[$position->parentid]['quantity'] = 1;
					$previous[$position->parentid]['deliverystatus'] = '';
					$previous[$position->parentid]['deliverydate'] = NULL;
					$previous[$position->parentid]['supplierorderstatus'] = '';
				}
				if($previous[$position->parentid]['ordering'] && ($previous[$position->parentid]['deliverystatus'] == $position->deliverystatus) && ($previous[$position->parentid]['deliverydate'] == $position->deliverydate) && ($previous[$position->parentid]['supplierorderstatus'] == $position->supplierorderstatus)) {
					$positions[$position->parentid][$position->ordering] = $positions[$position->parentid][$previous[$position->parentid]['ordering']];
					$positions[$position->parentid][$position->ordering]['quantity'] = ($previous[$position->parentid]['quantity'] + 1);
					unset($positions[$position->parentid][$previous[$position->parentid]['ordering']]);
					$previous[$position->parentid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->parentid]['quantity'] = $positions[$position->parentid][$position->ordering]['quantity'];
					$previous[$position->parentid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->parentid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
					$previous[$position->parentid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				} else {
					$positions[$position->parentid][$position->ordering]['deliverystatus'] = $position->deliverystatus;
					if($position->deliverydate)
						//$deliverydate = new Zend_Date($position->deliverydate);
						//if($position->deliverydate) $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
						$positions[$position->parentid][$position->ordering]['deliverydate'] = $position->deliverydate;
					if($position->itemtype == 'deliveryItem')
						$positions[$position->parentid][$position->ordering]['supplierorderstatus'] = $position->supplierorderstatus;
					$previous[$position->parentid] = array();
					$previous[$position->parentid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->parentid]['quantity'] = 1;
					$previous[$position->parentid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->parentid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
					$previous[$position->parentid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				}
			}
		}
		return $positions;
	}
}
