<?php

class Shops_ConsentController extends Shops_Controller_Action
{
	private const COOKIE_LIFETIME = 15552000;

	public function saveAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
		$response = $this->getResponse();
		$response->setHeader('Cache-Control', 'no-store, private', true);

		if (!$this->getRequest()->isPost() || !$this->isSameOriginRequest()) {
			$response->setHttpResponseCode(403)->setBody('Invalid consent request');
			return;
		}

		$state = (string)$this->_getParam('consent', '');
		$value = $this->_siteContext->getAnalyticsConsentValue($state);
		if ($value === null || !$this->_siteContext->hasAnalytics()) {
			$response->setHttpResponseCode(400)->setBody('Invalid consent choice');
			return;
		}
		if ($state === 'declined') $this->clearSiteConversions();

		$canonicalUrl = $this->_siteContext->getCanonicalBaseUrl();
		$secure = $this->getRequest()->isSecure() || ($canonicalUrl !== null && parse_url($canonicalUrl, PHP_URL_SCHEME) === 'https');
		setcookie($this->_siteContext->getAnalyticsConsentCookieName(), $value, [
			'expires' => time() + self::COOKIE_LIFETIME,
			'path' => $this->cookiePath(),
			'secure' => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]);

		$this->_helper->redirector->gotoUrl($this->returnPath());
	}

	private function cookiePath(): string
	{
		$baseUrl = rtrim((string)$this->getRequest()->getBaseUrl(), '/');

		return $baseUrl === '' ? '/' : $baseUrl . '/';
	}

	private function returnPath(): string
	{
		$path = (string)$this->_getParam('return', '/');

		return preg_match('~^/(?!/)[^\r\n]*$~D', $path) ? $path : '/';
	}

	private function isSameOriginRequest(): bool
	{
		$fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
		if ($fetchSite !== '') return in_array($fetchSite, ['same-origin', 'none'], true);

		$source = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
		if ($source === '') $source = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
		if ($source === '') return false;

		$originHost = strtolower((string)parse_url($source, PHP_URL_HOST));
		$requestHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));

		return $originHost !== '' && hash_equals($requestHost, $originHost);
	}
}
