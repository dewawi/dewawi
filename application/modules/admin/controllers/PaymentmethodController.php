<?php

class Admin_PaymentmethodController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'paymentmethods',
			'list' => 'Admin_Model_List_Paymentmethods',
			'entity' => Admin_Model_Entity_Paymentmethod::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Paymentmethod();

		return [
			'title' => $this->view->translate('NEW_PAYMENT_METHOD'),
			'ordering' => $db->getNextOrdering(),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
		$data = $paymentmethodDb->getPaymentmethod($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$paymentmethodDb->addPaymentmethod($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
			$paymentmethodDb->deletePaymentmethod($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
