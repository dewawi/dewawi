<?php

class Shops_RobotsController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$this->getRequest()->isGet() && !$this->getRequest()->isHead()) {
			$this->getResponse()->setHttpResponseCode(404);
			return;
		}

		$this->getResponse()->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
		$this->getResponse()->setBody($this->buildBody());
	}

	protected function buildBody(): string
	{
		if (!$this->_siteContext->isIndexable()) {
			return "User-agent: *\nDisallow: /\n";
		}

		$body = "User-agent: *\nAllow: /\n";
		$baseUrl = $this->_siteContext->getCanonicalBaseUrl();

		if ($baseUrl !== null) $body .= 'Sitemap: ' . $baseUrl . "/sitemap.xml\n";

		return $body;
	}
}
