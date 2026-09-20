<?php

class DEEC_Site_Router
{
	public function registerRoutes(DEEC_Site_Context $siteContext)
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		$this->registerFallbackRoute($router);
		$this->registerSlugRoute($router, $siteContext);
		$this->registerBaseRoutes($router, $siteContext);
	}

	protected function registerBaseRoutes(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
	{
		$router->addRoute('shop_home', new Zend_Controller_Router_Route(
			'/',
			array(
				'module' => 'shops',
				'controller' => 'index',
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
				'controller' => 'index',
				'action' => 'index'
			)
		));
	}

	protected function registerSlugRoute(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
	{
		$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

		if ($path === '') {
			return;
		}

		$slugDb = new Shops_Model_DbTable_Slug();
		$slug = $slugDb->resolvePath($path);

		if (!$slug || !$this->isSlugControllerEnabled((string)$slug['controller'], $siteContext)) {
			return;
		}

		$router->addRoute(
			'shop_slug',
			new Zend_Controller_Router_Route(
				$path,
				array(
					'module' => 'shops',
					'controller' => $slug['controller'],
					'action' => 'index',
					'id' => $slug['entityid']
				)
			)
		);
	}

	protected function isSlugControllerEnabled($controller, DEEC_Site_Context $siteContext)
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
