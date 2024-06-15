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

	/*protected function _initRoutes()
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		// Fetch routes from the database
		$db = Zend_Db_Table::getDefaultAdapter();
		$routesTable = new Zend_Db_Table('routes');
		$routes = $routesTable->fetchAll();

		// Add each route to the router
		foreach ($routes as $routeData) {
			$route = new Zend_Controller_Router_Route(
				$routeData['pattern'],
				array(
					'module'	 => $routeData['module'],
					'controller' => $routeData['controller'],
					'action'	 => $routeData['action']
				)
			);

			$router->addRoute('custom_' . $routeData['id'], $route);
		}
	}*/

	protected function _initRoutes()
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		// Fetch shops from the database
		$shopsTable = new Zend_Db_Table('shop');
		$shops = $shopsTable->fetchAll(['activated = ?' => 1]);

		// Check if the request domain matches the specific domain
		foreach($shops as $shop) {
			// Extract the domain from the URL
			$parsedUrl = parse_url($shop['url']);
			$domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

			if($_SERVER['HTTP_HOST'] === $domain) {
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
						'module'	 => 'shops',
						'controller' => 'index',
						'action'	 => 'index',
						'testid'	 => $shop['id']
					)
				);
				$router->addRoute('shop', $routeHome);

				$menuDb = new Zend_Db_Table('menu');
				$menus = $menuDb->fetchAll();

				// Route to pages
				$menuitemsTable = new Zend_Db_Table('menuitem');
				$menuitems = $menuitemsTable->fetchAll();
				foreach($menuitems as $menuitem) {
					if($menuitem->slug) {
						$routePage = new Zend_Controller_Router_Route(
							$menuitem->slug,
							array(
								'module'	 => 'shops',
								'controller' => 'page',
								'action'	 => 'index',
								'slug'	 => $menuitem->slug
							),
							array(
								'slug' => '[a-zA-Z0-9-]+' // Regular expression to match category slug
							)
						);
						$router->addRoute($menuitem->slug, $routePage);
					}
				}

				// Route to categories
				$categoryDb = new Zend_Db_Table('category');
				$categories = $categoryDb->fetchAll(['shopid = ?' => $shop['id']]);
				foreach($categories as $category) {
					if($category->slug) {
						$routeCategory = new Zend_Controller_Router_Route(
							$category->slug,
							array(
								'module'	 => 'shops',
								'controller' => 'category',
								'action'	 => 'index',
								'slug'	 => $category->slug
							),
							array(
								'slug' => '[a-zA-Z0-9-]+' // Regular expression to match category slug
							)
						);
						$router->addRoute($category->slug, $routeCategory);
					}
				}

				// Route to items
				$itemDb = new Zend_Db_Table('item');
				$items = $itemDb->fetchAll(['shopid = ?' => $shop['id']]);
				foreach($items as $item) {
					if($item->slug) {
						$routeItem = new Zend_Controller_Router_Route(
							$item->slug,
							array(
								'module'	 => 'shops',
								'controller' => 'category',
								'action'	 => 'index',
								'slug'	 => $item->slug
							),
							array(
								'slug' => '[a-zA-Z0-9-]+' // Regular expression to match category slug
							)
						);
						$router->addRoute($item->slug, $routeItem);
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
								'module'	 => 'shops',
								'controller' => 'tag',
								'action'	 => 'index',
								'slug'	 => $tag->slug
							),
							array(
								'slug' => '[a-zA-Z0-9-]+' // Regular expression to match category slug
							)
						);
						$router->addRoute($tag->slug, $routeTag);
					}
				}

				// Route to contact
				$routeContact = new Zend_Controller_Router_Route(
					'contact/send',
					array(
						'module'	 => 'shops',
						'controller' => 'contact',
						'action'	 => 'send'
					)
				);
				$router->addRoute('contact', $routeContact);

				// Route to a specific category
				/*$routeCategory = new Zend_Controller_Router_Route(
					':category',
					array(
						'module'	 => 'shops',
						'controller' => 'category',
						'action'	 => 'index'
					),
					array(
						'category' => '[a-zA-Z0-9-]+' // Regular expression to match category slug
					)
				);*/

				/*$routeSubcategory = new Zend_Controller_Router_Route(
					':category/:subcategory',
					array(
						'module'	 => 'shops',
						'controller' => 'category',
						'action'	 => 'index'
					),
					array(
						'category' => '[a-zA-Z0-9-]+', // Regular expression to match category slug
						'subcategory' => '[a-zA-Z0-9-]+' // Regular expression to match subcategory slug
					)
				);*/

				/*$routeCategoryItem = new Zend_Controller_Router_Route(
					':category/:item',
					array(
						'module'	 => 'shops',
						'controller' => 'item',
						'action'	 => 'index'
					),
					array(
						'category' => '[a-zA-Z0-9-]+', // Regular expression to match category slug
						'item'  => '[a-zA-Z0-9-]+'  // Regular expression to match item slug
					)
				);*/

				/*$routeSubcategoryItem = new Zend_Controller_Router_Route(
					':category/:subcategory/:item',
					array(
						'module'	 => 'shops',
						'controller' => 'item',
						'action'	 => 'index'
					),
					array(
						'category' => '[a-zA-Z0-9-]+', // Regular expression to match category slug
						'subcategory' => '[a-zA-Z0-9-]+', // Regular expression to match subcategory slug
						'item'  => '[a-zA-Z0-9-]+'  // Regular expression to match item slug
					)
				);*/

				// Example of how to generate routes dynamically from menu items
				/*$menuitemsTable = new Zend_Db_Table('menuitem');
				$menuitems = $menuitemsTable->fetchAll(['shopid = ?' => $shop['id']]);
				foreach ($menuitems as $menuitem) {
					$router->addRoute(
						'shop_' . $shop['id'] . '_' . $menuitem['slug'],
						new Zend_Controller_Router_Route(
							$menuitem['url'],
							[
								'module'	 => 'shops',
								'controller' => 'page',
								'action'	 => 'index',
								'page'	   => $menuitem['slug'],
								'shopid'	=> $shop['id']
							]
						)
					);
				}*/

				//$router->addRoute('category', $routeCategory);
				//$router->addRoute('subcategory', $routeSubcategory);
				//$router->addRoute('categoryitem', $routeCategoryItem);
				//$router->addRoute('subcategoryitem', $routeSubcategoryItem);
				//print_r($router->getRoutes());

				// Set up routes for the current shop
				//$this->_setupShopRoutes($router, $shop);
			}
		}
	}

	protected function _setupShopRoutes($router, $shop)
	{
		// Route to home
		/*$router->addRoute(
			'shop_' . $shop['id'] . '_home',
			new Zend_Controller_Router_Route(
				'/',
				[
					'module'	 => 'shops',
					'controller' => 'index',
					'action'	 => 'index',
					'shopid'	=> $shop['id']
				]
			)
		);

		// Route to a specific category
		$router->addRoute(
			'shop_' . $shop['id'] . '_category',
			new Zend_Controller_Router_Route(
				':category',
				[
					'module'	 => 'shops',
					'controller' => 'category',
					'action'	 => 'index',
					'shopid'	=> $shop['id']
				],
				[
					'category' => '[a-zA-Z0-9-]+'
				]
			)
		);

		// Route to a specific item within a category
		$router->addRoute(
			'shop_' . $shop['id'] . '_category_item',
			new Zend_Controller_Router_Route(
				':category/:item',
				[
					'module'	 => 'shops',
					'controller' => 'item',
					'action'	 => 'index',
					'shopid'	=> $shop['id']
				],
				[
					'category' => '[a-zA-Z0-9-]+',
					'item'	 => '[a-zA-Z0-9-]+'
				]
			)
		);

		// Example of how to generate routes dynamically from menu items
		/*$menuitemsTable = new Zend_Db_Table('menuitem');
		$menuitems = $menuitemsTable->fetchAll(['shopid = ?' => $shop['id']]);
		foreach ($menuitems as $menuitem) {
			$router->addRoute(
				'shop_' . $shop['id'] . '_' . $menuitem['slug'],
				new Zend_Controller_Router_Route(
					$menuitem['url'],
					[
						'module'	 => 'shops',
						'controller' => 'page',
						'action'	 => 'index',
						'page'	   => $menuitem['slug'],
						'shopid'	=> $shop['id']
					]
				)
			);
		}*/
		if($shop['id'] = 100) {
			//print_r($router->getRoutes());
		}
	}
}
