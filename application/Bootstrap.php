<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

	protected $_user = null;

	protected function _initDoctype()
	{
		$this->bootstrap('view');
		$view = $this->getResource('view');
		$view->addScriptPath(APPLICATION_PATH . '/views/scripts');
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

			Zend_Registry::set('User', $user);
		}
	}

	protected function _initDeecAutoload()
	{
		Zend_Loader_Autoloader::getInstance()->registerNamespace('DEEC_');

		set_include_path(
			get_include_path() . PATH_SEPARATOR . realpath(APPLICATION_PATH . '/../library')
		);
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

		// Translate
		$localeCode = (string)$language;
		$tr = new DEEC_Translate($localeCode);

		$base = BASE_PATH . '/languages/' . $localeCode;

		// Always load shared keys
		if (is_dir($base . '/default')) {
			$tr->loadDir('default', $base . '/default');
		}

		Zend_Registry::set('DEEC_Translate', $tr);
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
		$front->registerPlugin(new Application_Plugin_State());
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

	protected function _initSite()
	{
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		$resolver = new DEEC_Site_Resolver();
		$siteContext = $resolver->resolveByHost($host);

		if ($siteContext) {
		    Zend_Registry::set('SiteContext', $siteContext);

		    // Legacy compatibility
		    Zend_Registry::set('Shop', $siteContext->getSite());
		}
	}

	protected function _initSiteRoutes()
	{
		if (!Zend_Registry::isRegistered('SiteContext')) {
		    return;
		}

		$siteContext = Zend_Registry::get('SiteContext');
		$router = new DEEC_Site_Router();
		$router->registerRoutes($siteContext);
	}
}
