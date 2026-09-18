<?php

class Shops_ContactController extends Shops_Controller_Action
{
	protected $contactDataSession;

	public function init()
	{
		parent::init();

		$this->contactDataSession = new Zend_Session_Namespace('ShopsContact');
	}

	public function sendAction()
	{
		$request = $this->getRequest();

		if (!$request->isPost()) {
			return $this->_helper->redirector->gotoSimple('index', 'index', 'shops');
		}

		$data = $request->getPost();
		$this->contactDataSession->formData = $data;

		if (!empty($data['fax_number'])) {
			return $this->_helper->redirector->gotoRoute([], 'contact_success', true);
		}

		$this->_helper->Email->sendEmail('shops', 'contact', 'contact');

		return $this->_helper->redirector->gotoRoute([], 'contact_success', true);
	}

	public function successAction()
	{
		$this->initSiteLayout();

		$this->view->formData = $this->contactDataSession->formData ?? [];

		$this->assignMessages();
	}

	public function errorAction()
	{
		$this->initSiteLayout();

		$this->view->formData = [
			'name' => $this->_getParam('name'),
			'email' => $this->_getParam('email'),
			'subject' => $this->_getParam('subject'),
			'message' => $this->_getParam('message'),
		];

		$this->assignMessages();
	}
}
