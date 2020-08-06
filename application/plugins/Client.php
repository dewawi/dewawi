<?php

class Application_Plugin_Client extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		$auth = Zend_Auth::getInstance();
		if($auth->hasIdentity()) {
			$user = Zend_Registry::get('User');
			$view = Zend_Controller_Front::getInstance()
                            ->getParam('bootstrap')
                            ->getResource('view');

            //Get client
			$client = Zend_Registry::get('Client');

            //Client switcher
            if($user['admin']) {
                $form = new Application_Form_Client();
                $clientDb = new Application_Model_DbTable_Client();
                $form->clientid->addMultiOptions($clientDb->getClients($client['parentid']));
                $form->clientid->setValue($client['id']);
                $view->clientSwitcher = $form->getElement('clientid');
            }

            //Change clientid if parentid exists
            if($client['parentid']) {
		        $params = $request->getParams();
                if(isset($client['modules'][$params['module']])) {
                    $client = Zend_Registry::get('Client');
                    $client['id'] = $client['modules'][$params['module']];
                    Zend_Registry::set('Client', $client);
                }
                //error_log($client['id']);
                error_log($params['module']);
            }
		}
	}
}
