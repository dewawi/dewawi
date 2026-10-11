<?php

//pChart
require_once(BASE_PATH.'/library/pChart/pChart/pData.php');
require_once(BASE_PATH.'/library/pChart/pChart/pColor.php');
require_once(BASE_PATH.'/library/pChart/pChart/pDraw.php');
require_once(BASE_PATH.'/library/pChart/pChart/pCharts.php');
require_once(BASE_PATH.'/library/pChart/pChart/pColorGradient.php');
require_once(BASE_PATH.'/library/pChart/pChart/pException.php');

use pChart\pData;
use pChart\pColor;
use pChart\pDraw;
use pChart\pCharts;
use pChart\pColorGradient;
use pChart\pException;

class Statistics_Model_Customer extends Statistics_Model_Abstract
{
    private array $yearlyCustomers = [];
    private array $yearlyReport = [];

    public function getYearlyReport(): array
    {
        return $this->yearlyReport;
    }

	public function createCharts($width, $height, $statisticsUncategorized, $statisticsNoData, $params, $options)
	{
		$this->yearlyCustomers = [];
		$this->yearlyReport = [];

		$period = $this->getPeriod($params);

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$invoicesByMonth = $this->fetchData($invoicesDb, 'invoice', $client, $params, $options, $period);
		$creditnotesByMonth = $this->fetchData($creditnotesDb, 'creditnote', $client, $params, $options, $period);

		$period = $this->resolvePeriod($period, $invoicesByMonth, $creditnotesByMonth);

		$currentYear = (int)substr($period['to'], 0, 4);
		$currentMonth = (int)substr($period['to'], 5, 2);
		$startYear = (int)substr($period['from'], 0, 4);
		$startMonth = (int)substr($period['from'], 5, 2);

		$categories = $options['categories'] ?? [];

		$customerList = array();
		for($year = $startYear; $year <= $currentYear; $year++) $this->yearlyCustomers[$year] = [];
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					$invoices = $invoicesByMonth[$year.$ym] ?? [];
					$creditnotes = $creditnotesByMonth[$year.$ym] ?? [];

					// Calculate invoices
					foreach($invoices as $invoice) {
						if(isset($invoice->name1) && $invoice->invoiceid) {
							$invoiceSubtotal = $this->getInvoiceNetSubtotal($invoice);

							$this->addYearlyDocument($year, $invoice, $invoiceSubtotal, 'invoice', $categories);

							if(isset($customerList[$invoice->contactid])) {
								$customerList[$invoice->contactid]['subtotal'] += $invoiceSubtotal;
								$customerList[$invoice->contactid]['invoices'][] = $invoice->invoiceid;
							} else {
								$customerList[$invoice->contactid]['cid'] = $invoice->cid;
								$customerList[$invoice->contactid]['contactid'] = $invoice->contactid;
								$customerList[$invoice->contactid]['name1'] = $invoice->name1;
								$customerList[$invoice->contactid]['subtotal'] = $invoiceSubtotal;
								$customerList[$invoice->contactid]['invoices'] = array($invoice->invoiceid);
								$category = ($invoice->catid && isset($categories[$invoice->catid]))
									? $categories[$invoice->catid]
									: array();

								$customerList[$invoice->contactid]['ctitle'] = isset($category['title']) ? $category['title'] : '';
								$customerList[$invoice->contactid]['cfulltitle'] = isset($category['fulltitle']) ? $category['fulltitle'] : $customerList[$invoice->contactid]['ctitle'];
							}
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						if(isset($creditnote->name1) && $creditnote->creditnoteid) {
							$amount = $this->getCreditnoteNetSubtotal($creditnote);
							$this->addYearlyDocument($year, $creditnote, -$amount, 'creditnote', $categories);
							if(isset($customerList[$creditnote->contactid])) {
								$customerList[$creditnote->contactid]['subtotal'] -= $amount;
								$customerList[$creditnote->contactid]['creditnotes'][] = $creditnote->creditnoteid;
							} else {
								$customerList[$creditnote->contactid]['cid'] = $creditnote->cid;
								$customerList[$creditnote->contactid]['contactid'] = $creditnote->contactid;
								$customerList[$creditnote->contactid]['name1'] = $creditnote->name1;
								$customerList[$creditnote->contactid]['subtotal'] -= $amount;
								$customerList[$creditnote->contactid]['creditnotes'] = [$creditnote->creditnoteid];

								$category = ($creditnote->catid && isset($categories[$creditnote->catid]))
									? $categories[$creditnote->catid]
									: [];

								$customerList[$creditnote->contactid]['ctitle'] = $category['title'] ?? '';
								$customerList[$creditnote->contactid]['cfulltitle'] = $category['fulltitle'] ?? $customerList[$creditnote->contactid]['ctitle'];
							}
						}
					}
				}
			}
			//Begin from first month at the end of the year
			$startMonth = 1;
		}

		$this->prepareYearlyReport();

		if(count($customerList)) {
			list($customerList, $customerName, $customerTurnover) = $this->prepareRanking($customerList, 20);

			/* Create the pChart object */
			$chartCustomer = new pDraw($width, $height);

			/* Populate the pData object */
			$chartCustomer->myData->addPoints($customerTurnover,"Values");
			$chartCustomer->myData->setSerieProperties("Values",["Ticks" => 5]);
			$chartCustomer->myData->setAxisName(0,"€ / Netto");
			$chartCustomer->myData->addPoints($customerName,"Labels");
			$chartCustomer->myData->setSerieDescription("Labels","Months");
			$chartCustomer->myData->setAbscissa("Labels");

			/* Write the chart title */
			$chartCustomer->setFontProperties(["FontName"=>BASE_PATH."/library/pChart/fonts/Cairo-Regular.ttf","FontSize"=>10]);

			/* Create the pCharts object */
			$pCharts = new pCharts($chartCustomer);

			/* Draw the scale and the 1st chart */
			$chartCustomer->setGraphArea(75, 20, $width-30, $height-130);
			$chartCustomer->drawFilledRectangle(0,0,$width,$height,["Color"=> new pColor(234,240,200), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);
			$chartCustomer->drawScale(["DrawSubTicks"=>TRUE, 'Mode' => SCALE_MODE_START0, 'LabelRotation' => 45]);
			$chartCustomer->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
			$chartCustomer->setFontProperties(["FontSize"=>10]);
			$settings = array('Gradient' => TRUE, 'GradientMode' => GRADIENT_EFFECT_CAN, 'DisplayPos' => LABEL_POS_INSIDE, 'DisplayValues' => TRUE, 'DisplayR' => 0, 'DisplayG' => 0, 'DisplayB' => 0, 'DisplayShadow' => TRUE, 'Surrounding' => 10);
			$pCharts->drawBarChart($settings);
			$chartCustomer->setShadow(FALSE);

			$this->chartImages['customer'] = $chartCustomer->toBase64();
		}
		return $customerList;
	}

	private function addYearlyDocument(int $year, $document, float $amount, string $type, array $categories): void
	{
		$id = (int)$document->contactid;
		$number = $document->{$type.'id'};

		if(!isset($this->yearlyCustomers[$year][$id])) {
		    $category = $categories[(int)$document->catid] ?? [];

		    $this->yearlyCustomers[$year][$id] = [
		        'cid' => $document->cid,
		        'contactid' => $document->contactid,
		        'name1' => $document->name1,
		        'ctitle' => $category['title'] ?? '',
		        'cfulltitle' => $category['fulltitle'] ?? $category['breadcrumb'] ?? $category['title'] ?? '',
		        'subtotal' => 0.0,
		        'invoices' => [],
		        'creditnotes' => [],
				'daily' => [],
		    ];
		}

		$this->yearlyCustomers[$year][$id]['subtotal'] += $amount;
		$this->yearlyCustomers[$year][$id][$type === 'invoice' ? 'invoices' : 'creditnotes'][] = $number;

		$date = substr((string)$document->{$type.'date'}, 0, 10);
		$this->yearlyCustomers[$year][$id]['daily'][$date] = ($this->yearlyCustomers[$year][$id]['daily'][$date] ?? 0) + $amount;
	}

	private function prepareYearlyReport(): void
	{
		foreach($this->yearlyCustomers as $year => $customers) {
		    $rows = array_values($customers);
		    usort($rows, static fn($a, $b) => $b['subtotal'] <=> $a['subtotal']);

		    $total = array_sum(array_column($rows, 'subtotal'));

		    $summary = [
		        'year' => $year,
		        'customers' => count($rows),
		        'invoices' => array_sum(array_map(static fn($row) => count($row['invoices']), $rows)),
		        'creditnotes' => array_sum(array_map(static fn($row) => count($row['creditnotes']), $rows)),
		        'subtotal' => $total,
		    ];

		    foreach([1, 5, 10, 20, 30] as $count) {
		        $topTotal = array_sum(array_column(array_slice($rows, 0, $count), 'subtotal'));
		        $summary['top'.$count] = $total > 0 ? $topTotal / $total * 100 : 0;
		    }

		    $formatted = [];

		    if($rows) {
		        [$formatted] = $this->prepareRanking($rows, 20);

		        foreach($formatted as $i => &$row) $row['rank'] = $i + 1;
		        unset($row);
		    }

		    $this->yearlyReport[$year] = [
		        'summary' => $summary,
		        'customers' => $formatted,
		        'raw' => $rows,
		    ];
		}
	}
}
