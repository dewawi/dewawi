<?php

class Admin_CurrencyController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'currencies',
			'list' => 'Admin_Model_List_Currencies',
			'entity' => Admin_Model_Entity_Currency::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Currency();

		return [
			'code' => '---',
			'name' => $this->view->translate('NEW_CURRENCY'),
			'symbol' => '',
			'ordering' => $db->getNextOrdering(),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$currencyDb = new Admin_Model_DbTable_Currency();
		$data = $currencyDb->getCurrency($id);
		unset($data['id']);
		$data['name'] = $data['name'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$currencyDb->addCurrency($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$currencyDb = new Admin_Model_DbTable_Currency();
			$currencyDb->deleteCurrency($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
