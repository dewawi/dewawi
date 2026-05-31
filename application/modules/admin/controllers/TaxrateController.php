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

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Taxrate();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$taxrateDb = new Admin_Model_DbTable_Taxrate();
				$id = $taxrateDb->addTaxrate($data);
				echo Zend_Json::encode($taxrateDb->getTaxrate($id));
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}
	}

	public function editAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$taxrateDb = new Admin_Model_DbTable_Taxrate();
		$taxrate = $taxrateDb->getTaxrate($id);

		if($this->isLocked($taxrate['locked'], $taxrate['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$taxrateDb->lock($id);

			$form = new Admin_Form_Taxrate();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$taxrateDb = new Admin_Model_DbTable_Taxrate();
					$taxrateDb->updateTaxrate($id, $data);
					echo Zend_Json::encode($taxrateDb->getTaxrate($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
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
