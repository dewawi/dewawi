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

				$data = (array)$request->getPost();
				$element = (string)key($data);

				if (isset($data['password']) && $data['password'] === 'xxxxxxxxxx') {
					unset($data['password']);
				}

				if (isset($data['smtppass']) && $data['smtppass'] === 'xxxxxxxxxx') {
					unset($data['smtppass']);
				}

				if ($form->getElement($element) && $form->isValidPartial($data)) {
					$userDb->updateUser($id, $data);
					echo Zend_Json::encode(['saved' => true]);
				} else {
					echo Zend_Json::encode([
						'saved' => false,
						'message' => 'Form is invalid',
						'errors' => $form->getErrors(),
					]);
				}
				return;
			} else {
				if($id > 0) {
					$form->setValues($user);
					$form->setValue('password', 'xxxxxxxxxx');
					$form->setValue('smtppass', 'xxxxxxxxxx');

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
								if(password_verify($data['passwordactual'], $user['password'])) {
									$userDb->updateUser($id, array('password' => password_hash($data['passwordnew'], PASSWORD_DEFAULT)));
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
		$this->view->form = $form;

		if($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if($form->isValid($formData)) {
				$username = $formData['username'];
				$password = $formData['password'];
				$stayLoggedIn = !empty($formData['stayLoggedIn']) ? 1 : 0;

				$userDb = new Users_Model_DbTable_User();
				try {
					if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
						$user = $userDb->getUserByEmail($username);
					} else {
						$user = $userDb->getUserByUsername($username);
					}
				} catch (Exception $e) {
					$user = null;
				}

				if($user) {
					if($user->deleted) {
						$auth->clearIdentity();
						$this->_flashMessenger->addMessage('Benutzerkonto existiert nicht mehr.');
					} else {
						if($user->activated) {
							if(password_verify($password, $user->password)) {
								$rehash = password_needs_rehash($user->password, PASSWORD_DEFAULT);
								$login = true;
							} elseif($user->password == md5($password)) {
								$rehash = true;
								$login = true;
							} else {
								$rehash = false;
								$login = false;
								$auth->clearIdentity();
								$this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
							}

							if($rehash) {
								$userDb->updateUser($user->id, array('password' => password_hash($password, PASSWORD_DEFAULT)), $user->id);
							}

							if($login) {
								$storage = $auth->getStorage();
								$storage->write($user);

								DEEC_Session::login(
									(bool)$stayLoggedIn
								);

								$userDb->updateLoginTime($user->id);

								$target = $this->_getParam('url', null);

								$userTrackingDb = new Users_Model_DbTable_Usertracking();
								$userTrackingDb->addUsertracking($user, $target);

								if($target) {
									$url = explode('|', $target);

									if(isset($url[3]) && $url[3]) {
										$this->_helper->redirector->gotoSimple($url[2], $url[1], $url[0], ['id' => $url[3]]);
									} else {
										$this->_helper->redirector->gotoSimple($url[2], $url[1], $url[0]);
									}
								}

								$this->_helper->redirector->gotoSimple('index', 'index', 'index');
							}
						} else {
							$auth->clearIdentity();
							$this->_flashMessenger->addMessage('Benutzerkonto ist nicht aktiviert.');
						}
					}
				} else {
					$auth->clearIdentity();
					$this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
					$form->setValues($formData);
				}
			} else {
				$auth->clearIdentity();
				$this->_flashMessenger->addMessage('Benutzername und Passwort stimmen nicht überein.');
				$form->setValues($formData);
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
		DEEC_Session::logout();

		Zend_Auth::getInstance()->clearIdentity();

		$this->_helper->redirector->gotoSimple('index', 'index', 'index');
	}

	public function sessionAction(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$this->getResponse()
			->setHttpResponseCode(401)
			->setHeader(
				'Content-Type',
				'application/json; charset=UTF-8',
				true
			)
			->setBody(
				Zend_Json::encode([
					'ok' => false,
					'message' => 'session_expired',
				])
			);
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
