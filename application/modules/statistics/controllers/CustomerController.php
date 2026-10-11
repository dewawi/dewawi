<?php

class Statistics_CustomerController extends DEEC_Controller_Action
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
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);

		$lenght = (int)$this->_getParam('lenght', 12);
		if(!in_array($lenght, [12, 24, 60], true)) $lenght = 12;

		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$options['categories'] = (new Application_Model_DbTable_Category())->getCategories('contact');
		$params = $this->_helper->Params->getParams($toolbar, $options);
		$toolbar->setValue('lenght', $lenght);

		$chart = new Statistics_Model_Customer();
		$customerList = $chart->createCharts($lenght, 1000, 600, $this->view->translate("STATISTICS_UNCATEGORIZED"), $this->view->translate("STATISTICS_NO_DATA"), $params, $options);

		foreach($customerList as $i => $customer) $customerList[$i]['rank'] = $i + 1;

		$list = new Statistics_Model_List_Customers();
		$list->setId('statistics-customer')->setView($this->view)->setModule('statistics')->setController('customer')->setItems($customerList)->setSelectable(false);

		if($customerList) $list->setFooter([
			'subtotal' => $customerList[0]['total'],
		]);

		$this->view->lenght = $lenght;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->customerList = $customerList;
		$this->view->customerListTable = $list;
		$this->view->chartImages = $chart->getChartImages();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	protected function buildIndexView(): void
	{
		$this->indexAction();
		$this->exportList = $this->view->customerListTable;
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();
		$this->indexAction();
	}

	public function toolbarAction()
	{
		//$this->_helper->getHelper('layout')->disableLayout();
		$this->view->action = $this->_getParam('act', null);
		$this->view->controller = $this->_getParam('cont', null);
		$this->view->state = $this->_getParam('state', null);
	}
}
