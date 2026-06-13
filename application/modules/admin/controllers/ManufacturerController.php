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

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Manufacturer();

		return [
			'name' => $this->view->translate('NEW_MANUFACTURER'),
			'ordering' => $db->getNextOrdering(),
		];
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
