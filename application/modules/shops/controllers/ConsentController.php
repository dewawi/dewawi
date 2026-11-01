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

		$value = $this->_siteContext->getAnalyticsConsentValue((string)$this->_getParam('consent', ''));
		if ($value === null || !$this->_siteContext->hasAnalytics()) {
			$response->setHttpResponseCode(400)->setBody('Invalid consent choice');
			return;
		}

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
		if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) return false;

		$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
		if ($origin === '') return true;

		$originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
		$requestHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));

		return $originHost !== '' && hash_equals($requestHost, $originHost);
	}
}
