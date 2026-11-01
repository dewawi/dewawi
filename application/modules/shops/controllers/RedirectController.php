<?php

class Shops_RedirectController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$this->getRequest()->isGet() && !$this->getRequest()->isHead()) {
			throw new Zend_Controller_Action_Exception('Redirect not found', 404);
		}

		$path = trim((string)parse_url($this->getRequest()->getRequestUri(), PHP_URL_PATH), '/');
		$target = (new Shops_Model_DbTable_Slugredirect())->resolvePath($path);

		if (!$target) throw new Zend_Controller_Action_Exception('Redirect not found', 404);

		$this->getResponse()
			->setHttpResponseCode(301)
			->setHeader('Location', '/' . ltrim($target, '/'), true);
	}
}
