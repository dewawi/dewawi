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

class Statistics_Model_Quote extends Statistics_Model_Abstract
{
	public function createCharts($lenght, $width = 1000, $height = 600, $statisticsUncategorized, $statisticsNoData, $params, $options)
	{
		$period = $this->getPeriod((int)$lenght, $params);
		$currentYear = (int)substr($period['to'], 0, 4);
		$currentMonth = (int)substr($period['to'], 5, 2);
		$startYear = (int)substr($period['from'], 0, 4);
		$startMonth = (int)substr($period['from'], 5, 2);

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$quotesDb = new Sales_Model_DbTable_Quote();

		$quotesByMonth = $this->fetchData($quotesDb, 'quote', $client, $params, $options, $period);

		$customerList = array();
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					$quotes = $quotesByMonth[$year.$ym] ?? [];

					//Calculate quotes
					foreach($quotes as $quote) {
						if(isset($customerList[$quote->contactid])) {
							$customerList[$quote->contactid]['subtotal'] += $quote->subtotal;
							$customerList[$quote->contactid]['quantity'] += 1;
						} else {
							$customerList[$quote->contactid]['contactid'] = $quote->contactid;
							$customerList[$quote->contactid]['name1'] = $quote->name1;
							$customerList[$quote->contactid]['subtotal'] = $quote->subtotal;
							$customerList[$quote->contactid]['quantity'] = 1;
						}
					}
				}
			}
			//Begin from first month at the end of the year
			$startMonth = 1;
		}

		if(count($customerList)) {
			list($customerList, $customerName, $customerTurnover) = $this->prepareRanking($customerList, 19);

			/* Create the pChart object */
			$chartQuote = new pDraw($width, $height);

			/* Populate the pData object */
			$chartQuote->myData->addPoints($customerTurnover,"Values");
			$chartQuote->myData->setSerieProperties("Values",["Ticks" => 5]);
			$chartQuote->myData->setAxisName(0,"€ / Netto");
			$chartQuote->myData->addPoints($customerName,"Labels");
			$chartQuote->myData->setSerieDescription("Labels","Months");
			$chartQuote->myData->setAbscissa("Labels");

			/* Write the chart title */
			$chartQuote->setFontProperties(["FontName"=>BASE_PATH."/library/pChart/fonts/Cairo-Regular.ttf","FontSize"=>10]);

			/* Create the pCharts object */
			$pCharts = new pCharts($chartQuote);

			/* Draw the scale and the 1st chart */
			$chartQuote->setGraphArea(75, 20, $width-30, $height-130);
			$chartQuote->drawFilledRectangle(0,0,$width,$height,["Color"=> new pColor(234,240,200), "Dash"=>TRUE, "DashColor"=>new pColor(190,203,107)]);
			$chartQuote->drawScale(["DrawSubTicks"=>TRUE, 'Mode' => SCALE_MODE_START0, 'LabelRotation' => 45]);
			$chartQuote->setShadow(TRUE,["X"=>1,"Y"=>1,"Color"=>new pColor(0,0,0,10)]);
			$chartQuote->setFontProperties(["FontSize"=>10]);
			$settings = array('Gradient' => TRUE, 'GradientMode' => GRADIENT_EFFECT_CAN, 'DisplayPos' => LABEL_POS_INSIDE, 'DisplayValues' => TRUE, 'DisplayR' => 0, 'DisplayG' => 0, 'DisplayB' => 0, 'DisplayShadow' => TRUE, 'Surrounding' => 10);
			$pCharts->drawBarChart($settings);
			$chartQuote->setShadow(FALSE);

			$this->chartImages['quote'] = $chartQuote->toBase64();
		}
		return $customerList;
	}
}
