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
            $clientDb = new Application_Model_DbTable_Client();
            $client = $clientDb->getClient($user['clientid']);

            //Client switcher
            if($user['admin']) {
                $form = new Application_Form_Client();
                $form->clientid->addMultiOptions($clientDb->getClients($client['parentid']));
                $form->clientid->setValue($user['clientid']);
                $view->clientSwitcher = $form->getElement('clientid');
            }

            //Change clientid if parentid exists
            if($client['parentid']) {
		        $params = $request->getParams();
                if(($params['module'] == 'contacts') || ($params['module'] == 'items')) {
                    $user = Zend_Registry::get('User');
                    $user['clientid'] = $client['parentid'];
                    Zend_Registry::set('User', $user);
                }
            }
		}
	}
}
