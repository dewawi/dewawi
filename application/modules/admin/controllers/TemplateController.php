<?php

class Admin_TemplateController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'templates',
			'list' => 'Admin_Model_List_Templates',
			'entity' => Admin_Model_Entity_Template::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'description' => $this->view->translate('NEW_TEMPLATE'),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$templateDb = new Admin_Model_DbTable_Template();
		$data = $templateDb->getTemplate($id);
		unset($data['id']);
		$data['description'] = $data['description'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$templateDb->addTemplate($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$templateDb = new Admin_Model_DbTable_Template();
			$templateDb->deleteTemplate($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
