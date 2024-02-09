<?php

class Statistics_QuoteController extends Zend_Controller_Action
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
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		$lenght = $this->_getParam('lenght', 25);

		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$chart = new Statistics_Model_Quote();
		$customerList = $chart->createCharts($lenght, 1000, 600, $this->view->translate("STATISTICS_UNCATEGORIZED"), $params, $options);

		$this->view->lenght = $lenght;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->customerList = $customerList;
		$this->view->subfolder = $this->_helper->Directory->getShortUrl();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$lenght = $this->_getParam('lenght', 25);

		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$chart = new Statistics_Model_Quote();
		$chart->createCharts($lenght, 1000, 400, $this->view->translate("STATISTICS_UNCATEGORIZED"), $params, $options);

		$this->view->lenght = $lenght;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function toolbarAction()
	{
		//$this->_helper->getHelper('layout')->disableLayout();
		$this->view->action = $this->_getParam('act', null);
		$this->view->controller = $this->_getParam('cont', null);
		$this->view->state = $this->_getParam('state', null);
	}
}
