<?php

abstract class DEEC_Controller_SiteAction extends Zend_Controller_Action
{
	protected $_date = null;
	protected $_flashMessenger = null;
	protected $_siteContext = null;
	protected $_site = [];

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		$this->_siteContext = Zend_Registry::get('SiteContext');
		$this->_site = $this->_siteContext->getSite();

		$this->view->id = isset($params['id']) ? (int)$params['id'] : 0;
		$this->view->action = $params['action'] ?? '';
		$this->view->controller = $params['controller'] ?? '';
		$this->view->module = $params['module'] ?? '';
		$this->view->siteContext = $this->_siteContext;
		$this->view->site = $this->_site;
	}

	protected function assignMessages(): void
	{
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
