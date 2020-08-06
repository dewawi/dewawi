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
                        'admin' => $identity->admin,
                        'permissions' => $identity->permissions,
                        'clientid' => $identity->clientid
                        );

			$authNamespace = new Zend_Session_Namespace('Zend_Auth');
			$authNamespace->user = $user['username'];
			if(($_SESSION['__ZF']['Zend_Auth']['ENT'] - time()) < 3600) $authNamespace->setExpirationSeconds(3600);

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
        if(isset($authNamespace->storage->language) && $authNamespace->storage->language) {
            $language = new Zend_Locale($authNamespace->storage->language);
        } elseif(isset($config)) {
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
}
