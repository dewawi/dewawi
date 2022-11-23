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
			if($data['parentid']) {
				if($form->isValid($data) || true) {
					$phoneDb = new Contacts_Model_DbTable_Phone();
					$phoneDataBefore = $phoneDb->getPhone($data['parentid']);
					$latest = end($phoneDataBefore);
					$phoneDb->addPhone(array('contactid' => $data['parentid'], 'type' => $data['type'], 'ordering' => $latest['ordering']+1));
					$phoneDataAfter = $phoneDb->getPhone($data['parentid']);
					$phone = end($phoneDataAfter);
					echo $this->view->MultiForm('contacts', 'phone', $phone, array('phone', 'type'));
				}
			} else {
				$timestamp = time();
				$phone = array('id' => $timestamp, 'ordering' => $timestamp, 'type' => 'phone', 'phone' => '');
				echo $this->view->MultiForm('contacts', 'phone', $phone, array('phone', 'type'));
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
			$phoneDb = new Contacts_Model_DbTable_Phone();
			$phoneDb->deletePhone($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
