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
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);

		$lenght = (int)$this->_getParam('lenght', 12);
		if(!in_array($lenght, [12, 24, 60], true)) $lenght = 12;

		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$options['categories'] = (new Application_Model_DbTable_Category())->getCategories('contact');
		$params = $this->_helper->Params->getParams($toolbar, $options);
		$toolbar->setValue('lenght', $lenght);

		$chart = new Statistics_Model_Turnover();
		list($turnoverList, $turnoverTotal) = $chart->createCharts($lenght, 1000, 400, $this->view->translate("STATISTICS_UNCATEGORIZED"), $this->view->translate("STATISTICS_NO_DATA"), $params, $options);

		// Aggregate monthly and yearly turnover
		$monthlyTurnover = [];
		$yearlyTurnover = [];
		$fields = ['invoicesQuantity', 'invoicesSubtotal', 'creditnotesQuantity', 'creditnotesSubtotal'];

		foreach($turnoverList as $turnover) {
			$month = $turnover['month'];
			$year = substr($month, 0, 4);

			foreach($fields as $field) {
				$value = $turnover[$field] ?? 0;
				$monthlyTurnover[$month][$field] = $value;
				$yearlyTurnover[$year][$field] = ($yearlyTurnover[$year][$field] ?? 0) + $value;
			}
		}

		// Calculate monthly averages and net turnover
		foreach($monthlyTurnover as &$data) {
			$data['invoicesAverage'] = $data['invoicesQuantity'] ? $data['invoicesSubtotal'] / $data['invoicesQuantity'] : 0;
			$data['creditnotesAverage'] = $data['creditnotesQuantity'] ? $data['creditnotesSubtotal'] / $data['creditnotesQuantity'] : 0;
			$data['netTurnover'] = $data['invoicesSubtotal'] - $data['creditnotesSubtotal'];
		}
		unset($data);

		// Calculate yearly averages and net turnover
		foreach($yearlyTurnover as &$data) {
			$data['invoicesAverage'] = $data['invoicesQuantity'] ? $data['invoicesSubtotal'] / $data['invoicesQuantity'] : 0;
			$data['creditnotesAverage'] = $data['creditnotesQuantity'] ? $data['creditnotesSubtotal'] / $data['creditnotesQuantity'] : 0;
			$data['netTurnover'] = $data['invoicesSubtotal'] - $data['creditnotesSubtotal'];
		}
		unset($data);

		// Formatting with currency for monthly and yearly totals
		$currency = Zend_Registry::get('Zend_Currency');
		foreach ($monthlyTurnover as $id => $value) {
			$monthlyTurnover[$id]['invoicesSubtotal'] = $currency->toCurrency($value['invoicesSubtotal']);
			$monthlyTurnover[$id]['creditnotesSubtotal'] = $currency->toCurrency($value['creditnotesSubtotal']);
			$monthlyTurnover[$id]['invoicesAverage'] = $currency->toCurrency($value['invoicesAverage']);
			$monthlyTurnover[$id]['creditnotesAverage'] = $currency->toCurrency($value['creditnotesAverage']);
			$monthlyTurnover[$id]['netTurnover'] = $currency->toCurrency($value['netTurnover']);
		}

		foreach ($yearlyTurnover as $id => $value) {
			$yearlyTurnover[$id]['invoicesSubtotal'] = $currency->toCurrency($value['invoicesSubtotal']);
			$yearlyTurnover[$id]['creditnotesSubtotal'] = $currency->toCurrency($value['creditnotesSubtotal']);
			$yearlyTurnover[$id]['invoicesAverage'] = $currency->toCurrency($value['invoicesAverage']);
			$yearlyTurnover[$id]['creditnotesAverage'] = $currency->toCurrency($value['creditnotesAverage']);
			$yearlyTurnover[$id]['netTurnover'] = $currency->toCurrency($value['netTurnover']);
		}

		// Format total values with currency
		if($turnoverTotal) $turnoverTotal['netTurnover'] = $currency->toCurrency($turnoverTotal['invoicesTotal'] - $turnoverTotal['creditnotesTotal']);
		if(isset($turnoverTotal['invoicesTotal'])) $turnoverTotal['invoicesTotal'] = $currency->toCurrency($turnoverTotal['invoicesTotal']);
		if(isset($turnoverTotal['invoicesAverage'])) $turnoverTotal['invoicesAverage'] = $currency->toCurrency($turnoverTotal['invoicesAverage']);
		if(isset($turnoverTotal['creditnotesTotal'])) $turnoverTotal['creditnotesTotal'] = $currency->toCurrency($turnoverTotal['creditnotesTotal']);
		if(isset($turnoverTotal['creditnotesAverage'])) $turnoverTotal['creditnotesAverage'] = $currency->toCurrency($turnoverTotal['creditnotesAverage']);

		$monthlyRows = [];
		foreach($monthlyTurnover as $month => $data) {
			$monthlyRows[] = ['rank' => count($monthlyRows) + 1, 'period' => $month] + $data;
		}

		$yearlyRows = [];
		foreach($yearlyTurnover as $year => $data) {
			$yearlyRows[] = ['rank' => count($yearlyRows) + 1, 'period' => $year] + $data;
		}

		$monthlyList = new Statistics_Model_List_Turnover();
		$monthlyList->setId('statistics-turnover-monthly')->setView($this->view)->setModule('statistics')->setController('turnover')->setItems($monthlyRows)->setSelectable(false);

		$yearlyList = new Statistics_Model_List_Turnover();
		$yearlyList->setId('statistics-turnover-yearly')->setView($this->view)->setModule('statistics')->setController('turnover')->setItems($yearlyRows)->setSelectable(false);
		$yearlyList->setPeriodLabel('STATISTICS_TURNOVER_YEAR');

		if($turnoverTotal) {
			$footer = [
				'invoicesQuantity' => $turnoverTotal['invoicesTotalQuantity'],
				'invoicesSubtotal' => $turnoverTotal['invoicesTotal'],
				'invoicesAverage' => $turnoverTotal['invoicesAverage'],
				'creditnotesQuantity' => $turnoverTotal['creditnotesTotalQuantity'],
				'creditnotesSubtotal' => $turnoverTotal['creditnotesTotal'],
				'creditnotesAverage' => $turnoverTotal['creditnotesAverage'],
				'netTurnover' => $turnoverTotal['netTurnover'],
			];

			$monthlyList->setFooter($footer);
			$yearlyList->setFooter($footer);
		}

		$this->view->lenght = $lenght;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->monthlyTurnover = $monthlyTurnover;
		$this->view->yearlyTurnover = $yearlyTurnover;
		$this->view->monthlyList = $monthlyList;
		$this->view->yearlyList = $yearlyList;
		$this->view->turnoverTotal = $turnoverTotal;
		$this->view->chartImages = $chart->getChartImages();
		$this->view->messages = $this->_flashMessenger->getMessages();
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
