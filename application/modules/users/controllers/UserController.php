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
		if(Zend_Registry::isRegistered('User')) {
			$this->view->user = $this->_user = Zend_Registry::get('User');
			$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();
		}
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function editAction()
	{
		$id = $this->_user['id'];
		$request = $this->getRequest();
		$activeTab = $request->getCookie('tab', null);

		$userDb = new Users_Model_DbTable_User();
		$user = $userDb->getUser($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'user', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $user['locked'], $user['lockedtime']);

			$form = new Users_Form_User();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$userDb->updateUser($id, $data);
					echo Zend_Json::encode(array('saved' => true));
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$form->populate($user);
					$form->password->setValue('xxxxxxxxxx');
					$form->password->renderPassword = true;
					$form->smtppass->setValue('xxxxxxxxxx');
					$form->smtppass->renderPassword = true;

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

	public function passwordAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$id = $this->_user['id'];
		$request = $this->getRequest();
		$activeTab = $request->getCookie('tab', null);

		$userDb = new Users_Model_DbTable_User();
		$user = $userDb->getUser($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'user', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $user['locked'], $user['lockedtime']);

			$form = new Users_Form_Password();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				if(isset($data['passwordactual']) && $data['passwordactual']) {
					if(isset($data['passwordnew']) && $data['passwordnew']) {
						if(isset($data['passwordconfirm']) && $data['passwordconfirm']) {
							if($data['passwordnew'] == $data['passwordconfirm']) {
								if(md5($data['passwordactual']) == $user['password']) {
									$userDb->updateUser($id, array('password' => md5($data['passwordnew'])));
									echo Zend_Json::encode(array('saved' => true));
								} else {
									echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
								}
							} else {
								echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
							}
						} else {
							echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
						}
					} else {
						echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
					}
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
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

		$form = new Users_Form_Login();
		$form->submit->setLabel('USERS_LOGIN');
		$form->id->removeDecorator('Label');
		$this->view->form = $form;

		if($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if($form->isValid($formData)) {
				$username = $formData['username'];
				$password = $formData['password'];
				$stayLoggedIn = $formData['stayLoggedIn'];

				$authNamespace = new Zend_Session_Namespace('Zend_Auth');
				if($stayLoggedIn) $authNamespace->setExpirationSeconds(864000);
				else $authNamespace->setExpirationSeconds(3600);

				$db = Zend_Db_Table::getDefaultAdapter();
				$authAdapter = new Zend_Auth_Adapter_DbTable($db);

				$authAdapter->setTableName('user');
				if(strpos($username, '@')) $authAdapter->setIdentityColumn('email');
				else $authAdapter->setIdentityColumn('username');
				$authAdapter->setCredentialColumn('password');
				$authAdapter->setCredentialTreatment('MD5(?)');

				$authAdapter->setIdentity($username);
				$authAdapter->setCredential($password);

				//Select only active users
				//$authAdapter->getDbSelect()->where('deleted = 0');

				$result = $auth->authenticate($authAdapter);
				if($result->isValid()) {
					$storage = $auth->getStorage();
					$userInfo = $authAdapter->getResultRowObject(
									array(
										'id',
										'username',
										'name',
										'email',
										'admin',
										'clientid',
										'activated',
										'deleted'
									));

					if($userInfo->deleted) {
						$auth->clearIdentity();
						$this->_flashMessenger->addMessage('Benutzerkonto existiert nicht mehr.');
					} elseif($userInfo->activated) {
						//Store user info into session
						$storage->write($userInfo);

						//Store login time into database
						$userDb = new Users_Model_DbTable_User();
						$userDb->updateLoginTime($userInfo->id, $this->_date);

						//Redirect if url is defined
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
						$auth->clearIdentity();
						$this->_flashMessenger->addMessage('Benutzerkonto ist nicht aktiviert.');
					}
				} else {
					$auth->clearIdentity();
					$this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
				}
			} else {
				$auth->clearIdentity();
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
		// Clear session data
		Zend_Auth::getInstance()->clearIdentity();

		// Remove expiration time from session
		unset($_SESSION['__ZF']['Zend_Auth']['ENT']);

		// Redirect to start
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
