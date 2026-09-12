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

	protected function beforeEditSave(array $values, array $row): array
	{
		if (array_key_exists('smtppass', $values) && $values['smtppass'] === null) {
			unset($values['smtppass']);
		}

		return $values;
	}
}
