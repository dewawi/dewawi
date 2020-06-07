<?php

class Contacts_EmailController extends Zend_Controller_Action
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
				$emailDb = new Contacts_Model_DbTable_Email();
				$emailDataBefore = $emailDb->getEmail($data['contactid']);
				$latest = end($emailDataBefore);
				$emailDb->addEmail(array('contactid' => $data['contactid'], 'ordering' => $latest['ordering']+1));
				$emailDataAfter = $emailDb->getEmail($data['contactid']);
				$email = end($emailDataAfter);
				echo $this->view->MultiForm('email', $email);
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
				$emailDb = new Contacts_Model_DbTable_Email();
				if($id > 0) {
					$emailDb->updateEmail($id, $data);
				} else {
					$emailDb->addEmail($data['contactid'], $data['email'], $data['ordering']);
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
			$emailDb = new Contacts_Model_DbTable_Email();
			$emailDb->deleteEmail($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
