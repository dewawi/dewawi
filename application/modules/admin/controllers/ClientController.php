<?php

class Admin_ClientController extends Zend_Controller_Action
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
		$form = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);
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

		$form = new Admin_Form_Client();
		$toolbar = new Admin_Form_Toolbar();

		$clientsDb = new Admin_Model_DbTable_Client();
		$clients = $clientsDb->getClients();

		$forms = array();
		foreach($clients as $client) {
			$forms[$client->id] = new Admin_Form_Client();
			$forms[$client->id]->activated->setValue($client->activated);
		}

		$this->view->form = $form;
		$this->view->forms = $forms;
		$this->view->clients = $clients;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Client();
		$toolbar = new Admin_Form_Toolbar();

		$clientsDb = new Admin_Model_DbTable_Client();
		$clients = $clientsDb->getClients();

		$forms = array();
		foreach($clients as $client) {
			$forms[$client->id] = new Admin_Form_Client();
			$forms[$client->id]->activated->setValue($client->activated);
		}

		$this->view->form = $form;
		$this->view->forms = $forms;
		$this->view->clients = $clients;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Client();
			$data = $request->getPost();
			if($form->isValid($data)) {
				$clientDb = new Admin_Model_DbTable_Client();
				$id = $clientDb->addClient($data);

				//Add category row
				$categoryDb = new Admin_Model_DbTable_Category();
				$categoryDb->addCategory(array(
									'title' => 'Privatkunden',
									'type' => 'contact',
									'ordering' => 1,
									'parentid' => 0
									), $id);
				$categoryDb->addCategory(array(
									'title' => 'Geschäftskunden',
									'type' => 'contact',
									'ordering' => 2,
									'parentid' => 0
									), $id);
				$categoryDb->addCategory(array(
									'title' => 'Lieferanten',
									'type' => 'contact',
									'ordering' => 3,
									'parentid' => 0
									), $id);

				//Add config row
				$configDb = new Admin_Model_DbTable_Config();
				$configDb->addConfig(array(
									'timezone' => 'Europe/Berlin',
									'language' => 'de_DE'
									), $id);

				//Add currency row
				$currencyDb = new Admin_Model_DbTable_Currency();
				$currencyDb->addCurrency(array(
									'code' => 'EUR',
									'name' => 'Euro',
									'symbol' => '€'
									), $id);
				$currencyDb->addCurrency(array(
									'code' => 'USD',
									'name' => 'US Dollar',
									'symbol' => '$'
									), $id);

				//Add exchange rate row
				//$currencyDb = new Admin_Model_DbTable_Currency();
				//$currencyDb->addCurrency(array(
				//					'code' => 'EUR',
				//					'name' => 'Euro',
				//					'symbol' => '€',
				//					'clientid' => $id
				//					));
				//$currencyDb->addCurrency(array(
				//					'code' => 'USD',
				//					'name' => 'US Dollar',
				//					'symbol' => '$',
				//					'clientid' => $id
				//					));

				//Add file name row
				$filenameDb = new Admin_Model_DbTable_Filename();
				$filenameDb->addFilename(array(
									'creditnote' => 'Gutschrift-%NUMBER%.pdf',
									'deliveryorder' => 'Lieferschein-%NUMBER%.pdf',
									'invoice' => 'Rechnung-%NUMBER%.pdf',
									'purchaseorder' => 'Bestellung-%NUMBER%.pdf',
									'quote' => 'Angebot-%NUMBER%.pdf',
									'quoterequest' => 'Anfrage-%NUMBER%.pdf',
									'reminder' => 'Mahnung-%NUMBER%.pdf',
									'salesorder' => 'Auftragsbestaetigung-%NUMBER%.pdf'
									), $id);

				//Add increment row
				$incrementDb = new Admin_Model_DbTable_Increment();
				$incrementDb->addIncrement(array(
									'clientid' => $id,
									'contactid' => 10000,
									'creditnoteid' => 10000,
									'deliveryorderid' => 10000,
									'invoiceid' => 10000,
									'purchaseorderid' => 10000,
									'quoteid' => 10000,
									'quoterequestid' => 10000,
									'reminderid' => 10000,
									'salesorderid' => 10000
									), $id);

				//Add language row
				//To do: get loaded languages
				$languageDb = new Admin_Model_DbTable_Language();
				$languageDb->addLanguage(array(
									'code' => 'de_DE',
									'name' => 'Deutsch'
									), $id);
				$languageDb->addLanguage(array(
									'code' => 'en_US',
									'name' => 'Englisch'
									), $id);

				//Add payment method row
				//$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
				//$paymentmethodDb->addPaymentmethod(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$paymentmethodDb->addPaymentmethod(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add shipping method row
				//$shippingmethodDb = new Admin_Model_DbTable_Shippingmethod();
				//$shippingmethodDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$shippingmethodDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add state row
				//$stateDb = new Admin_Model_DbTable_State();
				//$stateDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$stateDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add taxrate row
				$taxrateDb = new Admin_Model_DbTable_Taxrate();
				$taxrateDb->addTaxrate(array(
									'name' => 'MwSt (16%)',
									'rate' => 16.0000
									), $id);
				$taxrateDb = new Admin_Model_DbTable_Taxrate();
				$taxrateDb->addTaxrate(array(
									'name' => 'MwSt (5%)',
									'rate' => 5.0000
									), $id);

				//Add template row
				$templateDb = new Admin_Model_DbTable_Template();
				$templateDb->addTemplate(array(
									'description' => 'Vorlage',
									'default' => 1
									), $id);

				//Add textblock row
				$textblockDb = new Admin_Model_DbTable_Textblock();
				$textblockDb->addTextblock($textblockDb->getTextblock(1), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(2), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(3), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(4), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(5), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(6), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(7), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(8), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(9), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(10), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(11), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(12), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(13), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(14), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(15), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(16), $id);

				//Add uom row
				$uomDb = new Admin_Model_DbTable_Uom();
				$uomDb->addUom(array(
									'title' => 'Stück'
									), $id);
				$uomDb->addUom(array(
									'title' => 'Pack.'
									), $id);
				$uomDb->addUom(array(
									'title' => 'Std.'
									), $id);
				$uomDb->addUom(array(
									'title' => 'kg'
									), $id);
				$uomDb->addUom(array(
									'title' => 'm'
									), $id);

				//Add warehouse row
				$warehouseDb = new Admin_Model_DbTable_Warehouse();
				$warehouseDb->addWarehouse(array(
									'title' => 'Hauptlager',
									'description' => 'Hauptlager'
									), $id);

				echo Zend_Json::encode($clientDb->getClient($id));
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}
	}

	public function editAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$clientDb = new Admin_Model_DbTable_Client();
		$client = $clientDb->getClient($id);

		if($this->isLocked($client['locked'], $client['lockedtime'])) {
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
			$clientDb->lock($id);

			$form = new Admin_Form_Client();
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$clientDb = new Admin_Model_DbTable_Client();
					$clientDb->updateClient($id, $data);
					echo Zend_Json::encode($clientDb->getClient($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$clientDb = new Admin_Model_DbTable_Client();
		$data = $clientDb->getClient($id);
		unset($data['id']);
		$data['company'] = $data['company'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$clientid = $clientDb->addClient($data);

		//Copy config row
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByClientID($id);
		unset($config['id']);
		$config['clientid'] = $clientid;
		$config['modified'] = NULL;
		$config['modifiedby'] = 0;
		$config['locked'] = 0;
		$config['lockedtime'] = NULL;
		$configDb->addConfig($config);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			if($id == $this->_user['clientid']) {
				$this->_flashMessenger->addMessage('MESSAGES_OWN_CLINET_CAN_NOT_BE_DELETED');
			} else {
				$clientDb = new Admin_Model_DbTable_Client();
				$clientDb->deleteClient($id);
				$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
			}
		}
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$clientDb = new Admin_Model_DbTable_Client();
		$client = $clientDb->getClient($id);
		if($this->isLocked($client['locked'], $client['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($client['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$clientDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$clientDb = new Admin_Model_DbTable_Client();
		$clientDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$clientDb = new Admin_Model_DbTable_Client();
		$clientDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Client();

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
