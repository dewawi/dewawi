<?php

class DEEC_Site_Router
{
	public static function isReservedPath(string $path): bool
	{
		$segment = explode('/', trim($path, '/'))[0] ?? '';

		return in_array($segment, [
			'robots.txt', 'sitemap.xml', 'search', 'contact', 'inquiry', 'cart',
			'checkout', 'cookie-consent', 'products-de.xml', 'product', 'files',
			'media', 'cache', 'library', 'admin', 'user', 'users', 'login', 'logout',
			'email-delivery', 'campaigns',
		], true);
	}

	public static function isRedirectablePath(string $path): bool
	{
		$path = trim($path, '/');

		if ($path === '' || strlen($path) > 2048 || self::isReservedPath($path)) return false;
		if (preg_match('~[\\\\?#\x00-\x20\x7f]|(?:^|/)\.{1,2}(?:/|$)~', $path)) return false;
		if (preg_match('~(?:^|/)[^/]*\.[^/]*(?:/|$)~', $path)) return false;

		return true;
	}

	public static function getRelativeRequestPath(string $requestUri, string $basePath = ''): ?string
	{
		$path = parse_url($requestUri, PHP_URL_PATH);
		if (!is_string($path)) return null;

		$basePath = rtrim($basePath, '/');
		if ($basePath !== '') {
			if ($path === $basePath || $path === $basePath . '/') return '';
			if (!str_starts_with($path, $basePath . '/')) return null;
			$path = substr($path, strlen($basePath));
		}

		return trim($path, '/');
	}

	public static function isCanonicalPublicPathRequest(string $requestUri, string $basePath, string $path): bool
	{
		$requestPath = parse_url($requestUri, PHP_URL_PATH);
		if (!is_string($requestPath)) return false;

		$basePath = rtrim($basePath, '/');
		$path = trim($path, '/');
		$canonicalPath = $basePath . ($path === '' ? '/' : '/' . $path);

		return $requestPath === $canonicalPath;
	}

	public static function isTrailingSlashPublicPathRequest(string $requestUri, string $basePath, string $path): bool
	{
		$requestPath = parse_url($requestUri, PHP_URL_PATH);
		if (!is_string($requestPath)) return false;

		$basePath = rtrim($basePath, '/');
		$path = trim($path, '/');
		if ($path === '') return $basePath !== '' && $requestPath === $basePath;

		return $requestPath === $basePath . '/' . $path . '/';
	}

	public static function getCanonicalRedirectLocation(string $requestUri, string $method, string $basePath, string $path, ?string $controller = null): ?string
	{
		if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)) return null;
		if (!self::isTrailingSlashPublicPathRequest($requestUri, $basePath, $path)) return null;

		$basePath = rtrim($basePath, '/');
		$path = trim($path, '/');
		if ($path !== '' && !self::isRedirectablePath($path)) return null;

		$location = $basePath . ($path === '' ? '/' : '/' . $path);
		if ($controller !== 'category') return $location;

		$query = parse_url($requestUri, PHP_URL_QUERY);
		if (!is_string($query) || $query === '') return $location;

		parse_str($query, $parameters);
		$allowed = [];
		$order = $parameters['order'] ?? null;
		if (is_string($order) && in_array($order, ['created', 'title'], true)) $allowed['order'] = $order;
		$sort = $parameters['sort'] ?? null;
		if (is_string($sort) && strtoupper($sort) === 'ASC') $allowed['sort'] = 'ASC';
		$page = $parameters['page'] ?? null;
		if (is_string($page) && preg_match('/^[0-9]+$/D', $page)) {
			$page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
			if ($page !== false) $allowed['page'] = $page;
		}

		return $location . ($allowed ? '?' . http_build_query($allowed, '', '&', PHP_QUERY_RFC3986) : '');
	}

	public function registerRoutes(DEEC_Site_Context $siteContext)
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		$this->registerFallbackRoute($router);
		$hasSlug = $this->registerSlugRoute($router, $siteContext);
		if (!$hasSlug) $this->registerRedirectRoute($router);
		$this->registerBaseRoutes($router, $siteContext);
	}

	protected function registerBaseRoutes(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
	{
		$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
		$homeController = 'index';
		$homeId = null;
		if (self::getCanonicalRedirectLocation($requestUri, $requestMethod, $siteContext->getBasePath(), '') !== null) {
			$homeController = 'redirect';
		} elseif (
			!self::isCanonicalPublicPathRequest($requestUri, $siteContext->getBasePath(), '')
			&& !self::isTrailingSlashPublicPathRequest($requestUri, $siteContext->getBasePath(), '')
		) {
			$homeController = 'page';
			$homeId = 0;
		}

		$router->addRoute('shop_home', new Zend_Controller_Router_Route(
			'/',
			array(
				'module' => 'shops',
				'controller' => $homeController,
				'action' => 'index',
				'id' => $homeId
			)
		));

		$router->addRoute('robots', new Zend_Controller_Router_Route(
			'robots.txt',
			array(
				'module' => 'shops',
				'controller' => 'robots',
				'action' => 'index'
			)
		));

		$router->addRoute('sitemap', new Zend_Controller_Router_Route(
			'sitemap.xml',
			array(
				'module' => 'shops',
				'controller' => 'sitemap',
				'action' => 'index'
			)
		));

		$router->addRoute('search', new Zend_Controller_Router_Route(
			'search',
			array(
				'module' => 'shops',
				'controller' => 'search',
				'action' => 'index'
			)
		));

		$router->addRoute('site_consent', new Zend_Controller_Router_Route(
			'cookie-consent',
			array(
				'module' => 'shops',
				'controller' => 'consent',
				'action' => 'save'
			)
		));

		if ($siteContext->hasFeature('contact') || $siteContext->hasFeature('inquiry')) {
			$router->addRoute('contact_send', new Zend_Controller_Router_Route(
				'contact/send',
				array(
					'module' => 'shops',
					'controller' => 'contact',
					'action' => 'send'
				)
			));

			$router->addRoute('contact_success', new Zend_Controller_Router_Route(
				'contact/success',
				array(
					'module' => 'shops',
					'controller' => 'contact',
					'action' => 'success'
				)
			));

			$router->addRoute('contact_error', new Zend_Controller_Router_Route(
				'contact/error',
				array(
					'module' => 'shops',
					'controller' => 'contact',
					'action' => 'error'
				)
			));
		}

		if ($siteContext->hasFeature('inquiry')) {
			$router->addRoute('inquiry_send', new Zend_Controller_Router_Route(
				'inquiry/send',
				array(
					'module' => 'shops',
					'controller' => 'inquiry',
					'action' => 'send'
				)
			));

			$router->addRoute('inquiry_success', new Zend_Controller_Router_Route(
				'inquiry/success',
				array(
					'module' => 'shops',
					'controller' => 'inquiry',
					'action' => 'success'
				)
			));

			$router->addRoute('inquiry_error', new Zend_Controller_Router_Route(
				'inquiry/error',
				array(
					'module' => 'shops',
					'controller' => 'inquiry',
					'action' => 'error'
				)
			));
		}

		if ($siteContext->hasFeature('cart')) {
			$router->addRoute('cart', new Zend_Controller_Router_Route(
				'cart',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'index'
				)
			));

			$router->addRoute('cart_add', new Zend_Controller_Router_Route(
				'cart/add',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'add'
				)
			));

			$router->addRoute('cart_update', new Zend_Controller_Router_Route(
				'cart/update',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'update'
				)
			));

			$router->addRoute('cart_remove', new Zend_Controller_Router_Route(
				'cart/remove',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'remove'
				)
			));

			$router->addRoute('cart_clear', new Zend_Controller_Router_Route(
				'cart/clear',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'clear'
				)
			));
		}

		if ($siteContext->hasFeature('checkout')) {
			$router->addRoute('checkout', new Zend_Controller_Router_Route(
				'checkout',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'index'
				)
			));

			$router->addRoute('checkout_send', new Zend_Controller_Router_Route(
				'checkout/send',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'send'
				)
			));

			$router->addRoute('checkout_success', new Zend_Controller_Router_Route(
				'checkout/success',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'success'
				)
			));
		}

		if ($siteContext->hasFeature('catalog')) {
			$router->addRoute('feed', new Zend_Controller_Router_Route(
				'products-de.xml',
				array(
					'module' => 'shops',
					'controller' => 'item',
					'action' => 'feed'
				)
			));

			$router->addRoute('product', new Zend_Controller_Router_Route(
				'product/:id',
				array(
					'module' => 'shops',
					'controller' => 'item',
					'action' => 'index',
					'id' => null
				),
				array(
					'id' => '\d+'
				)
			));
		}
	}

	protected function registerFallbackRoute(Zend_Controller_Router_Rewrite $router)
	{
		$router->addRoute('shop_fallback', new Zend_Controller_Router_Route(
			'*',
			array(
				'module' => 'shops',
				'controller' => 'page',
				'action' => 'index',
				'id' => 0
			)
		));
	}

	protected function registerSlugRoute(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext): bool
	{
		$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$path = self::getRelativeRequestPath(
			$requestUri,
			$siteContext->getBasePath()
		);

		if ($path === null || $path === '') {
			return false;
		}
		if (self::isReservedPath($path)) {
			return false;
		}

		$slugDb = new Shops_Model_DbTable_Slug();
		$slug = $slugDb->resolvePath($path);

		if (!$slug || !self::isSlugControllerEnabled((string)$slug['controller'], $siteContext)) {
			return false;
		}
		if (
			!self::isCanonicalPublicPathRequest($requestUri, $siteContext->getBasePath(), $path)
			&& !self::isTrailingSlashPublicPathRequest($requestUri, $siteContext->getBasePath(), $path)
		) return false;

		$controller = (string)$slug['controller'];
		if (self::getCanonicalRedirectLocation(
			$requestUri,
			(string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
			$siteContext->getBasePath(),
			$path,
			$controller
		) !== null) $controller = 'redirect';

		$router->addRoute(
			'shop_slug',
			new Zend_Controller_Router_Route(
				$path,
				array(
					'module' => 'shops',
					'controller' => $controller,
					'action' => 'index',
					'id' => $slug['entityid']
				)
			)
		);

		return true;
	}

	protected function registerRedirectRoute(Zend_Controller_Router_Rewrite $router): void
	{
		if (!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true)) return;

		$siteContext = Zend_Registry::get('SiteContext');
		$path = self::getRelativeRequestPath(
			(string)($_SERVER['REQUEST_URI'] ?? ''),
			$siteContext instanceof DEEC_Site_Context ? $siteContext->getBasePath() : ''
		);
		if ($path === null) return;
		if (!DEEC_Site_Router::isRedirectablePath($path)) return;
		if (!self::isCanonicalPublicPathRequest(
			(string)($_SERVER['REQUEST_URI'] ?? ''),
			$siteContext instanceof DEEC_Site_Context ? $siteContext->getBasePath() : '',
			$path
		)) return;

		$result = (new Shops_Model_DbTable_Slugredirect())->resolve($path);
		if (!$result) return;

		$router->addRoute('shop_redirect', new Zend_Controller_Router_Route(
			$path,
			array(
				'module' => 'shops',
				'controller' => 'redirect',
				'action' => 'index'
			)
		));
	}

	public static function isSlugControllerEnabled($controller, DEEC_Site_Context $siteContext)
	{
		if ($controller === 'inquiry') {
			return $siteContext->hasFeature('inquiry');
		}

		if (!in_array($controller, array('page', 'category', 'item', 'tag'), true)) {
			return false;
		}

		if (in_array($controller, array('category', 'item', 'tag'), true)) {
			return $siteContext->hasFeature('catalog');
		}

		return true;
	}
}
