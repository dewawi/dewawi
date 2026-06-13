<?php

class Admin_ModuleController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'modules',
			'list' => 'Admin_Model_List_Modules',
			'entity' => Admin_Model_Entity_Module::listConfig(),
		]);
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$moduleDb = new Admin_Model_DbTable_Module();
		$data = $moduleDb->getModule($id);
		unset($data['id']);
		$data['name'] = $data['name'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$moduleDb->addModule($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$moduleDb = new Admin_Model_DbTable_Module();
			$moduleDb->deleteModule($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
