<?php

class Admin_PermissionController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$modules = $this->getPermissionModules();

		$list = $this->buildListView([
			'viewKey' => 'permissions',
			'list' => 'Admin_Model_List_Permissions',
			'entity' => Admin_Model_Entity_Permission::listConfig(),
		]);

		$list->configure([
			'context' => array_merge($list->getContext(), [
				'modules' => $modules,
			]),
		]);

		$this->view->modules = $modules;
	}

	protected function getPermissionModules(): array
	{
		return [
			'contacts',
			'items',
			'processes',
			'purchases',
			'sales',
			'statistics',
		];
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Permission();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$permissionDb = new Admin_Model_DbTable_Permission();
				$id = $permissionDb->addPermission($data);
				echo Zend_Json::encode($permissionDb->getPermission($id));
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

		$permissionDb = new Admin_Model_DbTable_Permission();
		$permission = $permissionDb->getPermission($id);

		//if($this->isLocked($permission['locked'], $permission['lockedtime'])) {
		if(false) {
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
			$permissionDb->lock($id);

			$form = new Admin_Form_Permission();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = $data['element'];
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$permissionDb = new Admin_Model_DbTable_Permission();
					$permissions = $permissionDb->getPermission($id);
					$permission = json_decode($permission[$data['module']], true);
					if($data[$element]) array_push($permission[$data['controller']], $element);
					else {
						$key = array_search($element, $permission[$data['controller']]);
						unset($permission[$data['controller']][$key]);
					}
					$permission = json_encode($permission);
					$permissionDb = new Admin_Model_DbTable_Permission();
					$permissionDb->updatePermission($id, array($data['module'] => $permission));
					echo Zend_Json::encode($permissionDb->getPermission($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
