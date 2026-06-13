<?php

class Admin_CountryController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'countries',
			'list' => 'Admin_Model_List_Countries',
			'entity' => Admin_Model_Entity_Country::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'code' => '--',
			'name' => $this->view->translate('NEW_COUNTRY'),
			'language' => (string) Zend_Registry::get('Zend_Locale'),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$countryDb = new Admin_Model_DbTable_Country();
		$data = $countryDb->getCountry($id);
		unset($data['id']);
		$data['code'] = 'XX';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$countryDb->addCountry($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$countryDb = new Admin_Model_DbTable_Country();
			$countryDb->deleteCountry($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
