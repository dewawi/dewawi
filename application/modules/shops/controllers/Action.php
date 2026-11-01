<?php

abstract class Shops_Controller_Action extends DEEC_Controller_SiteAction
{
	protected $cart = null;
	private ?DEEC_Site_SubmissionLimiter $submissionLimiter = null;

	protected function initSiteCart(): Shops_Model_ShoppingCart
	{
		if (!$this->cart) {
			$this->cart = new Shops_Model_ShoppingCart();
			$this->view->cart = $this->cart;
		}

		return $this->cart;
	}

	protected function initSiteLayout(): void
	{
		$this->_helper->getHelper('layout')->setLayout('site');

		$categories = [];
		if ($this->_siteContext->hasFeature('catalog')) {
			$categoryDb = new Shops_Model_DbTable_Category();
			$categories = $categoryDb->getCategories();
		}

		if ($this->_siteContext->hasFeature('cart')) {
			$this->initSiteCart();
		}

		$this->initSiteContactForm();

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus();

		$menuitems = [];
		$menuitemDb = new Shops_Model_DbTable_Menuitem();

		foreach ($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems((int)$menu->id);
		}

		$this->view->shop = $this->_site;
		$this->view->categories = $categories;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
	}

	protected function initSiteContactForm(): void
	{
		if ($this->_siteContext->hasFeature('contact') || $this->_siteContext->hasFeature('inquiry')) {
			$this->view->contact = new Shops_Form_Contact();
		}
	}

	protected function setCanonicalPath(string $path): void
	{
		$this->view->canonicalPath = '/' . ltrim($path, '/');

		if (!$this->_siteContext->isIndexable()) {
			return;
		}

		$siteUrl = $this->_siteContext->getCanonicalBaseUrl();
		if ($siteUrl === null) return;

		$this->view->headLink([
			'rel' => 'canonical',
			'href' => $siteUrl . '/' . ltrim($path, '/'),
		]);
	}

	protected function isReasonableSubmission(array $data, int $maxFields = 200, int $maxBytes = 65536, int $maxDepth = 4): bool
	{
		$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
		if ($contentLength > $maxBytes) return false;

		$fields = 0;
		$bytes = 0;
		$walk = function ($value, int $depth) use (&$walk, &$fields, &$bytes, $maxFields, $maxBytes, $maxDepth): bool {
			if ($depth > $maxDepth) return false;
			if (is_array($value)) {
				foreach ($value as $key => $nested) {
					$bytes += strlen((string)$key);
					if (!$walk($nested, $depth + 1)) return false;
				}
				return true;
			}
			if (!is_scalar($value) && $value !== null) return false;
			$fields++;
			$bytes += strlen((string)$value);
			return $fields <= $maxFields && $bytes <= $maxBytes;
		};

		return $walk($data, 1);
	}

	protected function consumeSubmissionLimit(string $operation, int $limit, int $window): array
	{
		if ($this->submissionLimiter === null) $this->submissionLimiter = new DEEC_Site_SubmissionLimiter();

		return $this->submissionLimiter->consume(
			(int)$this->_siteContext->getClientId(),
			(int)$this->_siteContext->getSiteId(),
			$operation,
			(string)($_SERVER['REMOTE_ADDR'] ?? ''),
			$limit,
			$window
		);
	}

	protected function setRateLimitResponse(int $retryAfter): void
	{
		$this->getResponse()->setHttpResponseCode(429);
		$this->getResponse()->setHeader('Retry-After', (string)max(1, $retryAfter), true);
	}
}
