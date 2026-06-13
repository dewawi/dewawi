<?php

class Admin_ShippingmethodController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'shippingmethods',
			'list' => 'Admin_Model_List_Shippingmethods',
			'entity' => Admin_Model_Entity_Shippingmethod::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Shippingmethod();

		return [
			'title' => $this->view->translate('NEW_SHIPPING_METHOD'),
			'ordering' => $db->getNextOrdering(),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$shippingmethodDb = new Admin_Model_DbTable_Shippingmethod();
		$data = $shippingmethodDb->getShippingmethod($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$shippingmethodDb->addShippingmethod($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$shippingmethodDb = new Admin_Model_DbTable_Shippingmethod();
			$shippingmethodDb->deleteShippingmethod($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
