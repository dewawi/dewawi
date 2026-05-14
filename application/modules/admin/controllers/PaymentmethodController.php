<?php

class Admin_PaymentmethodController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Paymentmethod();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$paymentmethodsDb = new Admin_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodsDb->getPaymentmethods();

		$this->view->form = $form;
		$this->view->paymentmethods = $paymentmethods;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Paymentmethod();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$paymentmethodsDb = new Admin_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodsDb->getPaymentmethods();

		$this->view->form = $form;
		$this->view->paymentmethods = $paymentmethods;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Paymentmethod();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
				$id = $paymentmethodDb->addPaymentmethod($data);
				echo Zend_Json::encode($paymentmethodDb->getPaymentmethod($id));
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

		$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
		$paymentmethod = $paymentmethodDb->getPaymentmethod($id);

		if($this->isLocked($paymentmethod['locked'], $paymentmethod['lockedtime'])) {
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
			$paymentmethodDb->lock($id);

			$form = new Admin_Form_Paymentmethod();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
					$paymentmethodDb->updatePaymentmethod($id, $data);
					echo Zend_Json::encode($paymentmethodDb->getPaymentmethod($id));
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
		$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
		$data = $paymentmethodDb->getPaymentmethod($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$paymentmethodDb->addPaymentmethod($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
			$paymentmethodDb->deletePaymentmethod($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
