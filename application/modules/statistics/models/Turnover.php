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

class Statistics_Model_Turnover extends Statistics_Model_Abstract
{
	private array $yearlyCategoryRows = [];
	private array $yearlyComparisons = [];
	private array $negativeCreditnotes = [];

	public function getYearlyCategoryRows(): array
	{
		return $this->yearlyCategoryRows;
	}

	public function getYearlyComparisons(): array
	{
		return $this->yearlyComparisons;
	}

	public function getNegativeCreditnotes(): array
	{
		return $this->negativeCreditnotes;
	}

	public function createCharts($width, $height, $statisticsUncategorized, $statisticsNoData, $params, $options)
	{
		$this->negativeCreditnotes = [];

		$period = $this->getPeriod($params);

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$invoicesByMonth = $this->fetchData($invoicesDb, 'invoice', $client, $params, $options, $period);
		$creditnotesByMonth = $this->fetchData($creditnotesDb, 'creditnote', $client, $params, $options, $period);

		$period = $this->resolvePeriod($period, $invoicesByMonth, $creditnotesByMonth);

		$this->yearlyComparisons = $this->createYearlyComparisons($invoicesByMonth, $creditnotesByMonth, $period);

		$currentYear = (int)substr($period['to'], 0, 4);
		$currentMonth = (int)substr($period['to'], 5, 2);
		$startYear = (int)substr($period['from'], 0, 4);
		$startMonth = (int)substr($period['from'], 5, 2);

		$turnover = array();
		$turnoverCategories = array();

		$categoryOptions = isset($options['categories']) && is_array($options['categories']) ? $options['categories'] : array();

		$yearlyCategoryData = [];

		$turnoverList = array();
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					$invoices = $invoicesByMonth[$year.$ym] ?? [];
					$creditnotes = $creditnotesByMonth[$year.$ym] ?? [];

					$turnover[$year.$ym] = 0;
					$turnoverCategories[0][$year.$ym] = 0;

					// Calculate invoices
					foreach($invoices as $invoice) {
						$invoiceSubtotal = $this->getInvoiceNetSubtotal($invoice);

						$this->addYearlyCategoryDocument($yearlyCategoryData, $year, $invoice, $invoiceSubtotal, false);

						if(isset($turnoverList[$year.$ym]['invoicesQuantity'])) {
							$turnoverList[$year.$ym]['invoicesQuantity'] += 1;
							$turnoverList[$year.$ym]['invoicesSubtotal'] += $invoiceSubtotal;
						} else {
							$turnoverList[$year.$ym]['invoicesQuantity'] = 1;
							$turnoverList[$year.$ym]['invoicesSubtotal'] = $invoiceSubtotal;
							$turnoverList[$year.$ym]['month'] = $year.'/'.$ym;
						}

						$turnover[$year.$ym] += $invoiceSubtotal;

						if(isset($turnoverCategories[$invoice->catid][$year.$ym])) {
							$turnoverCategories[$invoice->catid][$year.$ym] += $invoiceSubtotal;
						} else {
							$turnoverCategories[$invoice->catid][$year.$ym] = $invoiceSubtotal;
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						if((float)$creditnote->subtotal < 0) $this->negativeCreditnotes[$year] = ($this->negativeCreditnotes[$year] ?? 0) + 1;
						$this->addYearlyCategoryDocument($yearlyCategoryData, $year, $creditnote, -(float)$creditnote->subtotal, true);

						if(isset($turnoverList[$year.$ym]['creditnotesQuantity'])) {
							$turnoverList[$year.$ym]['creditnotesQuantity']++;
							$turnoverList[$year.$ym]['creditnotesSubtotal'] += $creditnote->subtotal;
						} else {
							$turnoverList[$year.$ym]['creditnotesQuantity'] = 1;
							$turnoverList[$year.$ym]['creditnotesSubtotal'] = $creditnote->subtotal;
							$turnoverList[$year.$ym]['month'] = $year.'/'.$ym;
						}

						$turnover[$year.$ym] -= $creditnote->subtotal;

						if(isset($turnoverCategories[$creditnote->catid][$year.$ym])) {
							$turnoverCategories[$creditnote->catid][$year.$ym] -= $creditnote->subtotal;
						} else {
							$turnoverCategories[$creditnote->catid][$year.$ym] = -$creditnote->subtotal;
						}
					}

					$turnover[$year.$ym] = round($turnover[$year.$ym]);
					$months[$year.$ym] = $year.'/'.$ym;
				}
			}
			//Begin from first month at the end of the year
			$startMonth = 1;
		}

		$this->prepareYearlyCategoryRows($yearlyCategoryData, $categoryOptions, $statisticsUncategorized);

		// Aggregate categories into their top-level parent.
		$groupedCategories = [];

		foreach($turnoverCategories as $id => $values) {
			$groupId = (int)$id;

			while(isset($categoryOptions[$groupId]) && !empty($categoryOptions[$groupId]['parentid'])) {
				$parentId = (int)$categoryOptions[$groupId]['parentid'];
				if(!isset($categoryOptions[$parentId])) break;
				$groupId = $parentId;
			}

			foreach($values as $month => $value) {
				$groupedCategories[$groupId][$month] = ($groupedCategories[$groupId][$month] ?? 0) + $value;
			}
		}

		$turnoverCategories = [];
		$categoryRows = [];
		$categoryTotal = 0;

		foreach($groupedCategories as $id => $values) {
			$values = array_replace(array_fill_keys(array_keys($months), 0), $values);
			if(!array_filter($values)) continue;

			$subtotal = array_sum($values);
			$turnoverCategories[$id] = $values;
			$categoryTotal += $subtotal;

			$categoryRows[] = [
				'category' => $id === 0 ? $statisticsUncategorized : ($categoryOptions[$id]['title'] ?? 'ID '.$id),
				'subtotal' => $subtotal,
			];
		}

		usort($categoryRows, static fn($a, $b) => $b['subtotal'] <=> $a['subtotal']);

		foreach($categoryRows as &$row) {
			$row['share'] = $categoryTotal != 0 ? $row['subtotal'] / $categoryTotal * 100 : 0;
		}
		unset($row);

		//Remove empty arrays
		foreach($turnoverCategories as $key => $values) {
			if(!array_sum($values)) unset($turnoverCategories[$key]);
		}

		$turnoverTotal = array();
		if(count($turnoverList)) {

			$invoicesTotal = 0;
			$creditnotesTotal = 0;
			$invoicesQuantity = 0;
			$creditnotesQuantity = 0;

			$turnoverTotal = [
				'invoicesTotal' => 0,
				'creditnotesTotal' => 0,
				'invoicesTotalQuantity' => 0,
				'creditnotesTotalQuantity' => 0,
				'invoicesAverage' => 0,
				'creditnotesAverage' => 0,
			];

			foreach ($turnoverList as $id => $value) {
				// Initialize values with default if not set
				$value['invoicesSubtotal'] = $value['invoicesSubtotal'] ?? 0;
				$value['creditnotesSubtotal'] = $value['creditnotesSubtotal'] ?? 0;
				$value['invoicesQuantity'] = $value['invoicesQuantity'] ?? 0;
				$value['creditnotesQuantity'] = $value['creditnotesQuantity'] ?? 0;

				// Accumulate totals
				$invoicesTotal += $value['invoicesSubtotal'];
				$creditnotesTotal += $value['creditnotesSubtotal'];

				// Calculate averages
				$value['invoicesAverage'] = $value['invoicesQuantity'] ? $value['invoicesSubtotal'] / $value['invoicesQuantity'] : 0;
				$value['creditnotesAverage'] = $value['creditnotesQuantity'] ? $value['creditnotesSubtotal'] / $value['creditnotesQuantity'] : 0;

				// Accumulate quantities
				$invoicesQuantity += $value['invoicesQuantity'];
				$creditnotesQuantity += $value['creditnotesQuantity'];

				// Update turnover list with calculated totals and averages
				$value['invoicesTotal'] = $invoicesTotal;
				$value['creditnotesTotal'] = $creditnotesTotal;
				$value['invoicesTotalQuantity'] = $invoicesQuantity;
				$value['creditnotesTotalQuantity'] = $creditnotesQuantity;

				// Assign back the modified value
				$turnoverList[$id] = $value;
			}

			// Store overall totals and averages in turnoverTotal
			$turnoverTotal['invoicesTotal'] = $invoicesTotal;
			$turnoverTotal['creditnotesTotal'] = $creditnotesTotal;
			$turnoverTotal['invoicesTotalQuantity'] = $invoicesQuantity;
			$turnoverTotal['creditnotesTotalQuantity'] = $creditnotesQuantity;

			// Calculate overall averages if there are any quantities
			$turnoverTotal['invoicesAverage'] = $invoicesQuantity ? $invoicesTotal / $invoicesQuantity : 0;
			$turnoverTotal['creditnotesAverage'] = $creditnotesQuantity ? $creditnotesTotal / $creditnotesQuantity : 0;

			/* Create the pChart object */
			$chartTurnover = new pDraw($width, $height);

			/* Populate the pData object */
			$chartTurnover->myData->addPoints($turnover,"Values");
			//$chartTurnover->myData->setSerieProperties("Values",["Ticks" => 5]);
			$chartTurnover->myData->setAxisName(0,"€ / Netto");
			$chartTurnover->myData->addPoints($months,"Labels");
			$chartTurnover->myData->setSerieDescription("Labels","Months");
			$chartTurnover->myData->setAbscissa("Labels");

			/* Write the chart title */
			$chartTurnover->setFontProperties(["FontName"=>BASE_PATH."/library/pChart/fonts/Cairo-Regular.ttf","FontSize"=>10]);

			/* Create the pCharts object */
			$pCharts = new pCharts($chartTurnover);

			/* Draw the scale and the 1st chart */
			$chartTurnover->setGraphArea(75, 20, $width-30, $height-60);
			$chartTurnover->drawFilledRectangle(0,0,$width,$height,["Color"=> new pColor(234,240,200), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);
			$chartTurnover->drawScale(["DrawSubTicks"=>TRUE, 'Mode' => SCALE_MODE_START0, 'LabelRotation' => 45]);
			$chartTurnover->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
			$chartTurnover->setFontProperties(["FontSize"=>10]);
			$settings = array('Gradient' => TRUE, 'GradientMode' => GRADIENT_EFFECT_CAN, 'DisplayPos' => LABEL_POS_INSIDE, 'DisplayValues' => TRUE, 'DisplayR' => 0, 'DisplayG' => 0, 'DisplayB' => 0, 'DisplayShadow' => TRUE, 'Surrounding' => 10);
			$pCharts->drawBarChart($settings);
			$chartTurnover->setShadow(FALSE);

			$this->chartImages['turnover'] = $chartTurnover->toBase64();

			/* Create the pChart object */
			$chartTurnoverCategory = new pDraw($width, $height);
//print_r($turnoverCategories);
			// Add data in your dataset
			$turnoverCategoriesTotal = array();
			foreach($turnoverCategories as $key => $value) {
				$turnoverCategoriesTotal[$key] = array_sum($value);
			}

			arsort($turnoverCategoriesTotal);
			foreach($turnoverCategoriesTotal as $key => $value) {
				if(!$key) continue;

				$series = 'category-'.$key;
				$chartTurnoverCategory->myData->addPoints(array_values($turnoverCategories[$key]), $series);
				$chartTurnoverCategory->myData->setSerieDescription($series, $categoryOptions[$key]['title'] ?? 'ID '.$key);
			}

			if(isset($turnoverCategories[0])) {
				$series = 'category-0';
				$chartTurnoverCategory->myData->addPoints(array_values($turnoverCategories[0]), $series);
				$chartTurnoverCategory->myData->setSerieDescription($series, $statisticsUncategorized);
			}

			/* Populate the pData object */
			$chartTurnoverCategory->myData->setAxisName(0,"€ / Netto");
			$chartTurnoverCategory->myData->addPoints($months,"Labels");
			$chartTurnoverCategory->myData->setSerieDescription("Labels","Months");
			$chartTurnoverCategory->myData->setAbscissa("Labels");

			/* Write the chart title */ 
			$chartTurnoverCategory->setFontProperties(["FontName"=>BASE_PATH."/library/pChart/fonts/Cairo-Regular.ttf","FontSize"=>10]);

			/* Create the pCharts object */
			$pCharts = new pCharts($chartTurnoverCategory);

			/* Draw the scale and the 1st chart */
			$chartTurnoverCategory->setGraphArea(75, 20, $width-30, $height-60);
			$chartTurnoverCategory->drawFilledRectangle(0,0,$width,$height,["Color"=> new pColor(234,240,200), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);
			$chartTurnoverCategory->drawScale(['XMargin' => 2, 'DrawSubTicks' => TRUE, 'Mode' => SCALE_MODE_ADDALL, 'LabelRotation' => 45]);
			$chartTurnoverCategory->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
			$chartTurnoverCategory->setFontProperties(["FontSize"=>10]);

			$settings = array();
			$pCharts->drawStackedAreaChart($settings);
			$chartTurnoverCategory->setShadow(FALSE);

			$this->chartImages['turnover-category'] = $chartTurnoverCategory->toBase64();
		} else {
			// Create an empty chart with a message
			$chartTurnover = new pDraw($width, $height);
			$chartTurnover->drawFilledRectangle(0, 0, $width, $height, [
				"Color" => new pColor(255, 255, 255), // Set background color to white
				"Dash" => TRUE,
				"DashColor" => new pColor(200, 200, 200)
			]);
			$chartTurnover->setFontProperties([
				"FontName" => BASE_PATH . "/library/pChart/fonts/Cairo-Regular.ttf",
				"FontSize" => 15
			]);
			$chartTurnover->drawText($width / 2, $height / 2, $statisticsNoData, [
				"Align" => TEXT_ALIGN_MIDDLEMIDDLE
			]);

			$this->chartImages['turnover'] = $chartTurnover->toBase64();

			$chartTurnoverCategory = new pDraw($width, $height);
			$chartTurnoverCategory->drawFilledRectangle(0, 0, $width, $height, [
				"Color" => new pColor(255, 255, 255), // Set background color to white
				"Dash" => TRUE,
				"DashColor" => new pColor(200, 200, 200)
			]);
			$chartTurnoverCategory->setFontProperties([
				"FontName" => BASE_PATH . "/library/pChart/fonts/Cairo-Regular.ttf",
				"FontSize" => 15
			]);
			$chartTurnoverCategory->drawText($width / 2, $height / 2, $statisticsNoData, [
				"Align" => TEXT_ALIGN_MIDDLEMIDDLE
			]);

			$this->chartImages['turnover-category'] = $chartTurnoverCategory->toBase64();
		}
		return [$turnoverList, $turnoverTotal, $categoryRows];
	}

	public function createYearlyChart(array $yearlyTurnover, int $width, int $height): void
	{
		if(!$yearlyTurnover) return;

		$years = array_keys($yearlyTurnover);
		$values = array_map(static fn($row) => round((float)$row['netTurnover'], 2), array_values($yearlyTurnover));

		$chart = new pDraw($width, $height);
		$chart->myData->addPoints($values, 'Values');
		$chart->myData->setAxisName(0, '€ / Netto');
		$chart->myData->setAxisProperties(0, ['Display' => AXIS_FORMAT_CUSTOM, 'Format' => static fn($value) => number_format((float)$value, 0, ',', '.')]);
		$chart->myData->addPoints($years, 'Labels');
		$chart->myData->setSerieDescription('Labels', 'Years');
		$chart->myData->setAbscissa('Labels');

		$chart->setFontProperties(['FontName' => BASE_PATH.'/library/pChart/fonts/Cairo-Regular.ttf', 'FontSize' => 10]);
		$chart->setGraphArea(75, 20, $width-30, $height-60);
		$chart->drawFilledRectangle(0, 0, $width, $height, [
			'Color' => new pColor(234, 240, 200),
			'Dash' => TRUE,
			'DashColor' => new pColor(190, 203, 107),
		]);

		$mode = min($values) < 0 ? SCALE_MODE_FLOATING : SCALE_MODE_START0;
		$chart->drawScale(['DrawSubTicks' => TRUE, 'Mode' => $mode]);

		$chart->setShadow(TRUE, ['X' => 1, 'Y' => 1, 'Color' => new pColor(0, 0, 0, 10)]);
		$pCharts = new pCharts($chart);
		$pCharts->drawBarChart(['Gradient' => TRUE, 'GradientMode' => GRADIENT_EFFECT_CAN, 'DisplayValues' => TRUE, 'DisplayPos' => LABEL_POS_INSIDE, 'DisplayColor' => new pColor(0, 0, 0), 'DisplayShadow' => FALSE]);
		$chart->setShadow(FALSE);

		$this->chartImages['turnover-yearly'] = $chart->toBase64();
	}

	private function addYearlyCategoryDocument(array &$data, int $year, $document, float $amount, bool $creditnote): void
	{
		$id = (int)$document->catid;

		if(!isset($data[$year][$id])) {
		    $data[$year][$id] = [
		        'subtotal' => 0.0,
		        'customers' => [],
		        'invoicesQuantity' => 0,
		        'creditnotesQuantity' => 0,
		    ];
		}

		$data[$year][$id]['subtotal'] += $amount;
		$data[$year][$id][$creditnote ? 'creditnotesQuantity' : 'invoicesQuantity']++;
		$data[$year][$id]['customers'][(int)$document->contactid] = true;
	}

	private function prepareYearlyCategoryRows(array $data, array $categories, string $uncategorized): void
	{
		$this->yearlyCategoryRows = [];

		foreach($data as $year => $groups) {
		    $total = array_sum(array_column($groups, 'subtotal'));

		    foreach($groups as $id => $group) {
		        $name = (int)$id === 0 ? $uncategorized : ($categories[$id]['breadcrumb'] ?? $categories[$id]['title'] ?? 'ID '.$id);

		        $this->yearlyCategoryRows[] = [
		            'year' => $year,
		            'category' => $name,
		            'customersQuantity' => count($group['customers']),
		            'invoicesQuantity' => $group['invoicesQuantity'],
		            'creditnotesQuantity' => $group['creditnotesQuantity'],
		            'subtotal' => $group['subtotal'],
		            'share' => $total != 0 ? $group['subtotal'] / $total * 100 : 0,
		        ];
		    }
		}

		usort($this->yearlyCategoryRows, static fn($a, $b) => ($b['year'] <=> $a['year']) ?: ($b['subtotal'] <=> $a['subtotal']));
	}

	private function createYearlyComparisons(array $invoicesByMonth, array $creditnotesByMonth, array $period): array
	{
		$end = min($period['to'], date('Y-m-d'));
		if($period['from'] > $end) return [];

		$documents = [];

		foreach($invoicesByMonth as $invoices) {
		    foreach($invoices as $invoice) {
		        $date = (string)$invoice->invoicedate;
		        $documents[(int)substr($date, 0, 4)][] = [
		            'day' => substr($date, 5, 5),
		            'subtotal' => $this->getInvoiceNetSubtotal($invoice),
		        ];
		    }
		}

		foreach($creditnotesByMonth as $creditnotes) {
		    foreach($creditnotes as $creditnote) {
		        $date = (string)$creditnote->creditnotedate;
		        $documents[(int)substr($date, 0, 4)][] = [
		            'day' => substr($date, 5, 5),
		            'subtotal' => -(float)$creditnote->subtotal,
		        ];
		    }
		}

		$rows = [];
		$firstYear = (int)substr($period['from'], 0, 4);
		$lastYear = (int)substr($end, 0, 4);

		for($year = $firstYear + 1; $year <= $lastYear; $year++) {
		    $previousYear = $year - 1;
		    $fromCurrent = max($period['from'], $year.'-01-01');
		    $toCurrent = min($end, $year.'-12-31');
		    $fromPrevious = max($period['from'], $previousYear.'-01-01');
		    $toPrevious = min($end, $previousYear.'-12-31');

		    if($fromCurrent > $toCurrent || $fromPrevious > $toPrevious) continue;

		    $from = max(substr($fromCurrent, 5), substr($fromPrevious, 5));
		    $to = min(substr($toCurrent, 5), substr($toPrevious, 5));
		    if($from > $to) continue;

		    $current = 0.0;
		    $previous = 0.0;

		    foreach($documents[$year] ?? [] as $document) {
		        if($document['day'] >= $from && $document['day'] <= $to) $current += $document['subtotal'];
		    }

		    foreach($documents[$previousYear] ?? [] as $document) {
		        if($document['day'] >= $from && $document['day'] <= $to) $previous += $document['subtotal'];
		    }

		    $difference = $current - $previous;

		    $rows[] = [
		        'year' => $year,
		        'period' => substr($from, 3, 2).'.'.substr($from, 0, 2).'.–'.substr($to, 3, 2).'.'.substr($to, 0, 2).'.',
		        'currentTurnover' => $current,
		        'previousTurnover' => $previous,
		        'difference' => $difference,
		        'growth' => $previous > 0 ? $difference / $previous * 100 : null,
		    ];
		}

		return $rows;
	}
}
