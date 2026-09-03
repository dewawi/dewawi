<?php

class DEEC_Site_Router
{
	public function registerRoutes(DEEC_Site_Context $siteContext)
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		$this->registerFallbackRoute($router);
		$this->registerSlugRoutes($router, $siteContext);
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

	protected function registerSlugRoutes(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
	{
		$slugTable = new Zend_Db_Table('slug');
		$slugs = $slugTable->fetchAll(array(
			'shopid = ?' => $siteContext->getSiteId(),
			'clientid = ?' => $siteContext->getClientId(),
			'deleted = ?' => 0
		));

		$slugDict = array();

		foreach ($slugs as $slug) {
			$slugData = $slug->toArray();
			$slugDict[$this->getSlugKey($slugData)] = $slugData;
		}

		foreach ($slugs as $slug) {
			$slugData = $slug->toArray();

			if (empty($slugData['slug'])) {
				continue;
			}

			$router->addRoute(
				$this->getRouteName($slugData),
				new Zend_Controller_Router_Route(
					$this->buildFullSlug($slugData, $slugDict),
					array(
						'module' => $slugData['module'],
						'controller' => $slugData['controller'],
						'action' => 'index',
						'id' => $slugData['entityid']
					)
				)
			);
		}
	}

	protected function buildFullSlug(array $item, array $slugDict)
	{
		$slug = $item['slug'];
		$visited = array();

		while (!empty($item['parentid'])) {
			$parentKey = $this->getParentSlugKey($item);

			if (!isset($slugDict[$parentKey]) || isset($visited[$parentKey])) {
				break;
			}

			$visited[$parentKey] = true;
			$item = $slugDict[$parentKey];
			$slug = $item['slug'] . '/' . $slug;
		}

		return $slug;
	}

	protected function getSlugKey(array $slugData)
	{
		return $slugData['module'] . ':' . $slugData['controller'] . ':' . $slugData['entityid'];
	}

	protected function getRouteName(array $slugData)
	{
		return 'slug_' . $slugData['controller'] . '_' . $slugData['entityid'];
	}
}
