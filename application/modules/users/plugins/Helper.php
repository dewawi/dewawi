<?php

class Users_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'users') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/users/controllers/helpers',
								'Users_Controller_Action_Helper_'
								);
		}
	}
}
