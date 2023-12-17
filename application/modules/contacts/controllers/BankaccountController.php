<?php

class Contacts_BankaccountController extends Zend_Controller_Action
{
	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Bankaccount();

		if($request->isPost()) {
			$data = $request->getPost();
			if($data['parentid']) {
				if($form->isValid($data) || true) {
					$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
					$bankAccountDataBefore = $bankAccountDb->getBankaccount($data['parentid']);
					$latestOrdering = is_array($bankAccountDataBefore) && !empty($bankAccountDataBefore)
						? end($bankAccountDataBefore)['ordering']
						: 0;
					$bankAccountDb->addBankaccount(array('contactid' => $data['parentid'], 'ordering' => $latestOrdering+1));
					$bankAccountDataAfter = $bankAccountDb->getBankaccount($data['parentid']);
					$bankAccount = end($bankAccountDataAfter);
					echo $this->view->MultiForm('contacts', 'bankaccount', $bankAccount, array('iban', 'bic'));
				}
			} else {
				$timestamp = time();
				$bankAccount = array('id' => $timestamp, 'ordering' => $timestamp, 'type' => 'bankAccount', 'bankAccount' => '');
				echo $this->view->MultiForm('contacts', 'bankaccount', $bankAccount, array('iban', 'bic'));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Bankaccount();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
				if($id > 0) {
					$bankAccountDb->updateBankaccount($id, $data);
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
			$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
			$bankAccountDb->deleteBankaccount($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
