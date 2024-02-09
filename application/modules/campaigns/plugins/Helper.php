<?php

class Campaigns_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'campaigns') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/campaigns/controllers/helpers',
								'Campaigns_Controller_Action_Helper_'
								);
		}
	}
}
