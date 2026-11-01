<?php

class Shops_SitemapController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$this->getRequest()->isGet() && !$this->getRequest()->isHead()) {
			$this->getResponse()->setHttpResponseCode(404);
			return;
		}

		if (!$this->_siteContext->isIndexable()) {
			$this->getResponse()->setHttpResponseCode(404);
			return;
		}

		$baseUrl = $this->_siteContext->getCanonicalBaseUrl();
		if ($baseUrl === null) {
			$this->getResponse()->setHttpResponseCode(404);
			return;
		}

		$this->getResponse()->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
		$this->getResponse()->setHeader('X-Robots-Tag', 'noindex, follow', true);
		$this->getResponse()->setBody($this->buildXml($baseUrl, new Shops_Model_DbTable_Slug()));
	}

	protected function buildXml(string $baseUrl, Shops_Model_DbTable_Slug $slugDb): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$xml .= $this->renderUrl($baseUrl . '/', 'daily', '1.0');

		$urls = [$baseUrl . '/' => true];

		foreach ($slugDb->getSlugs() as $slug) {
			if ($slug['module'] !== 'shops' || empty($slug['slug']) || empty($slug['controller']) || empty($slug['entityid'])) {
				continue;
			}

			if (!$this->_siteContext->hasFeature('catalog') && in_array($slug['controller'], array('category', 'item', 'tag'), true)) {
				continue;
			}

			if ($slug['controller'] === 'inquiry' && !$this->_siteContext->hasFeature('inquiry')) {
				continue;
			}

			$path = $slugDb->getPublicPath($slug['controller'], (int)$slug['entityid']);

			if (!$path) {
				continue;
			}

			$url = $baseUrl . '/' . ltrim($path, '/');

			if (isset($urls[$url])) {
				continue;
			}

			$urls[$url] = true;
			$xml .= $this->renderUrl($url, 'weekly', '0.6');
		}

		$xml .= '</urlset>';

		return $xml;
	}

	protected function renderUrl(string $url, string $changefreq, string $priority): string
	{
		return '<url>'
			. '<loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>'
			. '<changefreq>' . $changefreq . '</changefreq>'
			. '<priority>' . $priority . '</priority>'
			. '</url>';
	}
}
