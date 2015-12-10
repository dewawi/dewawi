<?php

class Items_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'items') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/items/controllers/helpers',
								'Items_Controller_Action_Helper_'
								);
		}
	}
}
