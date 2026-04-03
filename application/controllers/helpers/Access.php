<?php

class Application_Controller_Action_Helper_Access extends Zend_Controller_Action_Helper_Abstract
{
	public function lock($id, $userid, $locked = null, $lockedtime = null)
	{
		$request = $this->getRequest();
		$params = $request->getParams();
		$isAjax = $request->isXmlHttpRequest();

		if ($isAjax) {
			$this->disableView();
		}

		$class = ucfirst($params['module']) . '_Model_DbTable_' . ucfirst($params['controller']);
		$db = new $class();

		if (($locked === null) || ($lockedtime === null)) {
			$function = 'get' . ucfirst($params['controller']);
			$data = $db->$function($id);

			$locked = $data['locked'] ?? 0;
			$lockedtime = $data['lockedtime'] ?? null;
		}

		if ($this->isLocked($locked, $lockedtime, $userid)) {
			if ($isAjax) {
				return [
					'ok' => false,
					'message' => 'locked',
				];
			}

			$view = Zend_Controller_Front::getInstance()
				->getParam('bootstrap')
				->getResource('view');

			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

			$userDb = new Users_Model_DbTable_User();
			$users = $userDb->getUsers();

			$message = $view->translate('MESSAGES_ACCESS_DENIED_%s');
			$message = sprintf($message, $users[$locked] ?? $locked);

			$flashMessenger->addMessage($message);
			$redirector->gotoSimple('index', $params['controller'], $params['module']);

			return null;
		}

		$db->lock($id);

		if ($isAjax) {
			return [
				'ok' => true,
				'message' => 'locked',
			];
		}

		return null;
	}

	public function unlock($id)
	{
		$request = $this->getRequest();
		$params = $request->getParams();
		$isAjax = $request->isXmlHttpRequest();

		if ($isAjax) {
			$this->disableView();
		}

		$class = ucfirst($params['module']) . '_Model_DbTable_' . ucfirst($params['controller']);
		$db = new $class();
		$db->unlock($id);

		if ($isAjax) {
			return [
				'ok' => true,
			];
		}

		return null;
	}

	public function keepalive($id)
	{
		$request = $this->getRequest();
		$params = $request->getParams();

		$this->disableView();

		$class = ucfirst($params['module']) . '_Model_DbTable_' . ucfirst($params['controller']);
		$db = new $class();
		$db->lock($id);

		return [
			'ok' => true,
		];
	}

	public function disableView()
	{
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$layout = Zend_Controller_Action_HelperBroker::getStaticHelper('layout');

		$viewRenderer->setNoRender();
		$layout->disableLayout();
	}

	public function isLocked($locked, $lockedtime, $userid)
	{
		if ($locked && ($locked != $userid)) {
			$timeout = strtotime($lockedtime) + 300;
			$timestamp = strtotime(date('Y-m-d H:i:s'));

			return !($timeout < $timestamp);
		}

		return false;
	}
}
