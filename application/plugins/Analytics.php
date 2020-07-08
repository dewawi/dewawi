<?php

class Application_Plugin_Analytics extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {
		    $configDb = new Application_Model_DbTable_Config();
		    $config = $configDb->getConfig();

			$view = Zend_Controller_Front::getInstance()
                            ->getParam('bootstrap')
                            ->getResource('view');

            if(isset($config['analytics'])) $view->analytics = $config['analytics'];
		}
	}
}
