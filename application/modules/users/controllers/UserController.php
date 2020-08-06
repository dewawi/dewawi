<?php

class Users_UserController extends Zend_Controller_Action
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

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$userDb = new Users_Model_DbTable_User();
		$user = $userDb->getUser($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'user', null, array('id' => $id));
		} elseif($this->isLocked($user['locked'], $user['lockedtime'])) {
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
			$userDb->lock($id);

			$form = new Users_Form_User();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$userDb->updateUser($id, $data);
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$form->populate($user);

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
				}
			}
		}
        $this->view->messages = array_merge(
            $this->_helper->flashMessenger->getMessages(),
            $this->_helper->flashMessenger->getCurrentMessages()
        );
        $this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function loginAction()
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) $this->_helper->redirector->gotoSimple('index', 'index', 'index');

		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Users_Form_User();
		$form->submit->setLabel('USERS_LOGIN');
		$form->id->removeDecorator('Label');
		$this->view->form = $form;

		//Clients
		//$clientsDb = new Application_Model_DbTable_Client();
		//$clients = $clientsDb->getClients();
		//foreach($clients as $id => $company) {
		//	$form->client->addMultiOption($id, $company);
		//}

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$username = $formData['username'];
				$password = $formData['password'];
				$stayLoggedIn = $formData['stayLoggedIn'];

				$authNamespace = new Zend_Session_Namespace('Zend_Auth');
				//$authNamespace->user = $username;
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

				$result = $auth->authenticate($authAdapter);

				if ($result->isValid()) {
					$storage = $auth->getStorage();
					$userInfo = $authAdapter->getResultRowObject(
                                    array(
                                        'id',
                                        'username',
                                        'name',
                                        'email',
                                        'admin',
                                        'permissions',
                                        'clientid'
                                    ));
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
				    $this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
				}
			} else {
				$this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
				$form->populate($formData);
			}
		}
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function logoutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		$this->_helper->redirector->gotoSimple('index', 'index', 'index');
	}

	public function clientAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		$clientid = $this->_getParam('clientid', 0);
        if($clientid) {
		    $authNamespace = new Zend_Session_Namespace('Zend_Auth');
		    $authNamespace->storage->clientid = $clientid;
        }
	}

	public function languageAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		$language = $this->_getParam('language', 0);
        if($language) {
		    $authNamespace = new Zend_Session_Namespace('Zend_Auth');
		    $authNamespace->storage->language = $language;
        }
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getProcess($id);
		if($this->isLocked($item['locked'], $item['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($item['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$itemDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$itemDb = new Items_Model_DbTable_Item();
		$itemDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$itemDb = new Items_Model_DbTable_Item();
		$itemDb->lock($id);
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
