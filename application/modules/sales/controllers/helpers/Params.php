<?php

class Sales_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->keyword->getAttrib('default')));
		$toolbar->keyword->setValue($params['keyword']);

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->catid->getAttrib('default')));
		$toolbar->catid->setValue($params['catid']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->limit->getAttrib('default')));
		$toolbar->limit->setValue($params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->order->getAttrib('default')));
		$toolbar->order->setValue($params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->sort->getAttrib('default')));
		$toolbar->sort->setValue($params['sort']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->country->getAttrib('default')));
		$toolbar->country->setValue($params['country']);

		$params['states'] = $request->getParam('states', $request->getCookie('states', $toolbar->states->getAttrib('default')));
		if(!is_array($params['states'])) $params['states'] = Zend_Json::decode($params['states']);
		$toolbar->states->setValue($params['states']);

		$params['from'] = $request->getParam('from', $request->getCookie('from', date('Y-m-d', strtotime('-1 month'))));
		$params['from'] = date("d.m.Y", strtotime($params['from']));
		$toolbar->from->setValue($params['from']);

		$params['to'] = $request->getParam('to', $request->getCookie('to', date('Y-m-d', strtotime('now'))));
		$params['to'] = date("d.m.Y", strtotime($params['to']));
		$toolbar->to->setValue($params['to']);

		$params['daterange'] = $request->getParam('daterange', $request->getCookie('daterange', $toolbar->daterange->getAttrib('default')));
		if($params['daterange'] && ($params['daterange'] != 'custom')) {
			$dateRange = $this->getDateRange($params['daterange']);
			$params['from'] = $dateRange['from'];
			$params['to'] = $dateRange['to'];
		}
		$toolbar->daterange->setValue($params['daterange']);

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
