<?php

class Ebiztrader_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'ebiztrader') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/ebiztrader/controllers/helpers',
								'Ebiztrader_Controller_Action_Helper_'
								);
		}
	}
}
