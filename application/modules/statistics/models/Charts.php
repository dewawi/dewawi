<?php

class Statistics_Model_Charts
{
	public function createCharts($lenght, $width = 1000, $height = 400, $statisticsUncategorized, $params, $options)
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

		$turnover = array();
		$turnoverCategories = array();

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
							->join(array('c' => 'contact'), 'i.contactid = c.contactid', array('catid'))
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

					$turnover[$y.$ym] = 0;
					$turnoverCategories[0][$y.$ym] = 0;

					//Calculate invoices
					foreach($invoices as $invoice) {
						$turnover[$y.$ym] += $invoice->subtotal;
						if(isset($turnoverCategories[$invoice->catid][$y.$ym])) {
							$turnoverCategories[$invoice->catid][$y.$ym] += $invoice->subtotal;
						} else {
							$turnoverCategories[$invoice->catid][$y.$ym] = $invoice->subtotal;
						}
					}

					//Calculate credit notes
					foreach($creditnotes as $creditnote) {
						$turnover[$y.$ym] -= $creditnote->subtotal;
						if(isset($turnoverCategories[$creditnote->catid][$y.$ym])) {
							$turnoverCategories[$creditnote->catid][$y.$ym] += $creditnote->subtotal;
						} else {
							$turnoverCategories[$creditnote->catid][$y.$ym] = $creditnote->subtotal;
						}
					}

					//Calculate categories
					foreach($options['categories'] as $id => $category) {
						if(isset($turnoverCategories[$id][$y.$ym])) {
							$turnoverCategories[$id][$y.$ym] = round($turnoverCategories[$id][$y.$ym]);
						} else {
							$turnoverCategories[$id][$y.$ym] = 0;
						}
					}

					$turnover[$y.$ym] = round($turnover[$y.$ym]);
					$turnoverCategories[0][$y.$ym] = round($turnoverCategories[0][$y.$ym]);

					$dataDb = 'total:'.$turnover[$y.$ym].';';
					foreach($turnoverCategories as $key => $value) {
						if(isset($value[$y.$ym])) $dataDb .= $key.':'.$value[$y.$ym].';';
					}
					//$archiveDb->addArchive($y.$ym, $dataDb, $client['id']);
					$months[$y.$ym] = $y.'/'.$ym;
				}
				++$m;
				if($m > 12) $m = 0;
			}
			++$y;
			$m = 1;
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

		require_once(BASE_PATH.'/library/pChart/class/pData.class.php');
		require_once(BASE_PATH.'/library/pChart/class/pDraw.class.php');
		require_once(BASE_PATH.'/library/pChart/class/pImage.class.php');

		//Turnover
		/* Create your dataset object */
		$turnoverData = new pData();

		/* Add data in your dataset */
		$turnoverData->addPoints($turnover,'Values');
		$turnoverData->setAxisName(0,'€ / Netto');

		/* Create the X serie */
		$turnoverData->addPoints($months,'Labels');
		$turnoverData->setSerieDescription('Labels','Months');
		$turnoverData->setAbscissa('Labels');

		/* Create a pChart object and associate your dataset */
		$turnover = new pImage($width, $height, $turnoverData, TRUE);

		/* Turn off AA processing */
		$turnover->Antialias = FALSE;

		/* Choose a nice font */
		$turnover->setFontProperties(array('FontName' => BASE_PATH.'/library/pChart/fonts/verdana.ttf','FontSize'=>10));

		/* Define the boundaries of the graph area */
		$turnover->setGraphArea(75, 20, $width-30, $height-60);

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
		$turnover->Render(BASE_PATH.'/cache/chart/turnover-'.$width.'-'.$height.'.png');

		//Turnover by categories
		/* Create your dataset object */
		$turnoverCategoriesData = new pData();

		/* Add data in your dataset */
		$turnoverCategoriesTotal = array();
		foreach($turnoverCategories as $key => $value) {
			$turnoverCategoriesTotal[$key] = array_sum($value);
		}
		arsort($turnoverCategoriesTotal);
		foreach($turnoverCategoriesTotal as $key => $value) {
			if($key && isset($options['categories'][$key])) $turnoverCategoriesData->addPoints($turnoverCategories[$key], $options['categories'][$key]['title']);
		}
		if(isset($turnoverCategories[0])) {
			$turnoverCategoriesData->addPoints($turnoverCategories[0], $statisticsUncategorized);
		}
		$turnoverCategoriesData->setAxisName(0, '€ / Netto');

		/* Create the X serie */
		$turnoverCategoriesData->addPoints($months, 'Labels');
		$turnoverCategoriesData->setSerieDescription('Labels', 'Months');
		$turnoverCategoriesData->setAbscissa('Labels');

		/* Create a pChart object and associate your dataset */
		$turnoverCategories = new pImage($width, $height, $turnoverCategoriesData, TRUE);

		/* Turn off AA processing */
		$turnoverCategories->Antialias = FALSE;

		/* Choose a nice font */
		$turnoverCategories->setFontProperties(array('FontName' => BASE_PATH.'/library/pChart/fonts/verdana.ttf', 'FontSize' => 10));

		/* Define the boundaries of the graph area */
		$turnoverCategories->setGraphArea(75, 20, $width-30, $height-60);

		/* Draw the scale, keep everything automatic */
		$turnoverCategories->drawScale(array('XMargin' => 2, 'DrawSubTicks' => TRUE, 'Mode' => SCALE_MODE_ADDALL_START0, 'LabelRotation' => 45));

		/* Draw the scale, keep everything automatic */
		$settings = array();
		$turnoverCategories->drawStackedAreaChart($settings);

		/* Write the chart legend */
		$turnoverCategories->drawLegend(100, 20, array('Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_VERTICAL));

		/* Build the PNG file and send it to the web browser */
		$turnoverCategories->Render(BASE_PATH.'/cache/chart/turnover-category-'.$width.'-'.$height.'.png');
	}
}
