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

		$slug = (new Shops_Model_DbTable_Slug())->resolvePath($path);
		$location = $slug && DEEC_Site_Router::isSlugControllerEnabled((string)$slug['controller'], $this->_siteContext)
			? DEEC_Site_Router::getCanonicalRedirectLocation(
				$this->getRequest()->getRequestUri(),
				$this->getRequest()->getMethod(),
				$this->_siteContext->getBasePath(),
				$path,
				(string)$slug['controller']
			)
			: null;
		if ($path === '') {
			$location = DEEC_Site_Router::getCanonicalRedirectLocation(
				$this->getRequest()->getRequestUri(),
				$this->getRequest()->getMethod(),
				$this->_siteContext->getBasePath(),
				''
			);
		}
		if ($location !== null) {
			$this->redirectPermanently($location);
			return;
		}
		if (!DEEC_Site_Router::isCanonicalPublicPathRequest(
			$this->getRequest()->getRequestUri(),
			$this->_siteContext->getBasePath(),
			$path
		)) {
			$this->renderSiteError(404);
			return;
		}

		$result = (new Shops_Model_DbTable_Slugredirect())->resolve($path);

		if (!$result) {
			$this->renderSiteError(404);
			return;
		}
		if ($result['status'] === Shops_Model_DbTable_Slugredirect::STATUS_GONE) {
			$this->renderSiteError(410);
			return;
		}

		$target = (string)$result['target'];
		$this->redirectPermanently($this->_siteContext->getBasePath() . '/' . ltrim($target, '/'));
	}

	private function redirectPermanently(string $location): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();
		$this->getResponse()
			->setHttpResponseCode(301)
			->setHeader('Location', $location, true);
	}
}
