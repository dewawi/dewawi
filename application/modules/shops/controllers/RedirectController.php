<?php

class Shops_RedirectController extends Shops_Controller_Action
{
	public function indexAction()
	{
		if (!$this->getRequest()->isGet() && !$this->getRequest()->isHead()) {
			throw new Zend_Controller_Action_Exception('Redirect not found', 404);
		}

		$path = DEEC_Site_Router::getRelativeRequestPath(
			$this->getRequest()->getRequestUri(),
			$this->_siteContext->getBasePath()
		);
		if ($path === null) throw new Zend_Controller_Action_Exception('Redirect not found', 404);
		$result = (new Shops_Model_DbTable_Slugredirect())->resolve($path);

		if (!$result) {
			$this->renderSiteError(404);
			return;
		}
		if ($result['status'] === Shops_Model_DbTable_Slugredirect::STATUS_GONE) {
			$this->renderSiteError(410);
			return;
		}

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();
		$target = (string)$result['target'];

		$this->getResponse()
			->setHttpResponseCode(301)
			->setHeader('Location', $this->_siteContext->getBasePath() . '/' . ltrim($target, '/'), true);
	}
}
