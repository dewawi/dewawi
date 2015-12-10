<?php

class Processes_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'processes') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/processes/controllers/helpers',
								'Processes_Controller_Action_Helper_'
								);
		}
	}
}
