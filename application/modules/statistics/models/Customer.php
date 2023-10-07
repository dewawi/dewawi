<?php

class Statistics_Model_Customer
{
	public function createCharts($lenght, $width = 1000, $height = 600, $statisticsUncategorized, $params, $options)
	{
		//print_r($params);
		//print_r($options);
		$year = date('Y');
		$month = date('m');
		$y = date('Y', strtotime('-'.($lenght-1).' month'));
		$m = date('m', strtotime('-'.($lenght-1).' month'));

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$creditnotesDb = new Sales_Model_DbTable_Creditnote();

		$customerList = array();
		while($y <= ($year)) {
			while($m) {
				if(($y < $year) || ($m <= $month)) {
					//Get invoices
					$ym = str_pad($m, 2, '0', STR_PAD_LEFT);
					$query = 'i.state = 105';
					$query .= " AND (invoicedate BETWEEN '".$y."-".$ym."-"."01' AND '".$y."-".$ym."-"."31')";
					$query .= ' AND i.clientid = '.$client['id'];
					$query .= ' AND c.clientid = '.$client['id'];
					$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCategory($query, $params['catid'], $options['categories'], 'c');
					if($params['country']) $query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCountry($query, $params['country'], $options['countries'], 'i');
					$invoices = $invoicesDb->fetchAll(
						$invoicesDb->select()
							->from(array('i' => 'invoice'))
							->join(array('c' => 'contact'), 'i.contactid = c.contactid', array('catid', 'name1'))
							->where($query ? $query : 1)
							->setIntegrityCheck(false)
					);

					//Get credit notes
					$ym = str_pad($m, 2, '0', STR_PAD_LEFT);
					$query = 'i.state = 105';
					$query .= " AND (creditnotedate BETWEEN '".$y."-".$ym."-"."01' AND '".$y."-".$ym."-"."31')";
					$query .= ' AND i.clientid = '.$client['id'];
					$query .= ' AND c.clientid = '.$client['id'];
					$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCategory($query, $params['catid'], $options['categories'], 'c');
					if($params['country']) $query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCountry($query, $params['country'], $options['countries'], 'i');
					$creditnotes = $creditnotesDb->fetchAll(
						$creditnotesDb->select()
							->from(array('i' => 'creditnote'))
							->join(array('c' => 'contact'), 'i.contactid = c.contactid', array('catid'))
							->where($query ? $query : 1)
							->setIntegrityCheck(false)
					);

					//Calculate invoices
					foreach($invoices as $invoice) {
						if(isset($customerList[$invoice->contactid])) {
							$customerList[$invoice->contactid]['subtotal'] += $invoice->subtotal;
						} else {
							$customerList[$invoice->contactid]['contactid'] = $invoice->contactid;
							$customerList[$invoice->contactid]['name1'] = $invoice->name1;
							$customerList[$invoice->contactid]['subtotal'] = $invoice->subtotal;
						}
						if($invoice->prepayment) {
							$customerList[$invoice->contactid]['subtotal'] -= ($invoice->prepayment/1.19); //TODO
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						if(isset($customerList[$creditnote->contactid])) {
							$customerList[$creditnote->contactid]['subtotal'] += $creditnote->subtotal;
						} else {
							$customerList[$creditnote->contactid]['contactid'] = $creditnote->contactid;
							$customerList[$creditnote->contactid]['name1'] = $creditnote->name1;
							$customerList[$creditnote->contactid]['subtotal'] = $creditnote->subtotal;
						}
					}
				}
				++$m;
				if($m > 12) $m = 0;
			}
			++$y;
			$m = 1;
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

			require_once(BASE_PATH.'/library/pChart/class/pData.class.php');
			require_once(BASE_PATH.'/library/pChart/class/pDraw.class.php');
			require_once(BASE_PATH.'/library/pChart/class/pImage.class.php');

			//Turnover
			/* Create your dataset object */
			$turnoverData = new pData();

			/* Add data in your dataset */
			$turnoverData->addPoints($customerTurnover,'Values');
			$turnoverData->setAxisName(0,'€ / Netto');

			/* Create the X serie */
			$turnoverData->addPoints($customerName,'Labels');
			$turnoverData->setSerieDescription('Labels','Months');
			$turnoverData->setAbscissa('Labels');

			/* Create a pChart object and associate your dataset */
			$turnover = new pImage($width, $height, $turnoverData, TRUE);

			/* Turn off AA processing */
			$turnover->Antialias = FALSE;

			/* Choose a nice font */
			$turnover->setFontProperties(array('FontName' => BASE_PATH.'/library/pChart/fonts/verdana.ttf','FontSize'=>10));

			/* Define the boundaries of the graph area */
			$turnover->setGraphArea(75, 20, $width-30, $height-130);

			/* Draw the scale, keep everything automatic */
			$turnover->drawScale(array('DrawSubTicks' => TRUE, 'Mode' => SCALE_MODE_START0, 'LabelRotation' => 45));

			/* Draw the scale, keep everything automatic */
			$settings = array('Gradient' => TRUE, 'GradientMode' => GRADIENT_EFFECT_CAN, 'DisplayPos' => LABEL_POS_INSIDE, 'DisplayValues' => TRUE, 'DisplayR' => 0, 'DisplayG' => 0, 'DisplayB' => 0, 'DisplayShadow' => TRUE, 'Surrounding' => 10);
			$turnover->drawBarChart($settings);

			/* Build the PNG file and send it to the web browser */
			if(!file_exists(BASE_PATH.'/cache/chart/')) {
				mkdir(BASE_PATH.'/cache/chart/');
				chmod(BASE_PATH.'/cache/chart/', 0777);
			}
			$turnover->Render(BASE_PATH.'/cache/chart/customer-'.$width.'-'.$height.'.png');
		}

		return $customerList;
	}
}
