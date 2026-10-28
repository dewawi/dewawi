<?php

class ErrorController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		if ($this->getRequest()->getActionName() === 'forbidden') {
			return;
		}

		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		if(Zend_Registry::isRegistered('User')) {
			$this->view->user = $this->_user = Zend_Registry::get('User');
			$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();
		}
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function forbiddenAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout->disableLayout();
		$this->getResponse()
			->setHttpResponseCode(403)
			->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)
			->setHeader('Cache-Control', 'no-store', true)
			->setBody('Forbidden');
	}

	public function errorAction()
	{
		$errors = $this->_getParam('error_handler');

		if ($this->isNotFound($errors)) {
			$this->getResponse()->setHttpResponseCode(404);
			$this->view->message = 'Page not found';
		} else {
			$this->getResponse()->setHttpResponseCode(500);
			$this->view->message = 'Application error';
		}

		// Log exception, if logger available
		if ($log = $this->getLog()) {
			$log->crit($this->view->message, $errors->exception);
		}

		// conditionally display exceptions
		if ($this->getInvokeArg('displayExceptions') == true) {
			$this->view->exception = $errors->exception;
		}

		// send errors to the web server's error log file
		error_log($errors->exception->getMessage());

		$this->view->request   = $errors->request;
	}

	protected function isNotFound($errors): bool
	{
		return in_array($errors->type, [
			Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE,
			Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER,
			Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION,
		], true) || (
			$errors->exception instanceof Zend_Controller_Action_Exception
			&& (int)$errors->exception->getCode() === 404
		);
	}

	public function getLog()
	{
		$bootstrap = $this->getInvokeArg('bootstrap');
		if (!$bootstrap->hasPluginResource('Log')) {
			return false;
		}
		$log = $bootstrap->getResource('Log');
		return $log;
	}


}
