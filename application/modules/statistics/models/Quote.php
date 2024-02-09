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

class Statistics_Model_Quote
{
	public function createCharts($lenght, $width = 1000, $height = 600, $statisticsUncategorized, $params, $options)
	{
		//print_r($params);
		//print_r($options);
		$currentYear = date('Y');
		$currentMonth = date('m');
		$startYear = date('Y', strtotime('-'.($lenght-1).' month'));
		$startMonth = date('m', strtotime('-'.($lenght-1).' month'));

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$quotesDb = new Sales_Model_DbTable_Quote();

		$customerList = array();
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					//Get quotes
					$quotes = $this->fetchData($quotesDb, 'quote', $year, $month, $ym, $client, $params, $options);

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

		function sortBySubtotal($a, $b) {
			if ($a['subtotal'] > $b['subtotal']) {
				return -1;
			} elseif ($a['subtotal'] < $b['subtotal']) {
				return 1;
			}
			return 0;
		}

		if(count($customerList)) {
			usort($customerList, 'sortBySubtotal');

			$i = 0;
			$total = 0;
			$customerName = array();
			$customerTurnover = array();
			foreach($customerList as $id => $customer) {
				$total += $customer['subtotal'];
				$customerList[$id]['total'] = $total;
				if($i < 15) {
					array_push($customerName, substr($customer['name1'], 0, 19));
					array_push($customerTurnover, round($customer['subtotal']));
				}
				$customerList[$id]['total'] += $customer['subtotal'];
				++$i;
			}

			$currency = Zend_Registry::get('Zend_Currency');
			foreach($customerList as $id => $customer) {
				if($customer['subtotal'] > 0) $customerList[$id]['share'] = round($customer['subtotal']/$total*100, 2);
				else $customerList[$id]['share'] = 0;
				$customerList[$id]['total'] = $currency->toCurrency($total);
				$customerList[$id]['subtotal'] = $currency->toCurrency($customer['subtotal']);
			}

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

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if(!file_exists(BASE_PATH.'/cache/chart/'.$url)) {
				mkdir(BASE_PATH.'/cache/chart/'.$url, 0777, true);
			}
			$chartQuote->Render(BASE_PATH.'/cache/chart/'.$url.'/quote-'.$width.'-'.$height.'.png');
		}
		return $customerList;
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
