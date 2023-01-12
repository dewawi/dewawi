<?php

class Application_Controller_Action_Helper_PriceRule extends Zend_Controller_Action_Helper_Abstract
{

	public function getPriceRulePositions($module, $parent, $parentid) {
		//Get price rule positions
		$pricerulesDb = new Items_Model_DbTable_Pricerulepos();
		$positions = $pricerulesDb->getPositions($module, $parent, $parentid);
		return $positions;
	}

	public function usePriceRules($pricerules, $price) {
		//Use price rules
		foreach($pricerules as $pricerule) {
			if($pricerule['amount'] && $pricerule['action']) {
				if($pricerule['action'] == 'bypercent')
					$price = $price*(100-$pricerule['amount'])/100;
				elseif($pricerule['action'] == 'byfixed')
					$price = ($price-$pricerule['amount']);
				elseif($pricerule['action'] == 'topercent')
					$price = $price*(100+$pricerule['amount'])/100;
				elseif($pricerule['action'] == 'tofixed')
					$price = ($price+$pricerule['amount']);
			}
		}
		return $price;
	}

	public function usePriceRulesOnPositions($positions, $module, $parent) {
		//Use price rules on all positions
		$price = array();
		$price['rules'] = array();
		$price['master'] = array();
		$price['calculated'] = array();
		foreach($positions as $position) {
			//Get price rules and properties
			if(!$position->masterid) {
				$price['rules'][$position->id] = $this->getPriceRulePositions($module, $parent, $position->id);
				$price['master'][$position->id] = $position->pricerulemaster;
			}
		}
		foreach($positions as $position) {
			//Use price rules
			if($position->masterid && $price['master'][$position->masterid] && isset($price['rules'][$position->masterid])) {
				$price['calculated'][$position->id] = $this->usePriceRules($price['rules'][$position->masterid], $position->price);
			} elseif(!$position->masterid && isset($price['rules'][$position->id])) {
				$price['calculated'][$position->id] = $this->usePriceRules($price['rules'][$position->id], $position->price);
			} else {
				$price['calculated'][$position->id] = $position->price;
			}
		}
		return $price;
	}

	public function getPriceRules($item, $contactid = 0) {
		//Get contact
		if($contactid) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContactWithID($contactid);
		}

		$formula = array();
		$pricerules = array();
		$priceruleamount = 0;
		$price = $item['price'];
		$formula['bypercent'] = 'return $price*(100-$priceruleamount)/100;';
		$formula['byfixed'] = 'return $price-$priceruleamount;';
		$formula['topercent'] = 'return $price*(100+$priceruleamount)/100;';
		$formula['tofixed'] = 'return $price+$priceruleamount;';
		//$data['priceruleamount'] = 0;
		//$data['priceruleaction'] = '';
		//Discard other price rules if price rule in contact is defined
		if($contactid && $contact['priceruleamount'] && $contact['priceruleaction']) {
			//$data['priceruleamount'] = $contact['priceruleamount'];
			//$data['priceruleaction'] = $contact['priceruleaction'];
		//Check other price rules if no price rule in contact is defined
		} else {
			$options = array();
			if($item['type']) $options['itemtype'] = $item['type'];
			if($item['manufacturerid']) $options['itemmanufacturer'] = $item['manufacturerid'];

			//Get price rules
			$priceruleDb = new Items_Model_DbTable_Pricerule();
			$pricerulesObject = $priceruleDb->getPricerules($options);

			//Select the price rules which are applicable to the item
			if(count($pricerulesObject)) {
				$categoryDb = new Application_Model_DbTable_Category();
				$categoriesItem = $categoryDb->getCategories('item');
				foreach($pricerulesObject as $pricerule) {
					if($pricerule->itemcatid == 0) {
						$pricerules[] = $pricerule;
					} elseif($pricerule->itemcatid && ($item['catid'] == 0)) {
						//do nothing
					} elseif($item['catid'] && ($item['catid'] == $pricerule->itemcatid)) {
						$pricerules[] = $pricerule;
					} elseif($item['catid'] && $pricerule->itemsubcat) {
						$categoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Category');
						$isParent = $categoryHelper->isParent($item['catid'], $pricerule->itemcatid, $categoriesItem);
						if($isParent) $pricerules[] = $pricerule;
					}
				}
			}
			//Remove price rules which are not applicable to the contact
			if($contactid && count($pricerules)) {
				$categoriesContact = $categoryDb->getCategories('contact');
				foreach($pricerules as $id => $pricerule) {
					if($pricerule->contactcatid && ($contact['catid'] == 0)) {
						unset($pricerules[$id]);
					} elseif($pricerule->contactcatid == $contact['catid']) {
						//Contact category is the same keep the rule
					} elseif($contact['catid'] && $pricerule->contactsubcat) {
						$categoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Category');
						$isParent = $categoryHelper->isParent($contact['catid'], $pricerule->contactcatid, $categoriesContact);
						if(!$isParent) unset($pricerules[$id]);
					}
				}
			}
			//Remove price rules which are not applicable to the item price
			if(count($pricerules)) {
				foreach($pricerules as $id => $pricerule) {
					if($pricerule->pricefrom) {
						if($pricerule->pricefrom > $item['price']) {
							unset($pricerules[$id]);
						}
					}
					if($pricerule->priceto) {
						if($pricerule->priceto < $item['price']) {
							unset($pricerules[$id]);
						}
					}
				}
			}
			//Remove price rules which are not applicable to the dates
			if(count($pricerules)) {
				foreach($pricerules as $id => $pricerule) {
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
		}
		return $pricerules;
	}

	public function applyPriceRules($module, $controller, $pricerules, $parentid) {
		//Apply the price rules to the position
		if(count($pricerules)) {
			$positionDb = new Items_Model_DbTable_Pricerulepos();
			foreach($pricerules as $pricerule) {
				if($pricerule->amount && $pricerule->action) {
					$positionDataBefore = $positionDb->getPositions($module, $controller, $parentid, 0);
					$latest = end($positionDataBefore);
					$positionDb->addPosition(array('module' => $module, 'controller' => $controller, 'parentid' => $parentid, 'amount' => $pricerule->amount, 'action' => $pricerule->action, 'masterid' => 0, 'possetid' => 0, 'ordering' => $latest['ordering']+1));

					/*$priceruleamount = $->amount;
					$price = eval($formula[$pricerule->action]);
					$price = round($price, 2);
					if($price < $item['price']) {
						$data['priceruleamount'] = $item['price'] - $price;
						$data['priceruleaction'] = 'byfixed';
					} elseif($price > $item['price']) {
						$data['priceruleamount'] = $price - $item['price'];
						$data['priceruleaction'] = 'tofixed';
					}*/

					//Stop the rule if subsequent
					if($pricerule->subsequent) break;
				}
			}
		}
	}

	public function formatPriceRules($pricerules, $currency, $locale) {
		if(isset($pricerules)) {
			foreach($pricerules as $id => $pricerule) {
				if(($pricerule['action'] == 'byfixed') || ($pricerule['action'] == 'tofixed')) {
					$pricerules[$id]['amount'] = $currency->toCurrency($pricerule['amount']);
				} elseif(($pricerule['action'] == 'bypercent') || ($pricerule['action'] == 'topercent')) {
					$precision = (floor($pricerule['amount']) == $pricerule['amount']) ? 0 : 2;
					$pricerules[$id]['amount'] = Zend_Locale_Format::toNumber($pricerule['amount'],array('precision' => $precision,'locale' => $locale)).' %';
				}
			}
		}
		return $pricerules;
	}
}
