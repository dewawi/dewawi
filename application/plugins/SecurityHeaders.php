<?php

class Application_Plugin_SecurityHeaders extends Zend_Controller_Plugin_Abstract
{
	public function dispatchLoopShutdown(): void
	{
		$response = $this->getResponse();
		$request = $this->getRequest();
		$publicSite = $request
			&& $request->getModuleName() === 'shops'
			&& Zend_Registry::isRegistered('SiteContext')
			&& Zend_Registry::get('SiteContext') instanceof DEEC_Site_Context;

		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin', true);
		$response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', true);
		if (!$this->hasHeader($response, 'Cache-Control')) $response->setHeader('Cache-Control', 'private, no-store', true);

		if (!$publicSite) {
			$response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
			$response->setHeader('X-Frame-Options', 'SAMEORIGIN', true);
			$response->setHeader('Content-Security-Policy', "frame-ancestors 'self'", true);
			return;
		}

		if (!Zend_Registry::get('SiteContext')->isIndexable()) {
			$response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
		}
	}

	private function hasHeader(Zend_Controller_Response_Http $response, string $name): bool
	{
		foreach ($response->getHeaders() as $header) {
			if (strcasecmp($header['name'], $name) === 0) return true;
		}

		return false;
	}
}
