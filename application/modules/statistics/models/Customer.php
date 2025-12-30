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

class Statistics_Model_Customer
{
	public function createCharts($lenght, $width = 1000, $height = 600, $statisticsUncategorized, $statisticsNoData, $params, $options)
	{
		//print_r($params);
		//print_r($options);
		$currentYear = date('Y');
		$currentMonth = date('m');
		$startYear = date('Y', strtotime('-'.($lenght-1).' month'));
		$startMonth = date('m', strtotime('-'.($lenght-1).' month'));

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$customerList = array();
		for($year = $startYear; $year <= $currentYear; $year++) {
			for($month = $startMonth; $month <= 12; $month++) {
				if(($year < $currentYear) || ($month <= $currentMonth)) {
					// Ensure a two-digit representation for the month
					$ym = str_pad($month, 2, '0', STR_PAD_LEFT);

					//Get invoices
					$invoices = $this->fetchData($invoicesDb, 'invoice', $year, $month, $ym, $client, $params, $options);

					//Get credit notes
					$creditnotes = $this->fetchData($creditnotesDb, 'creditnote', $year, $month, $ym, $client, $params, $options);

					//Get categories
					$categoriesDb = new Application_Model_DbTable_Category();
					$categories = $categoriesDb->getCategories('contact');

					//Calculate invoices
					foreach($invoices as $invoice) {
						if(isset($invoice->name1) && $invoice->invoiceid) {
							if(isset($customerList[$invoice->contactid]) && isset($customerList[$invoice->contactid]['invoices'])) {
								$customerList[$invoice->contactid]['subtotal'] += $invoice->subtotal;
								array_push($customerList[$invoice->contactid]['invoices'], $invoice->invoiceid);
							} else {
								$customerList[$invoice->contactid]['cid'] = $invoice->cid;
								$customerList[$invoice->contactid]['contactid'] = $invoice->contactid;
								$customerList[$invoice->contactid]['name1'] = $invoice->name1;
								$customerList[$invoice->contactid]['subtotal'] = $invoice->subtotal;
								$customerList[$invoice->contactid]['invoices'] = array($invoice->invoiceid);
								$customerList[$invoice->contactid]['ctitle'] = $categories[$invoice->catid]['title'];
								$customerList[$invoice->contactid]['cfulltitle'] = $categories[$invoice->catid]['fulltitle'];
							}
							if($invoice->prepayment) {
								$customerList[$invoice->contactid]['subtotal'] -= ($invoice->prepayment/1.19); //TODO
							}
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						if(isset($creditnote->name1) && $creditnote->creditnoteid) {
							if(isset($customerList[$creditnote->contactid]) && isset($customerList[$creditnote->contactid]['creditnotes'])) {
								$customerList[$creditnote->contactid]['subtotal'] += $creditnote->subtotal;
								if(isset($customerList[$creditnote->contactid]['creditnotes']))
									array_push($customerList[$creditnote->contactid]['creditnotes'], $creditnote->creditnoteid);
								else $customerList[$creditnote->contactid]['creditnotes'][] = $creditnote->creditnoteid;
							} else {
								$customerList[$creditnote->contactid]['cid'] = $creditnote->cid;
								$customerList[$creditnote->contactid]['contactid'] = $creditnote->contactid;
								$customerList[$creditnote->contactid]['name1'] = $creditnote->name1;
								$customerList[$creditnote->contactid]['subtotal'] = $creditnote->subtotal;
								$customerList[$creditnote->contactid]['creditnotes'] = array($creditnote->creditnoteid);
								if($creditnote->catid && isset($categories[$creditnote->catid]))
									$customerList[$creditnote->contactid]['ctitle'] = $categories[$creditnote->catid]['title'];
							}
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
					array_push($customerName, substr($customer['name1'], 0, 20));
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

			// Build the PNG file and send it to the web browser
			$url = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory')->getShortUrl();
			if(!file_exists(BASE_PATH.'/cache/chart/'.$url)) {
				mkdir(BASE_PATH.'/cache/chart/'.$url, 0777, true);
			}
			$chartCustomer->Render(BASE_PATH.'/cache/chart/'.$url.'/customer-'.$width.'-'.$height.'.png');
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
