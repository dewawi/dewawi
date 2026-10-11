<?php

class Statistics_TurnoverController extends DEEC_Controller_Action
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

		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$options['categories'] = (new Application_Model_DbTable_Category())->getCategories('contact');
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$chart = new Statistics_Model_Turnover();
		[$turnoverList, $turnoverTotal, $categoryRows] = $chart->createCharts(1000, 400, $this->view->translate("STATISTICS_UNCATEGORIZED"), $this->view->translate("STATISTICS_NO_DATA"), $params, $options);

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
		$chart->createYearlyChart($yearlyTurnover, 1000, 400);

		$currency = Zend_Registry::get('Zend_Currency');

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

		$categoryList = new DEEC_List([
			'id' => 'statistics-turnover-categories',
			'view' => $this->view,
			'module' => 'statistics',
			'controller' => 'turnover',
			'items' => $categoryRows,
			'selectable' => false,
			'columns' => [
				[
					'name' => 'category',
					'label' => 'STATISTICS_CUSTOMER_CATEGORY',
				],
				[
					'name' => 'subtotal',
					'label' => 'STATISTICS_NET_TURNOVER',
					'type' => 'currency',
					'export_callback' => static fn($item) => number_format((float)$item['subtotal'], 2, ',', ''),
				],
				[
					'name' => 'share',
					'label' => 'STATISTICS_CUSTOMER_SHARE_PERCENT',
					'type' => 'callback',
					'callback' => static fn($item) => number_format((float)$item['share'], 2, ',', '').'%',
					'export_callback' => static fn($item) => number_format((float)$item['share'], 4, ',', ''),
				],
			],
		]);

		if($categoryRows) {
			$categoryTotal = array_sum(array_column($categoryRows, 'subtotal'));

			$categoryList->setFooter([
				'category' => $this->view->translate('STATISTICS_NET_TURNOVER'),
				'subtotal' => $currency->toCurrency($categoryTotal),
				'share' => $categoryTotal != 0 ? '100,00%' : '0,00%',
			]);
		}

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

		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->exportFilters = $params;
		$this->view->monthlyTurnover = $monthlyTurnover;
		$this->view->yearlyTurnover = $yearlyTurnover;
		$this->view->monthlyList = $monthlyList;
		$this->view->yearlyList = $yearlyList;
		$this->view->categoryRows = $categoryRows;
		$this->view->categoryList = $categoryList;
		$this->view->turnoverTotal = $turnoverTotal;
		$this->view->chartImages = $chart->getChartImages();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	protected function buildIndexView(): void
	{
		$group = (string)$this->_getParam('group', 'monthly');
		if(!in_array($group, ['monthly', 'yearly'], true)) throw new InvalidArgumentException('Invalid turnover export group.');

		$this->indexAction();
		$this->exportList = $group === 'yearly' ? $this->view->yearlyList : $this->view->monthlyList;
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();
		$this->indexAction();
	}

	public function exportAction()
	{
		$this->disableView();
		$this->indexAction();

		$file = tempnam(sys_get_temp_dir(), 'dewawi-turnover-');
		if($file === false) throw new RuntimeException('Unable to create temporary ZIP file.');

		try {
			$zip = new ZipArchive();
			if($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create ZIP archive.');

			try {
				$lists = [
					'umsatz-jaehrlich.csv' => $this->view->yearlyList,
					'umsatz-monatlich.csv' => $this->view->monthlyList,
					'umsatz-kundengruppen.csv' => $this->view->categoryList,
				];

				foreach($lists as $name => $list) {
					$stream = fopen('php://temp', 'w+');
					if($stream === false) throw new RuntimeException('Unable to create CSV stream.');

					$filters = $this->view->exportFilters;
					$daterange = $filters['daterange'] ?? 'all';

					$from = $daterange === 'all' ? 'unbegrenzt' : (string)$filters['from'];
					$to = $daterange === 'all' ? 'unbegrenzt' : (string)$filters['to'];

					$info = [
						'DEWAWI - Umsatzstatistik',
						'Erstellt am: '.date('d.m.Y H:i:s'),
						'Zeitraum: '.$from.' bis '.$to,
						'Zeitraumauswahl: '.$daterange,
						'Letzter Monat mit Belegen: '.(array_key_last($this->view->monthlyTurnover) ?? 'keine'),
						'Land (Code): '.($filters['country'] ?? '0'),
						'Kundenkategorie (ID): '.($filters['catid'] ?? 'all'),
						'Belegstatus: 105 (abgeschlossen)',
						'Geloeschte und stornierte Belege: ausgeschlossen',
						'Nettoumsatz: Rechnungsnetto abzueglich Gutschriftennetto',
						'Hinweis: Das laufende Geschaeftsjahr ist nicht abgeschlossen.',
					];

					if(!$zip->addFromString('export-info.txt', implode("\n", $info)."\n")) {
						throw new RuntimeException('Unable to add export metadata.');
					}

					try {
						$list->writeCsv($stream);
						rewind($stream);
						$csv = stream_get_contents($stream);
					} finally {
						fclose($stream);
					}

					if($csv === false || !$zip->addFromString($name, $csv)) throw new RuntimeException('Unable to add CSV to ZIP archive.');
				}
			} finally {
				$closed = $zip->close();
			}

			if(!$closed) throw new RuntimeException('Unable to finalize ZIP archive.');

			$content = file_get_contents($file);
			if($content === false) throw new RuntimeException('Unable to read ZIP archive.');

			$response = $this->getResponse();
			$response->setHeader('Content-Type', 'application/zip', true);
			$response->setHeader('Content-Disposition', 'attachment; filename="statistics-turnover-'.date('Ymd-His').'.zip"', true);
			$response->setHeader('Cache-Control', 'private, no-store', true);
			$response->setHeader('X-Content-Type-Options', 'nosniff', true);
			$response->setBody($content);
		} finally {
			unlink($file);
		}
	}

	public function toolbarAction()
	{
		//$this->_helper->getHelper('layout')->disableLayout();
		$this->view->action = $this->_getParam('act', null);
		$this->view->controller = $this->_getParam('cont', null);
		$this->view->state = $this->_getParam('state', null);
	}
}
