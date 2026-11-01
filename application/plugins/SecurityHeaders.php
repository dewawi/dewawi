<?php

class Application_Plugin_SecurityHeaders extends Zend_Controller_Plugin_Abstract
{
	public function dispatchLoopShutdown(): void
	{
		$response = $this->getResponse();
		$request = $this->getRequest();
		$publicSite = $request && $request->getModuleName() === 'shops';

		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin', true);
		$response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', true);

		if ($publicSite) return;

		$response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
		$response->setHeader('X-Frame-Options', 'SAMEORIGIN', true);
		$response->setHeader('Content-Security-Policy', "frame-ancestors 'self'", true);
	}
}
