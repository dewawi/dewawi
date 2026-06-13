<?php

class Admin_DeliverytimeController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'deliverytimes',
			'list' => 'Admin_Model_List_Deliverytimes',
			'entity' => Admin_Model_Entity_Deliverytime::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Deliverytime();

		return [
			'title' => $this->view->translate('NEW_DELIVERY_TIME'),
			'ordering' => $db->getNextOrdering(),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$deliverytimeDb = new Admin_Model_DbTable_Deliverytime();
		$data = $deliverytimeDb->getDeliverytime($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$deliverytimeDb->addDeliverytime($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$deliverytimeDb = new Admin_Model_DbTable_Deliverytime();
			$deliverytimeDb->deleteDeliverytime($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
