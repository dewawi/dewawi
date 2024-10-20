<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

	protected $_user = null;

	protected function _initDoctype()
	{
		$this->bootstrap('view');
		$view = $this->getResource('view');
		$view->doctype('XHTML1_STRICT');
	}

	protected function _initAuth()
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {
			$identity = $auth->getIdentity();

			$user = array(
						'id' => $identity->id,
						'username' => $identity->username,
						'name' => $identity->name,
						'email' => $identity->email,
						'emailsender' => $identity->emailsender,
						'emailsignature' => $identity->emailsignature,
						'admin' => $identity->admin,
						'clientid' => $identity->clientid,
						'activated' => $identity->activated,
						'deleted' => $identity->deleted
						);

			$authNamespace = new Zend_Session_Namespace('Zend_Auth');

			$lifetime = $_SESSION['__ZF']['Zend_Auth']['ENT'] - time();
			if($lifetime < 3600) $authNamespace->setExpirationSeconds(3600);
			if($lifetime > 3600) $authNamespace->setExpirationSeconds(864000);

			Zend_Registry::set('User', $user);
		}
	}

	protected function _initDatabase()
	{
		$this->bootstrap('db');
	}

	protected function _initClient()
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {

			$clientDb = new Application_Model_DbTable_Client();
			$client = $clientDb->getClient();
			if($client['parentid']) {
				$client['modules'] = array(
						'contacts' => $client['parentid'],
						'items' => $client['parentid'],
						'sales' => $client['id'],
						'purchases' => $client['id'],
						'processes' => $client['id'],
						'statistics' => $client['id']
						);
			}
			Zend_Registry::set('Client', $client);
		}
	}

	protected function _initLocale()
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {
			//Config
			$configDb = new Application_Model_DbTable_Config();
			$config = $configDb->getConfig();
			Zend_Registry::set('Config', $config);
		}

		//Locale
		$authNamespace = new Zend_Session_Namespace('Zend_Auth');
		if(isset($authNamespace->storage->language) && $authNamespace->storage->language && file_exists(BASE_PATH.'/languages/'.$authNamespace->storage->language)) {
			$language = new Zend_Locale($authNamespace->storage->language);
		} elseif(isset($config) && file_exists(BASE_PATH.'/languages/'.$config['language'])) {
			$language = new Zend_Locale($config['language']);
		} else {
			$files = scandir(BASE_PATH.'/languages/');
			$language = new Zend_Locale($files[2]);
		}
		Zend_Registry::set('Zend_Locale', $language);

		$currency = new Zend_Currency(array(
			'locale' => $language,
			'precision' => 2
		));
		Zend_Registry::set('Zend_Currency', $currency);

		//Time zone
		if(isset($config)) {
			date_default_timezone_set($config['timezone']);
		}
	}

	protected function _initSessions() {
		$this->bootstrap('session');
	}

	protected function _initCache()
	{
		//mb_internal_encoding("UTF-8");
		$frontendOptions = array(
			'lifetime' => 86400, // cache lifetime of 24 hours
			'automatic_serialization' => true
		);

		$backendOptions = array(
			'cache_dir' => BASE_PATH . '/cache/' // Directory where to put the cache files
		);

		// getting a Zend_Cache_Core object
		$cache = Zend_Cache::factory('Core',
						'File',
						$frontendOptions,
						$backendOptions);

		Zend_Registry::set('Zend_Cache', $cache);
	}

	protected function _initPlugins() {
		$front = Zend_Controller_Front::getInstance();
		$front->registerPlugin(new Application_Plugin_Acl());
		$front->registerPlugin(new Application_Plugin_Analytics());
		$front->registerPlugin(new Application_Plugin_Client());
		$front->registerPlugin(new Application_Plugin_Translate());
	}

	protected function _initControllerHelpers() {
		Zend_Controller_Action_HelperBroker::addPath(
							APPLICATION_PATH.'/controllers/helpers',
							'Application_Controller_Action_Helper_'
							);
	}

	protected function _initShopRoutes()
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		// Get the current domain from the request (without the scheme)
		$currentDomain = $_SERVER['HTTP_HOST'];

		// Fetch all activated shops from the database
		$shopsTable = new Zend_Db_Table('shop');
		$shops = $shopsTable->fetchAll(['activated = ?' => 1]);

		$matchedShop = null;

		foreach ($shops as $shop) {
			// Extract the host part of the URL stored in the database
			$shopHost = parse_url($shop['url'], PHP_URL_HOST);

			// Normalize and compare the domain to avoid mismatches
			if ($shopHost === $currentDomain) {
				$matchedShop = $shop;
				break; // Stop looping once a match is found
			}
		}

		// If shop found, proceed with setting up the shop
		if ($matchedShop) {
			// Store shop details in the registry
			$shopData = array(
					'id' => $shop['id'],
					'url' => $shop['url'],
					'timezone' => $shop['timezone'],
					'language' => $shop['language'],
					'logo' => $shop['logo'],
					'title' => $shop['title'],
					'footer' => $shop['footer'],
					'emailsender' => $shop['emailsender'],
					'smtphost' => $shop['smtphost'],
					'smtpuser' => $shop['smtpuser'],
					'smtppass' => $shop['smtppass']
					);

			Zend_Registry::set('Shop', $shopData);

			// Route to home
			$routeHome = new Zend_Controller_Router_Route(
				'/',
				array(
					'module' => 'shops',
					'controller' => 'index',
					'action' => 'index',
					'testid' => $shop['id']
				)
			);
			$router->addRoute('shop', $routeHome);

			// Route to all others
			$routeOthers = new Zend_Controller_Router_Route(
				'*',
				array(
					'module' => 'shops',
					'controller' => 'index',
					'action' => 'index',
					'testid' => $shop['id']
				)
			);
			$router->addRoute('others', $routeOthers);

			// Fetch menu items and categories for the current shop
			$menuTable = new Zend_Db_Table('menu');
			$menuItemTable = new Zend_Db_Table('menuitem');

			// Get all menus for the current shop
			$menus = $menuTable->fetchAll(['shopid = ?' => $shop['id']]);
			$menuIds = array();
			foreach ($menus as $menu) {
				$menuIds[] = $menu['id']; // Collect menu IDs into an array
			}

			// Fetch menu items only if there are menu IDs
			if (!empty($menuIds)) {
				$menuItems = $menuItemTable->fetchAll(
					$menuItemTable->select()->where('menuid IN (?)', $menuIds)
				);
			} else {
				$menuItems = []; // No menu items if no menu IDs are present
			}

			// Get all categories for the current shop
			$categoryTable = new Zend_Db_Table('category');
			$categories = $categoryTable->fetchAll(['shopid = ?' => $shop['id']]);

			// Helper function to build the full slug path with parent-child hierarchy
			$getFullSlug = function ($item, $table) {
				$slug = $item['slug'];
				while ($item['parentid']) {
					$parentItem = $table->find($item['parentid'])->current();
					if ($parentItem) {
						$slug = $parentItem['slug'] . '/' . $slug;
						$item = $parentItem;
					} else {
						break; // Parent not found, stop.
					}
				}
				return $slug;
			};

			// Create routes for all menu items
			foreach ($menuItems as $menuItem) {
				if (!empty($menuItem['slug'])) { // Ensure slug exists
					$menuItemSlug = $getFullSlug($menuItem, $menuItemTable); // Get full slug path for the menu item
					$routeMenuItem = new Zend_Controller_Router_Route(
						$menuItemSlug,
						array(
							'module' => 'shops',
							'controller' => 'page', // Assuming 'page' controller for all menu items
							'action' => 'index',
							'id' => $menuItem['pageid']
						),
						array(
							'slug' => '[a-zA-Z0-9-]+' // Regular expression to match slugs
						)
					);
					$router->addRoute('menuitem_'.$menuItem['id'], $routeMenuItem);
				}
			}

			// Create routes for all categories
			foreach ($categories as $category) {
				if (!empty($category['slug'])) { // Ensure slug exists
					$categorySlug = $getFullSlug($category, $categoryTable); // Get full slug path for the category
					$routeCategory = new Zend_Controller_Router_Route(
						$categorySlug,
						array(
							'module' => 'shops',
							'controller' => 'category', // Assuming 'category' controller for all categories
							'action' => 'index',
							'id' => $category['id']
						),
						array(
							'slug' => '[a-zA-Z0-9-]+' // Regular expression to match slugs
						)
					);
					$router->addRoute('category_'.$category['id'], $routeCategory);
				}
			}

			// Route to tags
			$tagDb = new Zend_Db_Table('tag');
			$tags = $tagDb->fetchAll(['module = ?' => 'shops', 'controller = ?' => 'category', 'shopid = ?' => $shop['id']]);
			foreach($tags as $tag) {
				if($tag->slug) {
					$routeTag = new Zend_Controller_Router_Route(
						$tag->slug,
						array(
							'module' => 'shops',
							'controller' => 'tag',
							'action' => 'index',
							'id' => $tag->id
						),
						array(
							'slug' => '[a-zA-Z0-9-]+' // Regular expression to match tags
						)
					);
					$router->addRoute('tag_'.$tag->id, $routeTag);
				}
			}

			// Route to contact
			$contactRoutes = [
				'send' => 'contact',
				'success' => 'contact_success',
				'error' => 'contact_error',
			];

			foreach ($contactRoutes as $action => $routeName) {
				$router->addRoute($routeName, new Zend_Controller_Router_Route(
					'contact/' . $action,
					[
						'module' => 'shops',
						'controller' => 'contact',
						'action' => $action
					]
				));
			}

			// Route to sitemap
			$routeSitemap = new Zend_Controller_Router_Route(
				'sitemap.xml',
				array(
					'module' => 'shops',
					'controller' => 'sitemap',
					'action' => 'index'
				)
			);
			$router->addRoute('sitemap', $routeSitemap);

			// Get all registered routes
			//$routes = $router->getRoutes();

			//print_r($routes);

			/*// Fetch slugs from the slug table
			$slugTable = new Zend_Db_Table('slug');
			$slugs = $slugTable->fetchAll(['shopid = ?' => $shop['id']]);

			// Create routes for each slug in one step using the slug table
			foreach ($slugs as $slug) {
				$slugValue = $slug['slug'];
				$module = $slug['module'];
				$controller = $slug['controller'];
				$action = 'index';

				// Create a route for this slug
				$route = new Zend_Controller_Router_Route(
					$slugValue,
					array(
						'module' => $module,
						'controller' => $controller,
						'action' => $action,
						'slug' => $slugValue
					),
					array(
						'slug' => '[a-zA-Z0-9-]+' // Regular expression to match the slug
					)
				);
				$router->addRoute($slugValue, $route);
			}*/
		}
	}
}
