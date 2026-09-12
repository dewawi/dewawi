<?php

class Admin_ConfigController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByClientID((int)$this->view->client['id']);

		return $this->_helper->redirector->gotoSimple('edit', 'config', 'admin', [
			'id' => (int)$config['id'],
		]);
	}

	protected function getEditToolbar()
	{
		return null;
	}
}
