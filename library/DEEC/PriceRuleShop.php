<?php

class DEEC_PriceRuleShop {

	public function usePricerules($item, $connection, $options) {
		$formula = array();
		$priceruleamount = 0;
		$formula['bypercent'] = 'return $price*(100-$priceruleamount)/100;';
		$formula['byfixed'] = 'return $price-$priceruleamount;';
		$formula['topercent'] = 'return $price*(100+$priceruleamount)/100;';
		$formula['tofixed'] = 'return $price+$priceruleamount;';
		$data['priceruleamount'] = 0;
		$data['priceruleaction'] = '';
		$price = $item['price'];

		$pricerulesObject = $this->getPricerules($connection, $options, $item['clientid']);

		//Select the price rules which are applicable to the item
		$pricerules = array();
		if($pricerulesObject && count($pricerulesObject)) {

			$categoriesItem = $this->getCategories($connection, 'item', $item['clientid']);

			foreach($pricerulesObject as $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->itemcatid == 0) {
					$pricerules[] = $pricerule;
				} elseif($pricerule->itemcatid && ($item['catid'] == 0)) {
					//do nothing
				} elseif($item['catid'] && ($item['catid'] == $pricerule->itemcatid)) {
					$pricerules[] = $pricerule;
				} elseif($item['catid'] && $pricerule->itemsubcat) {
					$isParent = $this->isParent($item['catid'], $pricerule->itemcatid, $categoriesItem);
					if($isParent) $pricerules[] = $pricerule;
				}
			}
		}

		//Remove price rules which are not applicable to the manufacturer
		if(count($pricerules)) {
			foreach($pricerules as $id => $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->itemmanufacturer) {
					if($item['manufacturerid'] != $pricerule->itemmanufacturer) {
						unset($pricerules[$id]);
					}
				}
			}
		}

		//Remove price rules which are not applicable to the contact
		if(count($pricerules)) {
			$categoriesContact = $this->getCategories($connection, 'contact', $item['clientid']);
			foreach($pricerules as $id => $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->contactcatid) {
					unset($pricerules[$id]);
				}
			}
		}
		//Remove price rules which are not applicable to the item price
		if(count($pricerules)) {
			foreach($pricerules as $id => $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->pricefrom) {
					if($pricerule->pricefrom > $price) {
						unset($pricerules[$id]);
					}
				}
				if($pricerule->priceto) {
					if($pricerule->priceto < $price) {
						unset($pricerules[$id]);
					}
				}
			}
		}

		//Remove price rules which are not applicable to the dates
		if(count($pricerules)) {
			foreach($pricerules as $id => $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->datefrom) {
					if(strtotime($pricerule->datefrom) > strtotime(date('Y-m-d').' 23:59:59')) {
						unset($pricerules[$id]);
					}
				}
				if($pricerule->dateto) {
					if(strtotime($pricerule->dateto) < strtotime(date('Y-m-d').' 00:00:00')) {
						unset($pricerules[$id]);
					}
				}
			}
		}

		//Apply the price rules to the position
		if(count($pricerules)) {
			foreach($pricerules as $pricerule) {
				$pricerule = (object)$pricerule;
				if($pricerule->amount && $pricerule->action) {
					$priceruleamount = $pricerule->amount;
					$priceOld = $price;
					$price = eval($formula[$pricerule->action]);
					$price = round($price, 2);
					if($pricerule->action == 'bypercent') {
						$priceDifference = $priceOld - $price;
						if($pricerule->amountmin && ($pricerule->amountmin > $priceDifference)) {
							$price = $priceOld - $pricerule->amountmin;
						}
						if($pricerule->amountmax && ($pricerule->amountmax < $priceDifference)) {
							$price = $priceOld - $pricerule->amountmax;
						}
					}
					if($pricerule->action == 'topercent') {
						$priceDifference = $price - $priceOld;
						if($pricerule->amountmin && ($pricerule->amountmin > $priceDifference)) {
							$price = $priceOld + $pricerule->amountmin;
						}
						if($pricerule->amountmax && ($pricerule->amountmax < $priceDifference)) {
							$price = $priceOld + $pricerule->amountmax;
						}
					}
					//Stop the rule if subsequent
					if($pricerule->subsequent) break;
				}
			}
		}
	    return $price;
	}

	public function getPricerules($connection, $options, $clientid) {
		$where = '';
		foreach($options as $key => $option) {
			if($where) $where .= ' AND ';
			$where .= '('.$key.' = "'.$option.'" OR '.$key.' = 0 OR '.$key.' IS NULL)';
		}
		if($where) $where .= ' AND ';
		$where .= 'clientid = '.$clientid;
		$where .= ' AND activated = 1 ';
		$where .= ' AND deleted = 0';
		$query = '
				SELECT
					* FROM pricerule
				WHERE
					'.$where.'
				ORDER
					BY priority;';
		//echo $query;
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getCategories($connection, $type, $clientid) {
		$where = '';
		$where .= 'type = '.$type.' ';
		$where .= 'clientid = '.$clientid.' ';
		$where .= 'deleted = 0';
		$query = '
				SELECT
					* FROM category
				WHERE
					'.$where.'
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    $data = mysqli_fetch_object($result);
			$categories = array();
			foreach($data as $category) {
				if(!$category->parentid) {
					$categories[$category->id]['id'] = $category->id;
					$categories[$category->id]['title'] = $category->title;
					$categories[$category->id]['parentid'] = $category->parentid;
					$categories[$category->id]['ordering'] = $category->ordering;
					if($category->parentid) {
						if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
						if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
						array_push($categories[$category->parentid]['childs'], $category->id);
					}
				}
			}
			foreach($data as $category) {
				if($category->parentid) {
					$categories[$category->id]['id'] = $category->id;
					$categories[$category->id]['title'] = $category->title;
					$categories[$category->id]['parentid'] = $category->parentid;
					$categories[$category->id]['ordering'] = $category->ordering;
					if($category->parentid) {
						if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
						if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
						array_push($categories[$category->parentid]['childs'], $category->id);
					}
				}
			}
			return $categories;
		} else {
		    return false;
		}
	}
	public function isParent($id, $parentid, $categories) {
		if(isset($categories[$parentid]['childs'])) {
			return $this->checkChilds($id, $parentid, $categories);
		}
		return false;
	}

	public function checkChilds($id, $parentid, $categories) {
		if(isset($categories[$parentid]['childs'])) {
			if(array_search($id, $categories[$parentid]['childs']) !== false) {
				return true;
			} else {
				foreach($categories[$parentid]['childs'] as $child) {
					return $this->checkChilds($id, $child, $categories);
				}
			}
		} else {
			return false;
		}
	}
}
