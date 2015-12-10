<?php

class Contacts_AddressController extends Zend_Controller_Action
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
				$addressDb = new Contacts_Model_DbTable_Address();
				$latest = end($addressDb->getAddress($data['contactid']));
				$addressDb->addAddress($data['contactid'], $data['type'], '', $latest['ordering']+1);
				$address = end($addressDb->getAddress($data['contactid']));
				echo $this->view->MultiForm('address', $address);
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
				$addressDb = new Contacts_Model_DbTable_Address();
				if($id > 0) {
					$addressDb->updateAddress($id, $data);
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
			$addressDb = new Contacts_Model_DbTable_Address();
			$addressDb->deleteAddress($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
