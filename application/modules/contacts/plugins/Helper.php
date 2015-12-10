<?php

class Contacts_Plugin_Helper extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if($request->module == 'contacts') {
			Zend_Controller_Action_HelperBroker::addPath(
								APPLICATION_PATH.'/modules/contacts/controllers/helpers',
								'Contacts_Controller_Action_Helper_'
								);
		}
	}
}
