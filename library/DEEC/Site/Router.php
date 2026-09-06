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

	protected function registerSlugRoute(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
	{
		$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

		if ($path === '') {
			return;
		}

		$segments = explode('/', $path);
		$slug = end($segments);

		if (!$slug) {
			return;
		}

		$slugTable = new Zend_Db_Table('slug');
		$slugs = $slugTable->fetchAll(array(
			'shopid = ?' => $siteContext->getSiteId(),
			'clientid = ?' => $siteContext->getClientId(),
			'slug = ?' => $slug,
			'deleted = ?' => 0
		));

		foreach ($slugs as $row) {
			$slugData = $row->toArray();

			if ($this->buildSlugPath($slugData, $slugTable, $siteContext) !== $path) {
				continue;
			}

			$router->addRoute(
				'shop_slug',
				new Zend_Controller_Router_Route(
					$path,
					array(
						'module' => $slugData['module'],
						'controller' => $slugData['controller'],
						'action' => 'index',
						'id' => $slugData['entityid']
					)
				)
			);

			return;
		}
	}

	protected function buildSlugPath(array $item, Zend_Db_Table $slugTable, DEEC_Site_Context $siteContext)
	{
		$path = trim($item['slug'], '/');
		$visited = array();

		while (!empty($item['parentid'])) {
			$controller = $item['controller'] === 'item' ? 'category' : $item['controller'];
			$key = $controller . ':' . (int)$item['parentid'];

			if (isset($visited[$key])) {
				break;
			}

			$visited[$key] = true;

			$parent = $slugTable->fetchRow(array(
				'module = ?' => $item['module'],
				'controller = ?' => $controller,
				'entityid = ?' => (int)$item['parentid'],
				'shopid = ?' => $siteContext->getSiteId(),
				'clientid = ?' => $siteContext->getClientId(),
				'deleted = ?' => 0
			));

			if (!$parent) {
				break;
			}

			$item = $parent->toArray();

			if (empty($item['slug'])) {
				break;
			}

			$path = trim($item['slug'], '/') . '/' . $path;
		}

		return $path;
	}

	protected function getSlugKey(array $slugData)
	{
		return $slugData['module'] . ':' . $slugData['controller'] . ':' . $slugData['entityid'];
	}

	protected function getParentSlugKey(array $slugData)
	{
		$controller = $slugData['controller'];

		if ($controller === 'item') {
			$controller = 'category';
		}

		return $slugData['module'] . ':' . $controller . ':' . $slugData['parentid'];
	}

	protected function getRouteName(array $slugData)
	{
		return 'slug_' . $slugData['controller'] . '_' . $slugData['entityid'];
	}
}
