<?php

class Statistics_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'statistics') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/statistics/controllers/helpers',
								'Statistics_Controller_Action_Helper_'
								);
		}
	}
}
