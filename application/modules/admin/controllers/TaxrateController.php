<?php

class Admin_TaxrateController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'taxrates',
			'list' => 'Admin_Model_List_Taxrates',
			'entity' => Admin_Model_Entity_Taxrate::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Taxrate();

		return [
			'name' => $this->view->translate('NEW_TAX_RATE'),
			'rate' => 0,
			'ordering' => $db->getNextOrdering(),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$taxrateDb = new Admin_Model_DbTable_Taxrate();
		$data = $taxrateDb->getTaxrate($id);
		unset($data['id']);
		$data['name'] = $data['name'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$taxrateDb->addTaxrate($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$taxrateDb = new Admin_Model_DbTable_Taxrate();
			$taxrateDb->deleteTaxrate($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
