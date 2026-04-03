<?php

class Sales_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();
		$params  = [];

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->getDefault('keyword')));
		$toolbar->setValue('keyword', $params['keyword']);

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->getDefault('catid')));
		$toolbar->setValue('catid', $params['catid']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->getDefault('limit')));
		$toolbar->setValue('limit', $params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->getDefault('order')));
		$toolbar->setValue('order', $params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->getDefault('sort')));
		$toolbar->setValue('sort', $params['sort']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->getDefault('country')));
		$toolbar->setValue('country', $params['country']);

		$params['states'] = $request->getParam('states', $request->getCookie('states', $toolbar->getDefault('states')));
		if(!is_array($params['states'])) $params['states'] = Zend_Json::decode($params['states']);
		$toolbar->setValue('states', $params['states']);

		$params['from'] = $request->getParam('from', $request->getCookie('from', date('Y-m-d', strtotime('-1 month'))));
		$params['from'] = date("d.m.Y", strtotime($params['from']));
		$toolbar->setValue('from', $params['from']);

		$params['to'] = $request->getParam('to', $request->getCookie('to', date('Y-m-d', strtotime('now'))));
		$params['to'] = date("d.m.Y", strtotime($params['to']));
		$toolbar->setValue('to', $params['to']);

		$params['daterange'] = $request->getParam('daterange', $request->getCookie('daterange', $toolbar->getDefault('daterange')));
		if($params['daterange'] && ($params['daterange'] != 'custom')) {
			$dateRange = $this->getDateRange($params['daterange']);
			$params['from'] = $dateRange['from'];
			$params['to'] = $dateRange['to'];
		}
		$toolbar->setValue('daterange', $params['daterange']);

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
