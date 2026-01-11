<?php

class Application_Controller_Action_Helper_Access extends Zend_Controller_Action_Helper_Abstract
{
	public function lock($id, $userid, $locked = null, $lockedtime = null) {
		$request = $this->getRequest();
		$params = $request->getParams();

		// ajax detection
		if($isAjax = $request->isXmlHttpRequest()) {
			$this->disableView();
			$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		}

		$class = ucfirst($params['module']).'_Model_DbTable_'.ucfirst($params['controller']);
		$db = new $class();
		if(($locked === null) || ($lockedtime === null)) {
			$function = 'get'.ucfirst($params['controller']);
			$data = $db->$function($id);
			$locked = $data['locked'];
			$lockedtime = $data['lockedtime'];
		}
		if($this->isLocked($locked, $lockedtime, $userid)) {
			$view = Zend_Controller_Front::getInstance()
							->getParam('bootstrap')
							->getResource('view');

			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

			$userDb = new Users_Model_DbTable_User();
			$users = $userDb->getUsers();

			$message = $view->translate('MESSAGES_ACCESS_DENIED_%s');
			$message = sprintf($message, $users[$locked]);

			if ($isAjax) {
				return $json->sendJson([
					'ok' => false,
					'message' => 'locked'
				]);
			} else {
				$flashMessenger->addMessage($message);
				$redirector->gotoSimple('index', $params['controller'], $params['module']);
			}
		} else {
			$db->lock($id);

			/*if ($isAjax) {
				return $json->sendJson([
					'ok' => true,
					'message' => true
				]);
			}*/
		}
	}

	public function unlock($id) {
		$this->disableView();
		$request = $this->getRequest();
		$params = $request->getParams();
		$class = ucfirst($params['module']).'_Model_DbTable_'.ucfirst($params['controller']);
		$db = new $class();
		$db->unlock($id);
	}

	public function keepalive($id) {
		$this->disableView();
		$request = $this->getRequest();
		$params = $request->getParams();
		$class = ucfirst($params['module']).'_Model_DbTable_'.ucfirst($params['controller']);
		$db = new $class();
		$db->lock($id);
	}

	public function disableView() {
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$layout = Zend_Controller_Action_HelperBroker::getStaticHelper('layout');
		header('Content-type: application/json');
		$viewRenderer->setNoRender();
		$layout->disableLayout();
	}

	public function isLocked($locked, $lockedtime, $userid) {
		if($locked && ($locked != $userid)) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime(date('Y-m-d H:i:s'));
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}
}
