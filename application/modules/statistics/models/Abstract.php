<?php

abstract class Statistics_Model_Abstract
{
	protected function fetchData($db, $type, $year, $month, $ym, $client, array $params, array $options)
	{
		$categoryOptions = isset($options['categories']) && is_array($options['categories']) ? $options['categories'] : array();
		$countryOptions = isset($options['country']) && is_array($options['country']) ? $options['country'] : array();

		$catid = isset($params['catid']) ? $params['catid'] : null;
		$country = isset($params['country']) ? $params['country'] : null;

		$from = $year.'-'.$ym.'-01';
		$to = date('Y-m-t', strtotime($from));

		$query = "i.state = 105";
		$query .= " AND ({$type}date >= '{$from}' AND {$type}date <= '{$to}')";
		$query .= " AND i.clientid = {$client['id']}";
		$query .= " AND c.clientid = {$client['id']}";
		$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCategory($query, $catid, $categoryOptions, 'c');

		if($country) {
			$query = Zend_Controller_Action_HelperBroker::getStaticHelper('Query')->getQueryCountry($query, $country, $countryOptions, 'i');
		}

		return $db->fetchAll(
			$db->select()
				->from(array('i' => $type))
				->join(array('c' => 'contact'), "i.contactid = c.contactid", array('id AS cid', 'catid', 'name1'))
				->where($query ? $query : 1)
				->setIntegrityCheck(false)
		);
	}
}
