<?php

class Contacts_ContactController extends Zend_Controller_Action
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
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		//$contactDb = new Contacts_Model_DbTable_Contact();
		//$contact = $contactDb->getContact($this->_getParam('id', 0));

		$get = new Contacts_Model_Get();
		$contact = $get->contact($this->_getParam('id', 0));

		header('Content-type: application/json');
		echo Zend_Json::encode($contact);
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		$contacts = $get->contacts($params, $options['categories']);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		$contacts = $get->contacts($params, $options['categories']);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$contactid = $this->_getParam('contactid', 0);

		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		if($contactid) {
			$params['keyword'] = $contactid;
			$toolbar->keyword->setValue($params['keyword']);
		}

		$get = new Contacts_Model_Get();
		$contacts = $get->contacts($params, $options['categories']);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		$data = array();
		$data['catid'] = $catid;

		$client = Zend_Registry::get('Client');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$id = $contactDb->addContact($data);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$contactDb->updateContact($id, array('contactid' => $increment));
		$incrementDb->setIncrement(($increment+1), 'contactid');

		$addressDb = new Contacts_Model_DbTable_Address();
		$addressDb->addAddress(array('contactid' => $id, 'type' => 'billing', 'country' => $client['country'], 'ordering' => 1));

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phoneDb->addPhone(array('contactid' => $id, 'type' => 'phone', 'ordering' => 1));

		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->addEmail(array('contactid' => $id, 'ordering' => 1));

		$internetDb = new Contacts_Model_DbTable_Internet();
		$internetDb->addInternet(array('contactid' => $id, 'ordering' => 1));

		//Check if the directory is writable
		$this->_helper->Directory->isWritable($id, 'contact', $this->_flashMessenger);

		$this->_helper->redirector->gotoSimple('edit', 'contact', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$contactDb = new Contacts_Model_DbTable_Contact();
		if($id) $contact = $contactDb->getContact($id);

		//Redirect to index if there is no data
		if(!$contact) {
			$this->_helper->redirector->gotoSimple('index', 'contact');
			$this->_flashMessenger->addMessage('MESSAGES_NOT_FOUND');
		}

		//Check if the directory is writable
		$dirwritable = $this->_helper->Directory->isWritable($id, 'contact', $this->_flashMessenger);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'contact', null, array('id' => $id));
		} elseif($this->isLocked($contact['locked'], $contact['lockedtime'])) {
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
			$contactDb->lock($id);

			$form = new Contacts_Form_Contact();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if($element == 'contactinfo') {
					$data['info'] = $data['contactinfo'];
					unset($data['contactinfo']);
					$element = 'info';
				}
				if($element == 'customerinfo') {
					$data['info'] = $data['customerinfo'];
					unset($data['customerinfo']);
					$element = 'info';
				}
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(array_key_exists('priceruleamount', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['priceruleamount'] = Zend_Locale_Format::getNumber($data['priceruleamount'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('cashdiscountpercent', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['cashdiscountpercent'] = Zend_Locale_Format::getNumber($data['cashdiscountpercent'],array('precision' => 2,'locale' => $locale));
					}
					$contactDb->updateContact($id, $data);
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$data = $contact;
					$currency = $this->_helper->Currency->getCurrency();
					if($data['priceruleamount'] == '0.0000') $data['priceruleamount'] = '';
					else $data['priceruleamount'] = $currency->toCurrency($data['priceruleamount']);
					if($data['cashdiscountdays'] == '0') $data['cashdiscountdays'] = '';
					if($data['cashdiscountpercent'] == '0.0000') $data['cashdiscountpercent'] = '';
					else $data['cashdiscountpercent'] = $currency->toCurrency($data['cashdiscountpercent']);
					$form->populate($data);

					//Phone
					$phoneDb = new Contacts_Model_DbTable_Phone();
					$phone = $phoneDb->getPhone($id);

					//Email
					$emailDb = new Contacts_Model_DbTable_Email();
					$email = $emailDb->getEmail($id);

					//Internet
					$internetDb = new Contacts_Model_DbTable_Internet();
					$internet = $internetDb->getInternet($id);

					//Bank account
					$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
					$bankAccount = $bankAccountDb->getBankaccount($id);

					//Addresses
					$addressDb = new Contacts_Model_DbTable_Address();
					$address = $addressDb->getAddress($id);

					//History
					$get = new Contacts_Model_Get();
					$history = $get->history($contact['contactid']);

					//Files
					$files = array();
					$path = BASE_PATH.'/files/contacts/';
					if(file_exists($path.$id) && is_dir($path.$id)) {
						if($handle = opendir($path.$id)) {
							$files['contactSpecific'] = array();
							while (false !== ($entry = readdir($handle))) {
								if(substr($entry, 0, strlen('.')) !== '.') array_push($files['contactSpecific'], $entry);
							}
							closedir($handle);
						}
					}

					//Toolbar
					$toolbar = new Contacts_Form_Toolbar();

					$this->view->form = $form;
					$this->view->options = $options;
					$this->view->dirwritable = $dirwritable;
					$this->view->history = $history;
					$this->view->files = $files;
					$this->view->address = $address;
					$this->view->phone = $phone;
					$this->view->email = $email;
					$this->view->internet = $internet;
					$this->view->bankAccount = $bankAccount;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$contact = new Contacts_Model_DbTable_Contact();
		$data = $contact->getContact($id);
		unset($data['id']);
		unset(
			$data['street'],
			$data['postcode'],
			$data['city'],
			$data['country'],
			$data['shippingname1'],
			$data['shippingname2'],
			$data['shippingdepartment'],
			$data['shippingstreet'],
			$data['shippingpostcode'],
			$data['shippingcity'],
			$data['shippingcountry'],
			$data['shippingphone']
		);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$incrementDb->setIncrement(($increment+1), 'contactid');

		$data['name1'] = $data['name1'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['contactid'] = $increment;

		echo $contactid = $contact->addContact($data);

		//Copy addresses
		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getAddress($id);
		foreach($addresses as $address) {
			$data = array(
				'contactid' => $contactid,
				'type' => $address['type'],
				'street' => $address['street'],
				'postcode' => $address['postcode'],
				'city' => $address['city'],
				'country' => $address['country'],
				'ordering' => $address['ordering']
			);
			$addressDb->addAddress($data);
		}

		//Phone
		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phones = $phoneDb->getPhone($id);
		foreach($phones as $phone) {
			$phoneDb->addPhone(array('contactid' => $contactid, 'type' => $phone['type'], 'phone' => $phone['phone'], 'ordering' => $phone['ordering']));
		}

		//Email
		$emailDb = new Contacts_Model_DbTable_Email();
		$emails = $emailDb->getEmail($id);
		foreach($emails as $email) {
			$emailDb->addEmail(array('contactid' => $contactid, 'email' => $email['email'], 'ordering' => $email['ordering']));
		}

		//Internet
		$internetDb = new Contacts_Model_DbTable_Internet();
		$internets = $internetDb->getInternet($id);
		foreach($internets as $internet) {
			$internetDb->addInternet(array('contactid' => $contactid, 'internet' => $internet['internet'], 'ordering' => $internet['ordering']));
		}

		//Check if the directory is writable
		$this->_helper->Directory->isWritable($contactid, 'contact', $this->_flashMessenger);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	protected function uploadAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();
		//$form->file->setDestination('/var/www/dewawi/files/');

		if($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if($form->isValid($formData)) {
				$contactid = $this->_getParam('contactid', 0);

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/contacts/'.$contactid.'/');
				try {
					// upload received file(s)
					$upload->receive();
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contactDb->deleteContact($id);

			$phoneDb = new Contacts_Model_DbTable_Phone();
			$phones = $phoneDb->getPhone($id);
			foreach($phones as $phone) {
				$phoneDb->deletePhone($phone['id']);
			}

			$emailDb = new Contacts_Model_DbTable_Email();
			$emails = $emailDb->getEmail($id);
			foreach($emails as $email) {
				$emailDb->deleteEmail($email['id']);
			}

			$internetDb = new Contacts_Model_DbTable_Internet();
			$internets = $internetDb->getInternet($id);
			foreach($internets as $internet) {
				$internetDb->deleteInternet($internet['id']);
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
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($id);
		if($this->isLocked($contact['locked'], $contact['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($contact['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$contactDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contactDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contactDb->lock($id);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();
		$options = $this->_helper->Options->getOptions($form);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	public function autocompleteAction()
	{
		$request = $this->getRequest();
		$order = $this->_getParam('order', null) ? $this->_getParam('order', 'id') : $request->getCookie('order', 'id');
		$sort = $this->_getParam('sort', null) ? $this->_getParam('sort', 'desc') : $request->getCookie('sort', 'desc');
		$keyword = $this->_getParam('keyword', null);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$keyword = trim($keyword);
		$columns = array('id', 'name1', 'name2');
		if($keyword) {
			$keywordArray = explode(" ", $keyword);
		}

		$query = "";
		foreach($columns as $column) {
			if($query) $query .= " OR ";
			$query .= "(";
			//Keyword
			if(isset($keywordArray)) {
				$query .= "(";
				$count = count($keywordArray);
				foreach($keywordArray as $key => $value) {
					$query .= $column." LIKE '%".$value."%'";
					if($count > ($key+1)) $query .= " AND ";
				}
				$query .= ") AND ";
			} elseif($keyword) {
				$query .= $column." LIKE '%".$keyword."%' AND ";
			}
			$query .= "clientid = ".$this->_client['id'].")";
		}

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		$contacts = $get->contacts($params, $options['categories']);

		header('Content-type: application/json');
		//$suggestions = array("suggestions" => array());
		//foreach($contacts as $contact) {
		//	array_push($suggestions["suggestions"], array("id" => $contact->id, "name1" => $contact->name1));
		//}
		//echo Zend_Json::encode($suggestions);
echo '{
		// Query is not required as of version 1.2.5
		query: "Unit",
		suggestions: [
			{ value: "United Arab Emirates", data: "AE" },
			{ value: "United Kingdom",	   data: "UK" },
			{ value: "United States",		data: "US" }
		]
	}';
	//print_r($suggestions);
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
