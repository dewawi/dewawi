<?php

class Users_UserController extends Zend_Controller_Action
{

	public function init()
	{
		/* Initialize action controller here */
	}

	public function loginAction()
	{
		$auth = Zend_Registry::get('Zend_Auth');
		if($auth->hasIdentity()) $this->_helper->redirector->gotoSimple('index', 'index', 'index');

		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Users_Form_User();
		$form->submit->setLabel('USERS_LOGIN');
		$form->id->removeDecorator('Label');
		$this->view->form = $form;

		//Clients
		$clientsDb = new Application_Model_DbTable_Client();
		$clients = $clientsDb->fetchAll();
		foreach($clients as $client) {
			$form->client->addMultiOption($client->id, $client->company);
		}

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$username = $formData['username'];
				$password = $formData['password'];
				$client = $formData['client'];
				$stayLoggedIn = $formData['stayLoggedIn'];

				$authNamespace = new Zend_Session_Namespace('Zend_Auth');
				$authNamespace->user = $username;
				if($stayLoggedIn) $authNamespace->setExpirationSeconds(864000);
				else $authNamespace->setExpirationSeconds(3600);

				$db = Zend_Db_Table::getDefaultAdapter();
				$authAdapter = new Zend_Auth_Adapter_DbTable($db);

				$authAdapter->setTableName('user');
				$authAdapter->setIdentityColumn('username');
				$authAdapter->setCredentialColumn('password');
				$authAdapter->setCredentialTreatment('MD5(?)');

				$authAdapter->setIdentity($username);
				$authAdapter->setCredential($password);

				$auth = Zend_Auth::getInstance();
				$result = $auth->authenticate($authAdapter);

				if ($result->isValid()) {
					$storage = $auth->getStorage();
					$userInfo = $authAdapter->getResultRowObject(array('id', 'username', 'name', 'email'));
					$userInfo->clientid = $client;
					$storage->write($userInfo); //Store into session

					if($this->_getParam('url', null)) {
						$url = explode("|", $this->_getParam('url', null));
						if(isset($url[3]) && $url[3]) {
							$this->_helper->redirector->gotoSimple($url[2], $url[1], $url[0], array('id' => $url[3]));
						} else {
							$this->_helper->redirector->gotoSimple($url[2], $url[1], $url[0]);
						}
					}
					$this->_helper->redirector->gotoSimple("index", "index");
				} else {
					echo "error";
				}
			} else {
				$form->populate($formData);
			}
		}
	}

	public function logoutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		$this->_helper->redirector->gotoSimple('index', 'index', 'index');
	}
}
