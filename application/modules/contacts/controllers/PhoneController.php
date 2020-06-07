<?php

class Contacts_PhoneController extends Zend_Controller_Action
{
	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Phone();

		if($request->isPost()) {
			$data = $request->getPost();
			if($data['contactid']) {
				if($form->isValid($data) || true) {
					$phoneDb = new Contacts_Model_DbTable_Phone();
					$phoneDataBefore = $phoneDb->getPhone($data['contactid']);
					$latest = end($phoneDataBefore);
				    $phoneDb->addPhone(array('contactid' => $data['contactid'], 'type' => $data['type'], 'ordering' => $latest['ordering']+1));
					$phoneDataAfter = $phoneDb->getPhone($data['contactid']);
					$phone = end($phoneDataAfter);
					echo $this->view->MultiForm('phone', $phone, array('phone', 'type'));
				}
			} else {
				$timestamp = time();
				$phone = array('id' => $timestamp, 'ordering' => $timestamp, 'type' => 'phone', 'phone' => '');
				echo $this->view->MultiForm('phone', $phone, array('phone', 'type'));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Phone();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$phoneDb = new Contacts_Model_DbTable_Phone();
				if($id > 0) {
					$phoneDb->updatePhone($id, $data);
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
			$phoneDb = new Contacts_Model_DbTable_Phone();
			$phoneDb->deletePhone($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
