<?php

class Application_Controller_Action_Helper_Query extends Zend_Controller_Action_Helper_Abstract
{
	private function appendCondition(&$query, $condition)
	{
		if ($query) {
			$query .= ' AND ';
		}
		$query .= $condition;
	}

	public function getQueryKeyword($query, $keyword, $columns)
	{
		$keyword = trim($keyword);
		if ($keyword) {
			// Remove special characters and split by spaces
			$keyword = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);
			$keywords = array_filter(explode(' ', $keyword));

			// Build query for each keyword
			foreach ($keywords as $word) {
				$this->appendCondition($query, '(' . implode(' OR ', array_map(function ($column) use ($word) {
					return "$column LIKE '%$word%'";
				}, $columns)) . ')');
			}
		}
		return $query;
	}

	public function getQueryCategory($query, $catid, $categories, $schema = null)
	{
		if ($catid == '0') {
			$this->appendCondition($query, ($schema ? "$schema." : "") . 'catid = 0');
		} elseif ($catid !== 'all' && isset($categories[$catid])) {
			$childs = $this->getChildCategories($catid, $categories);
			$this->appendCondition($query, ($schema ? "$schema." : "") . 'catid IN (' . implode(',', array_merge([$catid], $childs)) . ')');
		}
		return $query;
	}

	public function getQueryStates($query, $states, $schema)
	{
		$this->appendCondition($query, "$schema.state IN (" . implode(',', $states) . ")");
		return $query;
	}

	public function getQueryCountry($query, $country, $countries, $schema)
	{
		if (isset($countries[$country])) {
			$this->appendCondition($query, "($schema.billingcountry = '$country' OR $schema.shippingcountry = '$country')");
		}
		return $query;
	}

	public function getQueryCountryC($query, $country, $countries, $schema)
	{
		if (isset($countries[$country])) {
			$this->appendCondition($query, "$schema.country = '$country'");
		}
		return $query;
	}

	public function getQueryDaterange($query, $from, $to, $schema)
	{
		$this->appendCondition($query, "($schema.created BETWEEN '$from 00:00:00' AND '$to 23:59:59' OR $schema.modified BETWEEN '$from 00:00:00' AND '$to 23:59:59')");
		return $query;
	}

	public function getQueryPaymentstatus($query, $paymentstatus, $schema)
	{
		$this->appendCondition($query, "$schema.paymentstatus IN ('" . implode("','", $paymentstatus) . "')");
		return $query;
	}

	public function getQueryClient($query, $clientid, $schema = null)
	{
		$this->appendCondition($query, ($schema ? "$schema." : "") . "clientid = $clientid");
		return $query;
	}

	public function getQueryShopID($query, $shopid, $schema = null)
	{
		$this->appendCondition($query, ($schema ? "$schema." : "") . "shopid = $shopid");
		return $query;
	}

	public function getQueryAccountID($query, $accountid, $schema = null)
	{
		$this->appendCondition($query, ($schema ? "$schema." : "") . "accountid = $accountid");
		return $query;
	}

	public function getQueryDeleted($query, $schema = null)
	{
		$this->appendCondition($query, ($schema ? "$schema." : "") . "deleted = 0");
		return $query;
	}

	// Recursively get all child categories
	public function getChildCategories($category, $categories)
	{
		$childCategories = [];
		if (isset($categories[$category]['childs'])) {
			foreach ($categories[$category]['childs'] as $childCategory) {
				$childCategories[] = $childCategory;
				$childCategories = array_merge($childCategories, $this->getChildCategories($childCategory, $categories));
			}
		}
		return $childCategories;
	}
}
