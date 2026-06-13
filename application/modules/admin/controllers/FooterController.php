<?php

class Admin_FooterController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'footers',
			'list' => 'Admin_Model_List_Footers',
			'entity' => Admin_Model_Entity_Footer::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'templateid' => 0,
			'column' => 0,
			'text' => '',
			'width' => 0,
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$footerDb = new Admin_Model_DbTable_Footer();
		$data = $footerDb->getFooter($id);
		unset($data['id']);
		$data['column'] = $data['column'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$footerDb->addFooter($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$footerDb = new Admin_Model_DbTable_Footer();
			$footerDb->deleteFooter($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
