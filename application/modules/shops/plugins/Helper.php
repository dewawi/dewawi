<?php

class Shops_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'shops') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/shops/controllers/helpers',
								'Shops_Controller_Action_Helper_'
								);
		}
	}
}
