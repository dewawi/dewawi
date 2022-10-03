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
				$internetDataBefore = $internetDb->getInternet($data['contactid']);
				$latest = end($internetDataBefore);
				$internetDb->addInternet(array('contactid' => $data['contactid'], 'ordering' => $latest['ordering']+1));
				$internetDataAfter = $internetDb->getInternet($data['contactid']);
				$internet = end($internetDataAfter);
				echo $this->view->MultiForm('contacts', 'internet', $internet);
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
					$internetDb->updateInternet($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
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
