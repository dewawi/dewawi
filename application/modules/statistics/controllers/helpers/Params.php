<?php

class Statistics_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->catid->getAttrib('default')));
		$toolbar->catid->setValue($params['catid']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->country->getAttrib('default')));
		$toolbar->country->setValue($params['country']);

		$params['from'] = $request->getParam('from', $request->getCookie('from', date('Y-m-d', strtotime('-1 month'))));
		$toolbar->from->setValue($params['from']);

		$params['to'] = $request->getParam('to', $request->getCookie('to', date('Y-m-d', strtotime('now'))));
		$toolbar->to->setValue($params['to']);

		$params['daterange'] = $request->getParam('daterange', $request->getCookie('daterange', $toolbar->daterange->getAttrib('default')));
		if($params['daterange'] && ($params['daterange'] != 'custom')) {
			$dateRange = $this->getDateRange($params['daterange']);
			$params['from'] = $dateRange['from'];
			$params['to'] = $dateRange['to'];
		}
		$toolbar->daterange->setValue($params['daterange']);

		$params['width'] = $request->getParam('width', $request->getCookie('width', $toolbar->width->getAttrib('default')));
		$toolbar->width->setValue($params['width']);

		$params['height'] = $request->getParam('height', $request->getCookie('height', $toolbar->height->getAttrib('default')));
		$toolbar->height->setValue($params['height']);

		return $params;
	}

	public function getDateRange($dateRange) {
		switch($dateRange) {
		case "today":
			$from = date('Y-m-d', strtotime('now'));
			$to = date('Y-m-d', strtotime('now'));
			break;
		case "yesterday":
			$from = date('Y-m-d', strtotime('-1 day'));
			$to = date('Y-m-d', strtotime('-1 day'));
			break;
		case "last7days":
			$from = date('Y-m-d', strtotime('-7 days'));
			$to = date('Y-m-d', strtotime('now'));
			break;
		case "last14days":
			$from = date('Y-m-d', strtotime('-14 days'));
			$to = date('Y-m-d', strtotime('now'));
			break;
		case "last30days":
			$from = date('Y-m-d', strtotime('-30 days'));
			$to = date('Y-m-d', strtotime('now'));
			break;
		case "thisMonth":
			$from = date('Y-m-01', strtotime('now'));
			$to = date('Y-m-t', strtotime('now'));
			break;
		case "lastMonth":
			$from = date('Y-m-01', strtotime('-1 month'));
			$to = date('Y-m-t', strtotime('-1 month'));
			break;
		case "thisYear":
			$from = date('Y-01-01', strtotime('now'));
			$to = date('Y-12-t', strtotime('now'));
			break;
		case "lastYear":
			$from = date('Y-01-01', strtotime('-1 year'));
			$to = date('Y-12-t', strtotime('-1 year'));
			break;
		default:
			$from = date('Y-m-d', strtotime('-1 month'));
			$to = date('Y-m-d', strtotime('now'));
		}
		return array('from' => $from, 'to' => $to);
	}
}
