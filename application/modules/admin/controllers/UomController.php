<?php

class Admin_UomController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'uoms',
			'list' => 'Admin_Model_List_Uoms',
			'entity' => Admin_Model_Entity_Uom::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('NEW_UOM'),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$uomDb = new Admin_Model_DbTable_Uom();
		$data = $uomDb->getUom($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$uomDb->addUom($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$uomDb = new Admin_Model_DbTable_Uom();
			$uomDb->deleteUom($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
