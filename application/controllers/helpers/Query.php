<?php

class Application_Controller_Action_Helper_Query extends Zend_Controller_Action_Helper_Abstract
{
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
		return $query;
	}

	public function getQueryStates($query, $states, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= '('.$schema.'.state IN ('.implode(',', $states).'))';
		return $query;
	}

	public function getQueryCountry($query, $country, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= "(".$schema.".billingcountry = '".$country."' OR ".$schema.".shippingcountry = '".$country."')";
		return $query;
	}

	public function getQueryCountryC($query, $country, $schema)
	{
		if($query) $query .= ' AND ';
		$query .= "(".$schema.".country = '".$country."')";
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
