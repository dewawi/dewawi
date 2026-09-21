<?php

class Campaigns_UnsubscribeController extends Zend_Controller_Action
{
	public function init()
	{
		$this->_helper->layout->disableLayout();
	}

	public function indexAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$token = trim((string)$this->_getParam('token', ''));

		$this->view->valid = false;
		$this->view->unsubscribed = false;
		$this->view->id = $id;
		$this->view->token = $token;

		if($id <= 0 || $token === '') return;

		$emailDb = new Contacts_Model_DbTable_Email();
		$email = $emailDb->getEmail($id);

		if(!$email || !empty($email['deleted']) || !$this->isValidToken($email, $token)) return;

		$this->view->valid = true;

		if(!$this->getRequest()->isPost()) return;

		$emailDb->setClientId((int)$email['clientid']);
		$emailDb->suppressByEmail((string)$email['email'], 'unsubscribe');

		$this->view->unsubscribed = true;
	}

	private function isValidToken(array $email, string $token): bool
	{
		if(empty($email['password'])) return false;

		$expected = hash('sha256', (int)$email['id'].'|'.(int)$email['clientid'].'|'.$email['password']);

		return hash_equals($expected, $token);
	}
}
