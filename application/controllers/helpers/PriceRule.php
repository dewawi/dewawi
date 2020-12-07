<?php

class Application_Controller_Action_Helper_PriceRule extends Zend_Controller_Action_Helper_Abstract
{
	public function direct($contactid, $item, $data, $helper) {
		//Get contact
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($contactid);

		$formula = array();
		$priceruleamount = 0;
		$price = $item['price'];
		$formula['bypercent'] = 'return $price*(100-$priceruleamount)/100;';
		$formula['byfixed'] = 'return $price-$priceruleamount;';
		$formula['topercent'] = 'return $price*(100+$priceruleamount)/100;';
		$formula['tofixed'] = 'return $price+$priceruleamount;';
		$data['priceruleamount'] = 0;
		$data['priceruleaction'] = '';
		//Discard other price rules if price rule in contact is defined
		if($contact['priceruleamount'] && $contact['priceruleaction']) {
			$data['priceruleamount'] = $contact['priceruleamount'];
			$data['priceruleaction'] = $contact['priceruleaction'];
		//Check other price rules if no price rule in contact is defined
		} else {
			$options = array();
			if($item['type']) $options['itemtype'] = $item['type'];
			if($item['manufacturerid']) $options['itemmanufacturer'] = $item['manufacturerid'];

			$priceruleDb = new Items_Model_DbTable_Pricerule();
			$pricerulesObject = $priceruleDb->getPricerules($options);

			//Select the price rules which are applicable to the item
			$pricerules = array();
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
						$isParent = $helper->Category->isParent($item['catid'], $pricerule->itemcatid, $categoriesItem);
						if($isParent) $pricerules[] = $pricerule;
					}
				}
			}
			//Remove price rules which are not applicable to the contact
			if(count($pricerules)) {
				$categoriesContact = $categoryDb->getCategories('contact');
				foreach($pricerules as $id => $pricerule) {
					if($pricerule->contactcatid && ($contact['catid'] == 0)) {
						unset($pricerules[$id]);
					} elseif($pricerule->contactcatid == $contact['catid']) {
						//Contact category is the same keep the rule
					} elseif($contact['catid'] && $pricerule->contactsubcat) {
						$isParent = $helper->Category->isParent($contact['catid'], $pricerule->contactcatid, $categoriesContact);
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

			//Apply the price rules to the position
			if(count($pricerules)) {
				foreach($pricerules as $pricerule) {
					if($pricerule->amount && $pricerule->action) {
						$priceruleamount = $pricerule->amount;
						$price = eval($formula[$pricerule->action]);
						$price = round($price, 2);
						if($price < $item['price']) {
							$data['priceruleamount'] = $item['price'] - $price;
							$data['priceruleaction'] = 'byfixed';
						} elseif($price > $item['price']) {
							$data['priceruleamount'] = $price - $item['price'];
							$data['priceruleaction'] = 'tofixed';
						}
						//Stop the rule if subsequent
						if($pricerule->subsequent) break;
					}
				}
			}
		}
		return $data;
	}
}
