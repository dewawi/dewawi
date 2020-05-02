<?php

class Application_Plugin_Auth extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		$params = $request->getParams();
		$auth = Zend_Auth::getInstance();
		Zend_Registry::set('Zend_Auth', $auth);
		if($auth->hasIdentity()) {
			$view = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('view');
			$identity = $auth->getIdentity();

			$userDb = new Users_Model_DbTable_User();
			$user = array(
                        'id' => $identity->id,
                        'username' => $identity->username,
                        'name' => $identity->name,
                        'email' => $identity->email,
                        'permissions' => $identity->permissions,
                        'clientid' => $identity->clientid
                        );

			$authNamespace = new Zend_Session_Namespace('Zend_Auth');
			$authNamespace->user = $user['username'];
			if(($_SESSION['__ZF']['Zend_Auth']['ENT'] - time()) < 3600) $authNamespace->setExpirationSeconds(3600);

			Zend_Registry::set('User', $user);
			$view->user = $user;

			$clientDb = new Application_Model_DbTable_Client();
			$client = $clientDb->getClient($user['clientid']);
			Zend_Registry::set('Client', $client); 
		} elseif($params['module'] != 'users' && $params['action'] != 'login') {
			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			if(isset($params['id']) && $params['id']) {
				$redirector->gotoSimple('login', 'user', 'users', array('url' => $params['module'].'|'.$params['controller'].'|'.$params['action'].'|'.$params['id']));
			} else {
				$redirector->gotoSimple('login', 'user', 'users', array('url' => $params['module'].'|'.$params['controller'].'|'.$params['action']));
			}
		}
	}
}
