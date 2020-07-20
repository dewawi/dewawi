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
		Zend_Registry::set('Zend_Auth', $auth);
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

            //error_log($identity->clientid);
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
			Zend_Registry::set('Client', $client);
		}
	}

	protected function _initLocale()
	{
		//Config
		$configDb = new Application_Model_DbTable_Config();
		$config = $configDb->getConfig();
		Zend_Registry::set('Config', $config);

		//Locale
	    $authNamespace = new Zend_Session_Namespace('Zend_Auth');
	    if(isset($authNamespace->storage->language) && $authNamespace->storage->language) {
            $language = new Zend_Locale($authNamespace->storage->language);
        } else {
		    $language = new Zend_Locale($config['language']);
        }
		Zend_Registry::set('Zend_Locale', $language);
        //error_log($language->toString());

        $currency = new Zend_Currency(array(
            'locale' => $language,
            'precision' => 2
        ));
		Zend_Registry::set('Zend_Currency', $currency);

		//Time zone
		date_default_timezone_set($config['timezone']);
		//$phpSettings = $this->getOption('phpSettings');
		//$date = new Zend_Date();
		//echo $date, "\n";
		//echo $date->toString('YYYY-MM-dd HH:mm:ss'); 
		/*$date = $date->get(Zend_date::WEEKDAY).', '.$date->get(Zend_date::DAY).' '.$date->get(Zend_date::MONTH_NAME).' '.$date->get(Zend_Date::YEAR);*/
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

	protected function _initAcl()
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {
        }


		/*$acl = new Zend_Acl();

		// Add groups to the Role registry using Zend_Acl_Role
		// Guest does not inherit access controls
		$roleGuest = new Zend_Acl_Role('guest');
		$acl->addRole($roleGuest);

		// Staff inherits from guest
		$acl->addRole(new Zend_Acl_Role('staff'), $roleGuest);

		// Editor inherits from staff
		$acl->addRole(new Zend_Acl_Role('editor'), 'staff');

		// Administrator does not inherit access controls
		$acl->addRole(new Zend_Acl_Role('administrator'));

		// Guest may only view content
		$acl->allow($roleGuest, null, 'view');

		$acl->allow('staff', null, array('edit', 'submit', 'revise'));

		$acl->allow('editor', null, array('publish', 'archive', 'delete'));

		$acl->allow('administrator');*/

		/*echo $acl->isAllowed('guest', null, 'view') ?
			 "allowed" : "denied";
		// allowed
		 
		echo $acl->isAllowed('staff', null, 'publish') ?
			 "allowed" : "denied";
		// denied
		 
		echo $acl->isAllowed('staff', null, 'revise') ?
			 "allowed" : "denied";
		// allowed
		 
		echo $acl->isAllowed('editor', null, 'view') ?
			 "allowed" : "denied";
		// allowed because of inheritance from guest
		 
		echo $acl->isAllowed('editor', null, 'update') ?
			 "allowed" : "denied";
		// denied because no allow rule for 'update'
		 
		echo $acl->isAllowed('administrator', null, 'view') ?
			 "allowed" : "denied";
		// allowed because administrator is allowed all privileges
		 
		echo $acl->isAllowed('administrator') ?
			 "allowed" : "denied";
		// allowed because administrator is allowed all privileges
		 
		echo $acl->isAllowed('administrator', null, 'update') ?
			 "allowed" : "denied";
		// allowed because administrator is allowed all privileges*/
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
