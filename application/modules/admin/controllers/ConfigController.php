<?php

class Admin_ConfigController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->_helper->getHelper('layout')->disableLayout();
		}

		$form = new Admin_Form_Config();
		$toolbar = new Admin_Form_Toolbar();

		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigs();

		$this->view->form = $form;
		$this->view->config = $config;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function editAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfig($id);

		if ($this->isLocked($config['locked'], $config['lockedtime'])) {
			echo Zend_Json::encode([
				'message' => $this->view->translate('MESSAGES_LOCKED'),
			]);
			return;
		}

		$configDb->lock($id);

		$form = new Admin_Form_Config();

		if ($request->isPost()) {
			$data = $request->getPost();
			$element = key($data);

			if (isset($form->$element) && $form->isValidPartial($data)) {
				$configDb->updateConfig($id, $data);
				echo Zend_Json::encode($configDb->getConfig($id));
				return;
			}

			echo Zend_Json::encode([
				'message' => $this->view->translate('MESSAGES_FORM_IS_INVALID'),
			]);
		}
	}
}
