<?php

class Statistics_TurnoverController extends Zend_Controller_Action
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

		$chart = new Statistics_Model_Turnover();
		list($turnoverList, $turnoverTotal) = $chart->createCharts($lenght, 1000, 400, $this->view->translate("STATISTICS_UNCATEGORIZED"), $this->view->translate("STATISTICS_NO_DATA"), $params, $options);

		// Array to group data by month
		$monthlyTurnover = [];

		// Loop through the monthly turnovers
		foreach ($turnoverList as $turnover) {
			// Extract the month from the "month" field
			$month = $turnover['month'];
			
			// Initialize if the month doesn't exist in the array
			if (!isset($monthlyTurnover[$month])) {
				$monthlyTurnover[$month]['invoicesQuantity'] = 0;
				$monthlyTurnover[$month]['invoicesSubtotal'] = 0;
				$monthlyTurnover[$month]['creditnotesQuantity'] = 0;
				$monthlyTurnover[$month]['creditnotesSubtotal'] = 0;
			}
			
			// Add the monthly turnover to the corresponding month
			$monthlyTurnover[$month]['invoicesQuantity'] += $turnover['invoicesQuantity'];
			$monthlyTurnover[$month]['invoicesSubtotal'] += $turnover['invoicesSubtotal'];
			$monthlyTurnover[$month]['creditnotesQuantity'] += $turnover['creditnotesQuantity'];
			$monthlyTurnover[$month]['creditnotesSubtotal'] += $turnover['creditnotesSubtotal'];
		}

		// Calculate averages for each month
		foreach ($monthlyTurnover as $month => &$data) {
			$data['invoicesAverage'] = $data['invoicesQuantity'] ? $data['invoicesSubtotal'] / $data['invoicesQuantity'] : 0;
			$data['creditnotesAverage'] = $data['creditnotesQuantity'] ? $data['creditnotesSubtotal'] / $data['creditnotesQuantity'] : 0;
		}

		// Array to group data by year
		$yearlyTurnover = [];

		// Loop through the monthly turnovers
		foreach ($turnoverList as $turnover) {
			// Extract the year from the "month" field
			$year = substr($turnover['month'], 0, 4);
			
			// Initialize if the year doesn't exist in the array
			if (!isset($yearlyTurnover[$year])) {
				$yearlyTurnover[$year]['invoicesQuantity'] = 0;
				$yearlyTurnover[$year]['invoicesSubtotal'] = 0;
				$yearlyTurnover[$year]['creditnotesQuantity'] = 0;
				$yearlyTurnover[$year]['creditnotesSubtotal'] = 0;
			}
			
			// Add the monthly turnover to the corresponding year
			$yearlyTurnover[$year]['invoicesQuantity'] += $turnover['invoicesQuantity'];
			$yearlyTurnover[$year]['invoicesSubtotal'] += $turnover['invoicesSubtotal'];
			$yearlyTurnover[$year]['creditnotesQuantity'] += $turnover['creditnotesQuantity'];
			$yearlyTurnover[$year]['creditnotesSubtotal'] += $turnover['creditnotesSubtotal'];
		}

		// Calculate averages for each year
		foreach ($yearlyTurnover as $year => &$data) {
			$data['invoicesAverage'] = $data['invoicesQuantity'] ? $data['invoicesSubtotal'] / $data['invoicesQuantity'] : 0;
			$data['creditnotesAverage'] = $data['creditnotesQuantity'] ? $data['creditnotesSubtotal'] / $data['creditnotesQuantity'] : 0;
		}

		// Formatting with currency for monthly and yearly totals
		$currency = Zend_Registry::get('Zend_Currency');
		foreach ($monthlyTurnover as $id => $value) {
			$monthlyTurnover[$id]['invoicesSubtotal'] = $currency->toCurrency($value['invoicesSubtotal']);
			$monthlyTurnover[$id]['creditnotesSubtotal'] = $currency->toCurrency($value['creditnotesSubtotal']);
			$monthlyTurnover[$id]['invoicesAverage'] = $currency->toCurrency($value['invoicesAverage']);
			$monthlyTurnover[$id]['creditnotesAverage'] = $currency->toCurrency($value['creditnotesAverage']);
		}

		foreach ($yearlyTurnover as $id => $value) {
			$yearlyTurnover[$id]['invoicesSubtotal'] = $currency->toCurrency($value['invoicesSubtotal']);
			$yearlyTurnover[$id]['creditnotesSubtotal'] = $currency->toCurrency($value['creditnotesSubtotal']);
			$yearlyTurnover[$id]['invoicesAverage'] = $currency->toCurrency($value['invoicesAverage']);
			$yearlyTurnover[$id]['creditnotesAverage'] = $currency->toCurrency($value['creditnotesAverage']);
		}

		// Format total values with currency
		$turnoverTotal['invoicesTotal'] = $currency->toCurrency($turnoverTotal['invoicesTotal']);
		$turnoverTotal['invoicesAverage'] = $currency->toCurrency($turnoverTotal['invoicesAverage']);
		$turnoverTotal['creditnotesTotal'] = $currency->toCurrency($turnoverTotal['creditnotesTotal']);
		$turnoverTotal['creditnotesAverage'] = $currency->toCurrency($turnoverTotal['creditnotesAverage']);

		$this->view->lenght = $lenght;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->monthlyTurnover = $monthlyTurnover;
		$this->view->yearlyTurnover = $yearlyTurnover;
		$this->view->turnoverTotal = $turnoverTotal;
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

		$chart = new Statistics_Model_Turnover();
		$chart->createCharts($lenght, 1000, 400, $this->view->translate("STATISTICS_UNCATEGORIZED"), $this->view->translate("STATISTICS_NO_DATA"), $params, $options);

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
