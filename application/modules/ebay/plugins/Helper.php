<?php

class Ebay_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'ebay') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/ebay/controllers/helpers',
								'Ebay_Controller_Action_Helper_'
								);
		}
	}
}
