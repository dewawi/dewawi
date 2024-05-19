<?php

class Admin_UserController extends Zend_Controller_Action
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

		$form = new Admin_Form_User();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$usersDb = new Admin_Model_DbTable_User();
		$users = $usersDb->getUsers();

		$forms = array();
		foreach($users as $user) {
			$forms[$user->id] = new Admin_Form_User();
			$forms[$user->id]->activated->setValue($user->activated);
		}

		$this->view->form = $form;
		$this->view->forms = $forms;
		$this->view->users = $users;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_User();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$usersDb = new Admin_Model_DbTable_User();
		$users = $usersDb->getUsers();

		$forms = array();
		foreach($users as $user) {
			$forms[$user->id] = new Admin_Form_User();
			$forms[$user->id]->activated->setValue($user->activated);
		}

		$this->view->form = $form;
		$this->view->forms = $forms;
		$this->view->users = $users;
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
			$form = new Admin_Form_User();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$userDb = new Admin_Model_DbTable_User();
				// Use password_hash instead of md5
				$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
				$id = $userDb->addUser($data);

				// Add permissions row
				$permissionDb = new Admin_Model_DbTable_Permission();
				$permissions = [
					'default' => [
						"index" => ["view"],
						"comment" => ["add", "edit", "view", "delete"]
					],
					'contacts' => [
						"contact" => ["add", "edit", "view", "delete"],
						"email" => ["add", "edit", "view", "delete"]
					],
					'items' => [
						"item" => ["add", "edit", "view", "delete"],
						"inventory" => ["add", "edit", "view", "delete"],
						"pricerule" => ["add", "edit", "view", "delete"]
					],
					'processes' => [
						"process" => ["add", "edit", "view", "delete"]
					],
					'purchases' => [
						"quoterequest" => ["add", "edit", "view", "delete"],
						"purchaseorder" => ["add", "edit", "view", "delete"]
					],
					'sales' => [
						"quote" => ["add", "edit", "view", "delete"],
						"salesorder" => ["add", "edit", "view", "delete"],
						"deliveryorder" => ["add", "edit", "view", "delete"],
						"invoice" => ["add", "edit", "view", "delete"],
						"creditnote" => ["add", "edit", "view", "delete"],
						"reminder" => ["add", "edit", "view", "delete"]
					],
					'statistics' => [
						"turnover" => ["view"],
						"customer" => ["view"],
						"quote" => ["view"]
					]
				];
				$permissionsJson = [];
				foreach ($permissions as $key => $value) {
					$permissionsJson[$key] = json_encode($value);
				}
				$permissionsJson['userid'] = $id;
				$permissionDb->addPermission($permissionsJson);

				echo Zend_Json::encode($userDb->getUser($id));
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

		$userDb = new Admin_Model_DbTable_User();
		$user = $userDb->getUser($id);

		if($this->isLocked($user['locked'], $user['lockedtime'])) {
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

			$form = new Admin_Form_User();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(isset($data['password']) && $data['password']) {
						$data['password'] = md5($data['password']);
					}
					$userDb = new Admin_Model_DbTable_User();
					$userDb->updateUser($id, $data);
					$response = $userDb->getUser($id);
					$response['password'] = '******';
					echo Zend_Json::encode($response);
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
		$userDb = new Admin_Model_DbTable_User();
		$data = $userDb->getUser($id);
		unset($data['id']);
		$data['username'] = $data['username'].'2';
		$data['email'] = $data['email'].'2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$userid = $userDb->addUser($data);

		//Copy user permissions
		$permissionDb = new Admin_Model_DbTable_Permission();
		$permissions = $permissionDb->getPermissionByUserID($id);
		unset($permissions['id']);
		$permissions['userid'] = $userid;
		$permissions['modified'] = NULL;
		$permissions['modifiedby'] = 0;
		$permissions['locked'] = 0;
		$permissions['lockedtime'] = NULL;
		$permissionDb->addPermission($permissions);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			if($id == $this->_user['id']) {
				$this->_flashMessenger->addMessage('MESSAGES_OWN_USER_CAN_NOT_BE_DELETED');
			} else {
				$userDb = new Admin_Model_DbTable_User();
				$userDb->deleteUser($id);
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
		$userDb = new Admin_Model_DbTable_User();
		$user = $userDb->getUser($id);
		if($this->isLocked($user['locked'], $user['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($user['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$userDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$userDb = new Admin_Model_DbTable_User();
		$userDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$userDb = new Admin_Model_DbTable_User();
		$userDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_User();

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
