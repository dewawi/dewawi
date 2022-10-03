<?php

class Contacts_AddressController extends Zend_Controller_Action
{
	public function init()
	{
		$this->view->user = $this->_user = Zend_Registry::get('User');
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();
		$options = $this->_helper->Options->getOptions($form);
		$this->view->options = $options;
		$this->view->action = 'add';

		$client = Zend_Registry::get('Client');

		if($request->isPost()) {
			$data = $request->getPost();
			if($data['contactid']) {
				if($form->isValid($data) || true) {
					$addressDb = new Contacts_Model_DbTable_Address();
					$addressDataBefore = $addressDb->getAddress($data['contactid']);
					$latest = end($addressDataBefore);
					$addressDb->addAddress(array('contactid' => $data['contactid'], 'type' => $data['type'], 'country' => $client['country'], 'ordering' => $latest['ordering']+1));
					$addressDataAfter = $addressDb->getAddress($data['contactid']);
					$address = end($addressDataAfter);
					echo $this->view->MultiForm('contacts', 'address', $address, array(
																		//array('label' => 'CONTACTS_NAME', 'field' => 'name1'),
																		array('label' => 'CONTACTS_STREET', 'field' => 'street'),
																		array('label' => 'CONTACTS_POSTCODE_CITY', 'fields' => array('postcode', 'city')),
																		array('label' => 'CONTACTS_COUNTRY_ADDRESS_TYPE', 'fields' => array('country', 'type'))
																		));
				}
			} else {
				$timestamp = time();
				$address = array('id' => $timestamp, 'ordering' => $timestamp, 'type' => 'address', 'address' => '');
				echo $this->view->MultiForm('contacts', 'address', $address, array(
																	array('label' => 'CONTACTS_STREET', 'field' => 'street'),
																	array('label' => 'CONTACTS_POSTCODE_CITY', 'fields' => array('postcode', 'city')),
																	array('label' => 'CONTACTS_COUNTRY_ADDRESS_TYPE', 'fields' => array('country', 'type'))
																	));
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
			$addressDb = new Contacts_Model_DbTable_Address();
			$addressDb->deleteAddress($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
