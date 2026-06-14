<?php

class Admin_UserController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'users',
			'list' => 'Admin_Model_List_Users',
			'entity' => Admin_Model_Entity_User::listConfig(),
		]);
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
						"ledger" => ["add", "edit", "view", "delete"],
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

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$permissionDb = new Admin_Model_DbTable_Permission();
		$permissions = $permissionDb->getPermissionByUserID($oldId);

		unset($permissions['id']);

		$permissions['userid'] = $newId;
		$permissions['modified'] = null;
		$permissions['modifiedby'] = 0;
		$permissions['locked'] = 0;
		$permissions['lockedtime'] = null;

		$permissionDb->addPermission($permissions);
	}

	protected function canDeleteRow(array $row): bool
	{
		if ((int)$row['id'] === (int)$this->_user['id']) {
			$this->_flashMessenger->addMessage('MESSAGES_OWN_USER_CAN_NOT_BE_DELETED');
			return false;
		}

		return true;
	}
}
