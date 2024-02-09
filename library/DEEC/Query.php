<?php

class DEEC_Query {

	public function getQueryKeyword($query, $keyword, $columns)
	{
		$keyword = trim($keyword);
		if($keyword) {
			//Remove special chracters from keyword
			$search =  '!"#€$%&/()=?*+\'-.,;:_§^{}[]´`¸';
			$search = str_split($search);
			$keyword = str_replace($search, " ", $keyword);
			//Split keyword
			$keywords = explode(' ', $keyword);
			foreach($keywords as $keyword) {
				if($keyword) {
					if($query) $query .= ' AND ';
					$query .= '(';
					$i = 0;
					foreach($columns as $column) {
						if($i) $query .= ' OR ';
						$query .= $column." LIKE '%".$keyword."%'";
						++$i;
					}
					$query .= ')';
				}
			}
		}
		return $query;
	}

	public function getQueryCategory($query, $catid, $categories, $schema = null)
	{
		//echo $catid;
		$categories = $this->getCategoryArray($categories);
		//print_r($categories);
		if($catid == '0') {
			if($query) $query .= ' AND ';
			if($schema) $query .= '('.$schema.'.catid = 0)';
			else $query .= '(catid = 0)';
		} elseif($catid == 'all') {
			//Do nothing
		} elseif(isset($categories[$catid])) {
			if($query) $query .= ' AND ';
			if(isset($categories[$catid]['childs'])) {
				$childs = $this->getChildCategories($catid, $categories);
				$childs = $this->getString($childs);
				if($schema) $query .= '('.$schema.'.catid IN ('.$catid.','.$childs.'))';
				else $query .= '(catid IN ('.$catid.','.$childs.'))';
			} else {
				if($schema) $query .= '('.$schema.'.catid = '.$catid.')';
				else $query .= '(catid = '.$catid.')';
			}
		}
		return $query;
	}

	public function getCategoryArray($categories)
	{
		$categoryArray = array();
		foreach($categories as $category) {
			$categoryArray[$category['id']] = $category;
		}
		foreach($categories as $category) {
			if($category['parentid']) {
				if(!isset($categoryArray[$category['parentid']])) $categoryArray[$category['parentid']] = array();
				if(!isset($categoryArray[$category['parentid']]['childs'])) $categoryArray[$category['parentid']]['childs'] = array();
				array_push($categoryArray[$category['parentid']]['childs'], $category['id']);
			}
		}
		return $categoryArray;
	}

	public function getQueryStates($query, $states, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= '('.$schema.'.state IN ('.implode(',', $states).'))';
		return $query;
	}

	public function getQueryCountry($query, $country, $countries, $schema)
	{
		if(isset($countries[$country])) {
			if($query) $query .= ' AND ';
			$query .= "(".$schema.".billingcountry = '".$country."' OR ".$schema.".shippingcountry = '".$country."')";
		}
		return $query;
	}

	public function getQueryCountryC($query, $country, $countries, $schema)
	{
		if(isset($countries[$country])) {
			if($query) $query .= ' AND ';
			$query .= "(".$schema.".country = '".$country."')";
		}
		return $query;
	}

	public function getQueryDaterange($query, $from, $to, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= "((".$schema.".created BETWEEN '".$from." 00:00:00' AND '".$to." 23:59:59') OR (".$schema.".modified BETWEEN '".$from." 00:00:00' AND '".$to." 23:59:59'))";
		return $query;
	}

	public function getQueryPaymentstatus($query, $paymentstatus, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= '('.$schema.'.paymentstatus IN ("'.implode('","', $paymentstatus).'"))';
		return $query;
	}

	public function getQueryClient($query, $clientid, $schema = null)
	{
		if($query) $query .= ' AND ';
		if($schema) $query .= '('.$schema.'.clientid = '.$clientid.')';
		else $query .= '(clientid = '.$clientid.')';
		return $query;
	}

	public function getQueryShopID($query, $shopid, $schema = null)
	{
		if($query) $query .= ' AND ';
		if($schema) $query .= '('.$schema.'.shopid = '.$shopid.')';
		else $query .= '(shopid = '.$shopid.')';
		return $query;
	}

	public function getQueryAccountID($query, $accountid, $schema = null)
	{
		if($query) $query .= ' AND ';
		if($schema) $query .= '('.$schema.'.accountid = '.$accountid.')';
		else $query .= '(accountid = '.$accountid.')';
		return $query;
	}

	public function getQueryDeleted($query, $schema = null)
	{
		if($query) $query .= ' AND ';
		if($schema) $query .= '('.$schema.'.deleted = 0)';
		else $query .= '(deleted = 0)';
		return $query;
	}

	public function getChildCategories($category, $categories) {
		$childCategories = array();
		array_push($childCategories, $categories[$category]['childs']);
		foreach($categories[$category]['childs'] as $childCategory) {
			if(isset($categories[$childCategory]['childs'])) {
				array_push($childCategories, $this->getChildCategories($childCategory, $categories));
			}
		}
		return $childCategories;
	}

	public function getString($categories) {
		foreach($categories as $category) {
			if(is_array($category)) {
				if(!isset($values)) $values = $this->getString($category);
				else $values .= ','.$this->getString($category);
			} else {
				if(!isset($values)) $values = $category;
				else $values .= ','.$category;
			}
		}
		return $values;
	}
}
