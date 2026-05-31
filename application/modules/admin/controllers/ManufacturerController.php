<?php

class Admin_ManufacturerController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'manufacturers',
			'list' => 'Admin_Model_List_Manufacturers',
			'entity' => Admin_Model_Entity_Manufacturer::listConfig(),
		]);
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Manufacturer();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$manufacturerDb = new Admin_Model_DbTable_Manufacturer();
				$id = $manufacturerDb->addManufacturer($data);
				echo Zend_Json::encode($manufacturerDb->getManufacturer($id));
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

		$manufacturerDb = new Admin_Model_DbTable_Manufacturer();
		$manufacturer = $manufacturerDb->getManufacturer($id);

		if($this->isLocked($manufacturer['locked'], $manufacturer['lockedtime'])) {
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
			$manufacturerDb->lock($id);

			$form = new Admin_Form_Manufacturer();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$manufacturerDb = new Admin_Model_DbTable_Manufacturer();
					$manufacturerDb->updateManufacturer($id, $data);
					echo Zend_Json::encode($manufacturerDb->getManufacturer($id));
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
		$manufacturerDb = new Admin_Model_DbTable_Manufacturer();
		$data = $manufacturerDb->getManufacturer($id);
		unset($data['id']);
		$data['name'] = $data['name'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$manufacturerDb->addManufacturer($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$manufacturerDb = new Admin_Model_DbTable_Manufacturer();
			$manufacturerDb->deleteManufacturer($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
