<?php

class Admin_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'admin') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/admin/controllers/helpers',
								'Admin_Controller_Action_Helper_'
								);
		}
	}
}
