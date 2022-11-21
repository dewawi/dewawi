<?php

class Application_Controller_Action_Helper_Calculate extends Zend_Controller_Action_Helper_Abstract
{
	public function direct($id, $date, $user, $taxfree = null) {
		$request = $this->getRequest();
		$module = $request->getParam('module', null);
		$controller = $request->getParam('controller', null);
		if($controller == 'position') $controller = $request->getParam('parent');
		if(substr($controller, -3) == 'pos') $controller = substr($controller, 0, -3);
		$class = ucfirst($module).'_Model_DbTable_'.ucfirst($controller);
		$classPos = $class.'pos';
		$function = 'get'.ucfirst($controller);
		if(class_exists($class) || class_exists($classPos)) {
			//Get object
			$objectDb = new $class();
			$object = $objectDb->$function($id);

			//Get positions
			$positionsDb = new $classPos();
			$positions = $positionsDb->getPositions($id);

			$calculations = array();
			$calculations['row'] = array();
			$calculations['locale'] = array();
			$calculations['row']['subtotal'] = 0;
			$calculations['row']['taxes'] = array();
			$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
			$pricerulesHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
			$currency = $currencyHelper->getCurrency();
			$pricerules = array();
			$pricerulemaster = array();
			foreach($positions as $position) {
				//Get price rules and properties
				if(!$position->masterid) {
					$pricerules[$position->id] = $pricerulesHelper->getPriceRulePositions($module, $controller.'pos', $position->id);
					$pricerulemaster[$position->id] = $position->pricerulemaster;
				}
			}
			foreach($positions as $position) {
				//Use price rules
				if($position->masterid && $pricerulemaster[$position->masterid] && isset($pricerules[$position->masterid])) {
					$price = $pricerulesHelper->usePriceRules($pricerules[$position->masterid], $position->price);
				} elseif(!$position->masterid && isset($pricerules[$position->id])) {
					$price = $pricerulesHelper->usePriceRules($pricerules[$position->id], $position->price);
				} else {
					$price = $position->price;
				}

				$calculations['row'][$position->id]['total'] = $price*$position->quantity;
				$calculations['row']['subtotal'] += $calculations['row'][$position->id]['total'];

				if(isset($calculations['row']['taxes']['total'])) $calculations['row']['taxes']['total'] += $calculations['row'][$position->id]['total']*$position->taxrate/100;
				else $calculations['row']['taxes']['total'] = $calculations['row'][$position->id]['total']*$position->taxrate/100;
				if(isset($calculations['row']['taxes'][$position->taxrate])) $calculations['row']['taxes'][$position->taxrate] += $calculations['row'][$position->id]['total']*$position->taxrate/100;
				else $calculations['row']['taxes'][$position->taxrate] = $calculations['row'][$position->id]['total']*$position->taxrate/100;

				$currencyHelper->setCurrency($currency, $position->currency, 'USE_SYMBOL');
				$calculations['locale'][$position->id]['price'] = $currency->toCurrency($price);
				$calculations['locale'][$position->id]['total'] = $currency->toCurrency($calculations['row'][$position->id]['total']);

				$objectPosDb = new $classPos();
				$objectPosDb->updatePosition($position->id, array('total' => ($price*$position->quantity*(1+$position->taxrate/100))));
			}

			if($taxfree === null) $taxfree = $object['taxfree'];
			if($taxfree) $calculations['row']['taxes']['total'] = 0;

			if(!isset($calculations['row']['taxes']['total'])) $calculations['row']['taxes']['total'] = 0;
			$calculations['row']['total'] = $calculations['row']['subtotal'] + $calculations['row']['taxes']['total'];

			$objectDb->updateTotal($id, $calculations['row']['subtotal'], $calculations['row']['taxes']['total'], $calculations['row']['total']);

			$calculations['locale']['subtotal'] = $currency->toCurrency($calculations['row']['subtotal']);
			$calculations['locale']['total'] = $currency->toCurrency($calculations['row']['subtotal']+$calculations['row']['taxes']['total']);
				foreach($calculations['row']['taxes'] as $key => $value)
					$calculations['locale']['taxes'][$key] = $currency->toCurrency($value);
			return $calculations;
		}
	}
}
