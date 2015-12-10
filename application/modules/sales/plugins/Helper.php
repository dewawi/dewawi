<?php

class Sales_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'sales') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/sales/controllers/helpers',
								'Sales_Controller_Action_Helper_'
								);
		}
	}
}
