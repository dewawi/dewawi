<?php

class Contacts_ContactpersonController extends Zend_Controller_Action
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
			if($data['parentid']) {
				if($form->isValid($data) || true) {
					$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
					$contactpersonDataBefore = $contactpersonDb->getContactpersons($data['parentid']);
					$latest = end($contactpersonDataBefore);
					$contactpersonDb->addContactperson(array('contactid' => $data['parentid'], 'ordering' => $latest['ordering']+1));
					$contactpersonDataAfter = $contactpersonDb->getContactpersons($data['parentid']);
					$contactperson = end($contactpersonDataAfter);
					echo $this->view->MultiForm('contacts', 'contactperson', $contactperson, array(
																		//array('label' => 'CONTACT_PERSONS_TITLE', 'field' => 'title'),
																		array('label' => 'CONTACT_PERSONS_SALUTATION', 'field' => 'salutation'),
																		array('label' => 'CONTACT_PERSONS_NAME', 'fields' => array('name1', 'name2')),
																		array('label' => 'CONTACT_PERSONS_DEPARTMENT', 'field' => 'department')
																		),
																		'',
																		array($contactperson['id'] => array())
																	);
				}
			} else {
				$timestamp = time();
				$contactperson = array('id' => $timestamp, 'ordering' => $timestamp, 'type' => 'contactperson', 'address' => '');
				echo $this->view->MultiForm('contacts', 'contactperson', $contactperson, array(
																	//array('label' => 'CONTACT_PERSONS_TITLE', 'field' => 'street'),
																	array('label' => 'CONTACT_PERSONS_SALUTATION', 'field' => 'salutation'),
																	array('label' => 'CONTACT_PERSONS_NAME', 'fields' => array('name1', 'name2')),
																	array('label' => 'CONTACT_PERSONS_DEPARTMENT', 'field' => 'department')
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
				$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
				if($id > 0) {
					$contactpersonDb->updateContactperson($id, $data);
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
			$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
			$contactpersonDb->deleteContactperson($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
