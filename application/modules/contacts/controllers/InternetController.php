<?php

class Contacts_InternetController extends Zend_Controller_Action
{
	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$internetDb = new Contacts_Model_DbTable_Internet();
				$latest = end($internetDb->getInternet($data['contactid']));
				$internetDb->addInternet($data['contactid'], '', $latest['ordering']+1);
				$internet = end($internetDb->getInternet($data['contactid']));
				echo $this->view->MultiForm('internet', $internet);
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$internetDb = new Contacts_Model_DbTable_Internet();
				if($id > 0) {
					$internetDb->updateInternet($id, $data['internet'], $data['ordering']);
				} else {
					$internetDb->addInternet($data['contactid'], $data['internet'], $data['ordering']);
				}
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$internetDb = new Contacts_Model_DbTable_Internet();
			$internetDb->deleteInternet($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
