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

class Statistics_Model_Turnover
{
	public function createCharts($lenght, $width = 1000, $height = 400, $statisticsUncategorized, $statisticsNoData, $params, $options, $startMonth = null)
	{
		//print_r($params);
		//print_r($options);
		$currentYear = date('Y');
		$currentMonth = date('m');
		$startYear = date('Y', strtotime('-'.($lenght-1).' month'));
		if(!$startMonth) $startMonth = date('m', strtotime('-'.($lenght-1).' month'));

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$turnover = array();
		$turnoverCategories = array();

		$turnoverList = array();
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					//Get invoices
					$invoices = $this->fetchData($invoicesDb, 'invoice', $year, $month, $ym, $client, $params, $options);

					//Get credit notes
					$creditnotes = $this->fetchData($creditnotesDb, 'creditnote', $year, $month, $ym, $client, $params, $options);

					$turnover[$year.$ym] = 0;
					$turnoverCategories[0][$year.$ym] = 0;

					//Calculate invoices
					foreach($invoices as $invoice) {
						if(isset($turnoverList[$year.$ym]['invoicesQuantity'])) {
							$turnoverList[$year.$ym]['invoicesQuantity'] += 1;
							$turnoverList[$year.$ym]['invoicesSubtotal'] += $invoice->subtotal;
						} else {
							$turnoverList[$year.$ym]['invoicesQuantity'] = 1;
							$turnoverList[$year.$ym]['invoicesSubtotal'] = $invoice->subtotal;
							$turnoverList[$year.$ym]['month'] = $year.'/'.$ym;
						}

						$turnover[$year.$ym] += $invoice->subtotal;
						if(isset($turnoverCategories[$invoice->catid][$year.$ym])) {
							$turnoverCategories[$invoice->catid][$year.$ym] += $invoice->subtotal;
						} else {
							$turnoverCategories[$invoice->catid][$year.$ym] = $invoice->subtotal;
						}
						if($invoice->prepayment) {
							$turnover[$year.$ym] -= ($invoice->prepayment/1.19); //TODO
							$turnoverList[$year.$ym]['invoicesSubtotal'] -= ($invoice->prepayment/1.19); //TODO
							$turnoverCategories[$invoice->catid][$year.$ym] -= ($invoice->prepayment/1.19); //TODO
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						if(isset($turnoverList[$year.$ym]['creditnotesQuantity'])) {
							$turnoverList[$year.$ym]['creditnotesQuantity'] += 1;
							$turnoverList[$year.$ym]['creditnotesSubtotal'] += $creditnote->subtotal;
						} else {
							$turnoverList[$year.$ym]['creditnotesQuantity'] = 1;
							$turnoverList[$year.$ym]['creditnotesSubtotal'] = 0;
							$turnoverList[$year.$ym]['month'] = $year.'/'.$ym;
						}

						$turnover[$year.$ym] -= $creditnote->subtotal;
						if(isset($turnoverCategories[$creditnote->catid][$year.$ym])) {
							$turnoverCategories[$creditnote->catid][$year.$ym] += $creditnote->subtotal;
						} else {
							$turnoverCategories[$creditnote->catid][$year.$ym] = $creditnote->subtotal;
						}
					}

					//Calculate categories
					foreach($options['categories'] as $id => $category) {
						if(isset($turnoverCategories[$id][$year.$ym])) {
							$turnoverCategories[$id][$year.$ym] = round($turnoverCategories[$id][$year.$ym]);
						} else {
							$turnoverCategories[$id][$year.$ym] = 0;
						}
					}

					$turnover[$year.$ym] = round($turnover[$year.$ym]);
					$turnoverCategories[0][$year.$ym] = round($turnoverCategories[0][$year.$ym]);

					$dataDb = 'total:'.$turnover[$year.$ym].';';
					foreach($turnoverCategories as $key => $value) {
						if(isset($value[$year.$ym])) $dataDb .= $key.':'.$value[$year.$ym].';';
					}
					//$archiveDb->addArchive($year.$ym, $dataDb, $client['id']);
					$months[$year.$ym] = $year.'/'.$ym;
				}
			}
			//Begin from first month at the end of the year
			$startMonth = 1;
		}

		//Merge subcategories to main categories
		foreach($turnoverCategories as $id => $values) {
			if(isset($options['categories'][$id]['childs']) && ($id != $params['catid'])) {
				foreach($options['categories'][$id]['childs'] as $childId) {
					foreach($values as $month => $value) {
						if(isset($turnoverCategories[$id][$month])) {
							$turnoverCategories[$id][$month] += $turnoverCategories[$childId][$month];
						} else {
							$turnoverCategories[$id][$month] = $turnoverCategories[$childId][$month];
						}
					}
					unset($turnoverCategories[$childId]);
				}
			}
		}

		//Remove empty arrays
		foreach($turnoverCategories as $key => $values) {
			if(!array_sum($values)) unset($turnoverCategories[$key]);
		}

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
			$chartTurnover->myData->setSerieProperties("Values",["Ticks" => 5]);
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

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if(!file_exists(BASE_PATH.'/cache/chart/'.$url)) {
				mkdir(BASE_PATH.'/cache/chart/'.$url, 0777, true);
			}
			$chartTurnover->Render(BASE_PATH.'/cache/chart/'.$url.'/turnover-'.$width.'-'.$height.'.png');

			/* Create the pChart object */
			$chartTurnoverCategory = new pDraw($width, $height);

			// Add data in your dataset
			$turnoverCategoriesTotal = array();
			foreach($turnoverCategories as $key => $value) {
				$turnoverCategoriesTotal[$key] = array_sum($value);
			}
			arsort($turnoverCategoriesTotal);
			foreach($turnoverCategoriesTotal as $key => $value) {
				if($key && isset($options['categories'][$key])) $chartTurnoverCategory->myData->addPoints($turnoverCategories[$key], $options['categories'][$key]['title']);
			}
			if(isset($turnoverCategories[0])) {
				$chartTurnoverCategory->myData->addPoints($turnoverCategories[0], $statisticsUncategorized);
			}

			/* Populate the pData object */
			//$chartTurnoverCategory->myData->setSerieProperties("Values",["Ticks" => 5]);
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
			$chartTurnoverCategory->drawScale(array('XMargin' => 2, 'DrawSubTicks' => TRUE, 'Mode' => SCALE_MODE_ADDALL_START0, 'LabelRotation' => 45));
			$chartTurnoverCategory->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
			$chartTurnoverCategory->setFontProperties(["FontSize"=>10]);

			$settings = array();
			$pCharts->drawStackedAreaChart($settings);
			$chartTurnoverCategory->setShadow(FALSE);

			/* Write the chart legend */
			$chartTurnoverCategory->drawLegend(100, 20, array('Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_VERTICAL));

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if(!file_exists(BASE_PATH.'/cache/chart/'.$url)) {
				mkdir(BASE_PATH.'/cache/chart/'.$url, 0777, true);
			}
			$chartTurnoverCategory->Render(BASE_PATH.'/cache/chart/'.$url.'/turnover-category-'.$width.'-'.$height.'.png');
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

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if (!file_exists(BASE_PATH . '/cache/chart/' . $url)) {
				mkdir(BASE_PATH . '/cache/chart/' . $url, 0777, true);
			}
			$chartTurnover->Render(BASE_PATH . '/cache/chart/' . $url . '/turnover-' . $width . '-' . $height . '.png');

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

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if (!file_exists(BASE_PATH . '/cache/chart/' . $url)) {
				mkdir(BASE_PATH . '/cache/chart/' . $url, 0777, true);
			}
			$chartTurnoverCategory->Render(BASE_PATH . '/cache/chart/' . $url . '/turnover-category-' . $width . '-' . $height . '.png');
		}
		return array($turnoverList, $turnoverTotal);
	}

	private function fetchData($db, $type, $year, $month, $ym, $client, $params, $options)
	{
		$query = "i.state = 105";
		$query .= " AND ({$type}date >= '{$year}-{$ym}-01' AND {$type}date <= '{$year}-{$ym}-31')";
		$query .= " AND i.clientid = {$client['id']}";
		$query .= " AND c.clientid = {$client['id']}";
		$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCategory($query, $params['catid'], $options['categories'], 'c');
		if($params['country']) {
			$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCountry($query, $params['country'], $options['countries'], 'i');
		}

		$data = $db->fetchAll(
			$db->select()
				->from(array('i' => $type))
				->join(array('c' => 'contact'), "i.contactid = c.contactid", array('id AS cid', 'catid', 'name1'))
				->where($query ? $query : 1)
				->setIntegrityCheck(false)
		);

		return $data;
	}
}
