<?php

class Purchases_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'purchases') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/purchases/controllers/helpers',
								'Purchases_Controller_Action_Helper_'
								);
		}
	}
}
