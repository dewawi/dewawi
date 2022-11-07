<?php

class Tasks_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'tasks') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/tasks/controllers/helpers',
								'Tasks_Controller_Action_Helper_'
								);
		}
	}
}
