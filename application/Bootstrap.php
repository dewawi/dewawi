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
						'smtphost' => $identity->smtphost,
						'smtpuser' => $identity->smtpuser,
						'smtppass' => $identity->smtppass,
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
					'smtppass' => $shop['smtppass'],
					'clientid' => $shop['clientid']
					);

			Zend_Registry::set('Shop', $shopData);

			// Route to home
			$routeHome = new Zend_Controller_Router_Route(
				'/',
				array(
					'module' => 'shops',
					'controller' => 'index',
					'action' => 'index'
				)
			);
			$router->addRoute('shop', $routeHome);

			// Route to all others
			$routeOthers = new Zend_Controller_Router_Route(
				'*',
				array(
					'module' => 'shops',
					'controller' => 'index',
					'action' => 'index'
				)
			);
			$router->addRoute('others', $routeOthers);

			// Fetch slugs for the current shop
			$slugTable = new Zend_Db_Table('slug');
			$slugs = $slugTable->fetchAll(['shopid = ?' => $shop['id']]);

			// Organize slugs into a dictionary with entityid as the key
			$slugDict = [];
			foreach ($slugs as $slug) {
				$slugDict[$slug['entityid']] = $slug;
			}

			// Helper function to build the full slug path using entityid with parent-child hierarchy
			$getFullSlug = function ($item, $slugDict) {
				$slug = $item['slug'];
				
				// Continue while the item has a parent
				while ($item['parentid']) {
					// Find the parent item in the slugDict
					if (isset($slugDict[$item['parentid']])) {
						$parentItem = $slugDict[$item['parentid']];
						// Prepend the parent's slug to the current slug
						$slug = $parentItem['slug'] . '/' . $slug;
						$item = $parentItem;
					} else {
						break; // Parent not found, stop the loop
					}
				}
				
				return $slug;
			};

			// Create routes for all slugs
			foreach ($slugs as $slug) {
				if (!empty($slug['slug'])) { // Ensure slug exists
					$fullSlug = $getFullSlug($slug, $slugDict); // Get full slug path
					$routeSlug = new Zend_Controller_Router_Route(
						$fullSlug,
						array(
							'module' => $slug['module'],
							'controller' => $slug['controller'],
							'action' => 'index',
							'id' => $slug['entityid']
						),
						array(
							'slug' => '[a-zA-Z0-9-]+' // Regular expression to match slugs
						)
					);
					$router->addRoute($slug['controller'].'_'.$slug['entityid'], $routeSlug);
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

			// Route to shopping cart
			$routeCart = new Zend_Controller_Router_Route(
				'cart',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'index'
				)
			);
			$router->addRoute('cart', $routeCart);

			// Route add to cart
			$routeAddToCart = new Zend_Controller_Router_Route(
				'cart/add',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'add'
				)
			);
			$router->addRoute('addtocart', $routeAddToCart);

			// Route remove from cart
			$routeRemoveFromCart = new Zend_Controller_Router_Route(
				'cart/remove',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'remove'
				)
			);
			$router->addRoute('removefromcart', $routeRemoveFromCart);

			// Route update shopping cart
			$routeUpdateCart = new Zend_Controller_Router_Route(
				'cart/update',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'update'
				)
			);
			$router->addRoute('updatecart', $routeUpdateCart);

			// Route to shopping cart
			$routeClearCart = new Zend_Controller_Router_Route(
				'cart/clear',
				array(
					'module' => 'shops',
					'controller' => 'cart',
					'action' => 'clear'
				)
			);
			$router->addRoute('clearcart', $routeClearCart);

			// Route to checkout
			$routeCheckout = new Zend_Controller_Router_Route(
				'checkout',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'index'
				)
			);
			$router->addRoute('checkout', $routeCheckout);

			// Route send checkout
			$routeSendCheckout = new Zend_Controller_Router_Route(
				'checkout/send',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'send'
				)
			);
			$router->addRoute('sendcheckout', $routeSendCheckout);

			// Route success checkout
			$routeSuccessCheckout = new Zend_Controller_Router_Route(
				'checkout/success',
				array(
					'module' => 'shops',
					'controller' => 'checkout',
					'action' => 'success'
				)
			);
			$router->addRoute('successcheckout', $routeSuccessCheckout);

			// Route to product feed
			$routeFeed = new Zend_Controller_Router_Route(
				'products-de.xml',
				array(
					'module' => 'shops',
					'controller' => 'item',
					'action' => 'feed'
				)
			);
			$router->addRoute('feed', $routeFeed);

			// Route to product
			$routeProduct = new Zend_Controller_Router_Route(
				'product/:id', // Use :id as a placeholder for the dynamic segment
				array(
					'module' => 'shops',
					'controller' => 'item',
					'action' => 'index',
					'id' => null // Default value for id
				),
				array(
					'id' => '\d+' // Regex to ensure id is numeric
				)
			);
			$router->addRoute('product', $routeProduct);

			// Get all registered routes
			//$routes = $router->getRoutes();

			//print_r($routes);
		}
	}
}
