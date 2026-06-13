<?php

class Admin_ShopController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'shops',
			'list' => 'Admin_Model_List_Shops',
			'entity' => Admin_Model_Entity_Shop::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('NEW_SHOP'),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$shopDb = new Admin_Model_DbTable_Shop();
		$data = $shopDb->getShop($id);
		unset($data['id']);
		$data['company'] = $data['company'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$shopid = $shopDb->addShop($data);

		//Copy config row
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByShopID($id);
		unset($config['id']);
		$config['shopid'] = $shopid;
		$config['modified'] = NULL;
		$config['modifiedby'] = 0;
		$config['locked'] = 0;
		$config['lockedtime'] = NULL;
		$configDb->addConfig($config);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			if($id == $this->_user['shopid']) {
				$this->_flashMessenger->addMessage('MESSAGES_OWN_CLINET_CAN_NOT_BE_DELETED');
			} else {
				$shopDb = new Admin_Model_DbTable_Shop();
				$shopDb->deleteShop($id);
				$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
			}
		}
	}
}
