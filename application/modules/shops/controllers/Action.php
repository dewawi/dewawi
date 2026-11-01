<?php

abstract class Shops_Controller_Action extends DEEC_Controller_SiteAction
{
	private const CONVERSION_TTL = 600;
	private const CONVERSION_LIMIT = 10;
	protected $cart = null;
	private ?DEEC_Site_SubmissionLimiter $submissionLimiter = null;
	private bool $siteLayoutInitialized = false;

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
		if ($this->siteLayoutInitialized) return;
		$this->siteLayoutInitialized = true;
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
		$this->view->conversionEvents = $this->consumeSiteConversions();
	}

	protected function renderSiteError(int $status): void
	{
		if (!in_array($status, [404, 410], true)) throw new InvalidArgumentException('Unsupported public error status');

		$this->initSiteLayout();
		$key = $status === 410 ? 'SHOPS_ERROR_GONE' : 'SHOPS_ERROR_NOT_FOUND';
		$title = (string)$this->view->translate($key . '_TITLE');
		$message = (string)$this->view->translate($key . '_MESSAGE');
		$response = $this->getResponse();
		$response->setHttpResponseCode($status);
		$this->view->headTitle()->set($title);
		$this->view->headMeta()->setName('robots', 'noindex, follow');
		$this->clearCanonicalLinks();
		unset($this->view->canonicalPath, $this->view->item, $this->view->category, $this->view->page, $this->view->tag);
		$this->view->siteErrorStatus = $status;
		$this->view->siteErrorTitle = $title;
		$this->view->siteErrorMessage = $message;

		$this->_helper->viewRenderer->setNoRender();
		if ($this->getRequest()->isHead()) {
			$this->_helper->layout->disableLayout();
			$response->setBody('');
			return;
		}

		$accept = (string)$this->getRequest()->getHeader('Accept');
		if ($this->getRequest()->isXmlHttpRequest() || stripos($accept, 'application/json') !== false) {
			$this->_helper->layout->disableLayout();
			$response->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
			$response->setBody(json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			return;
		}

		$this->renderScript('error/site-error.phtml');
	}

	private function clearCanonicalLinks(): void
	{
		$headLink = $this->view->headLink();
		foreach ($headLink as $index => $link) {
			if (strtolower((string)($link->rel ?? '')) === 'canonical') $headLink->offsetUnset($index);
		}
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

	protected function markSiteConversion(string $name, ?float $value = null, ?string $currency = null, ?int $now = null, ?array $cookies = null, ?string $environment = null): void
	{
		try {
			if (!in_array($name, ['contact_submitted', 'technical_inquiry_submitted', 'order_submitted'], true)) return;
			$interface = $this->_siteContext->getAnalyticsEventInterface();
			if ($interface === null || !$this->_siteContext->canLoadAnalytics($cookies, $environment)) {
				$this->clearSiteConversions();
				return;
			}

			$parameters = ['shop_id' => (int)$this->_siteContext->getSiteId()];
			if ($name === 'order_submitted') {
				$currency = strtoupper(trim((string)$currency));
				if ($value === null || !is_finite($value) || $value < 0 || !preg_match('/^[A-Z]{3}$/D', $currency)) return;
				$parameters['value'] = round($value, 4);
				$parameters['currency'] = $currency;
			}

			$session = $this->getSiteConversionSession();
			$events = is_array($session->events ?? null) ? $session->events : [];
			$events[] = [
				'id' => bin2hex(random_bytes(16)),
				'name' => $name,
				'parameters' => $parameters,
				'interface' => $interface,
				'clientid' => (int)$this->_siteContext->getClientId(),
				'siteid' => (int)$this->_siteContext->getSiteId(),
				'created' => $now ?? time(),
			];
			$session->events = array_slice($events, -self::CONVERSION_LIMIT);
		} catch (Throwable $e) {
			error_log('Shop conversion marker could not be stored: ' . $e->getMessage());
		}
	}

	protected function clearSiteConversions(): void
	{
		try {
			$session = $this->getSiteConversionSession();
			unset($session->events);
		} catch (Throwable $e) {
			error_log('Shop conversion markers could not be cleared: ' . $e->getMessage());
		}
	}

	protected function consumeSiteConversions(?int $now = null, ?array $cookies = null, ?string $environment = null): array
	{
		try {
			$session = $this->getSiteConversionSession();
			$events = is_array($session->events ?? null) ? $session->events : [];
			unset($session->events);

			$interface = $this->_siteContext->getAnalyticsEventInterface();
			if ($interface === null || !$this->_siteContext->canLoadAnalytics($cookies, $environment)) return [];
			$now = $now ?? time();
			$out = [];

			foreach ($events as $event) {
				if (!is_array($event)
					|| !preg_match('/^[a-f0-9]{32}$/D', (string)($event['id'] ?? ''))
					|| (int)($event['clientid'] ?? 0) !== (int)$this->_siteContext->getClientId()
					|| (int)($event['siteid'] ?? 0) !== (int)$this->_siteContext->getSiteId()
					|| (string)($event['interface'] ?? '') !== $interface
					|| (int)($event['created'] ?? 0) < $now - self::CONVERSION_TTL
					|| (int)($event['created'] ?? 0) > $now + 30) continue;

				$name = (string)($event['name'] ?? '');
				$parameters = is_array($event['parameters'] ?? null) ? $event['parameters'] : [];
				if (!in_array($name, ['contact_submitted', 'technical_inquiry_submitted', 'order_submitted'], true)
					|| (int)($parameters['shop_id'] ?? 0) !== (int)$this->_siteContext->getSiteId()) continue;

				$cleanParameters = ['shop_id' => (int)$parameters['shop_id']];
				if ($name === 'order_submitted') {
					$value = $parameters['value'] ?? null;
					$currency = (string)($parameters['currency'] ?? '');
					if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || !preg_match('/^[A-Z]{3}$/D', $currency)) continue;
					$cleanParameters['value'] = round((float)$value, 4);
					$cleanParameters['currency'] = $currency;
				}

				$out[] = [
					'id' => (string)$event['id'],
					'interface' => $interface,
					'name' => $name,
					'parameters' => $cleanParameters,
				];
			}

			return $out;
		} catch (Throwable $e) {
			error_log('Shop conversion marker could not be consumed: ' . $e->getMessage());
			return [];
		}
	}

	private function getSiteConversionSession(): Zend_Session_Namespace
	{
		$session = new Zend_Session_Namespace('SiteConversion');
		$context = $this->_siteContext->getClientId() . ':' . $this->_siteContext->getSiteId();
		if (($session->context ?? '') !== $context) {
			unset($session->events);
			$session->context = $context;
		}

		return $session;
	}
}
