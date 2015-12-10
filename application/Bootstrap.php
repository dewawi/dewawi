<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	protected function _initDoctype()
	{
		$this->bootstrap('view');
		$view = $this->getResource('view');
		$view->doctype('XHTML1_STRICT');
	}

	protected function _initLocale()
	{
		//Config
		$this->bootstrap('db');
		$configDb = new Application_Model_DbTable_Config();
		$config = $configDb->getConfig();

		//Locale
		$language = new Zend_Locale($config['language']);
		Zend_Registry::set('Zend_Locale', $language);

		//Date
		$phpSettings = $this->getOption('phpSettings');
		date_default_timezone_set($config['timezone']);
		$date = new Zend_Date();
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
		$acl = new Zend_Acl();

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

		$acl->allow('administrator');

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
		$front->registerPlugin(new Application_Plugin_Auth());
		$front->registerPlugin(new Application_Plugin_Translate());
	}

	protected function _initControllerHelpers() {
		Zend_Controller_Action_HelperBroker::addPath(
							APPLICATION_PATH.'/controllers/helpers',
							'Application_Controller_Action_Helper_'
							);
	}
}
