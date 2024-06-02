<?php

class Application_Controller_Action_Helper_Positions extends Zend_Controller_Action_Helper_Abstract
{
	public function getPositions($id, $quote, $locale)
	{
		//Get currency
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency($quote['currency'], 'USE_SYMBOL');

		$options = array();
		$optionSets = array();
		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->getPositions($id);
		if(count($positions)) {
			//Use price rules on all positions
			$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
			$price = $priceRuleHelper->usePriceRulesOnPositions($positions, 'sales', 'quotepos');

			//Set precision and currency
			foreach($positions as $position) {
				$precision = (floor($position->quantity) == $position->quantity) ? 0 : 2;
				$position->total = $currency->toCurrency($price['calculated'][$position->id]*$position->quantity);
				$position->price = $currency->toCurrency($price['calculated'][$position->id]);
				$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => $precision,'locale' => $locale));
			}

			$quote['taxes'] = $currency->toCurrency($quote['taxes']);
			$quote['subtotal'] = $currency->toCurrency($quote['subtotal']);
			$quote['total'] = $currency->toCurrency($quote['total']);
			if($quote['taxfree']) {
				$quote['taxrate'] = Zend_Locale_Format::toNumber(0, array('precision' => 2, 'locale' => $locale));
			} else {
				$quote['taxrate'] = Zend_Locale_Format::toNumber($positions[0]->taxrate, array('precision' => 2, 'locale' => $locale));
			}

			//Get options
			list($options, $optionSets) = $this->getOptions($positions, $price, $currency);
		}
		return array($positions, $quote, $options, $optionSets);
	}

	public function getOptions($positions, $price, $currency)
	{
		$options = [];
		$optionSets = [];
		$optionsDb = new Items_Model_DbTable_Itemopt();
		$optionSetsDb = new Items_Model_DbTable_Itemoptset();
		$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');

		foreach($positions as $position) {
			if($position->itemid && !$position->masterid) {
				$optionSets[$position->id] = $optionSetsDb->getPositionSets($position->itemid);
				foreach($optionSets[$position->id] as $optionSet) {
					$options[$position->id][$optionSet->id] = $optionsDb->getPositions($position->itemid, $optionSet->id);
					foreach($options[$position->id][$optionSet->id] as $key => $option) {
						$price = $this->calculatePrice($option, $position, $priceRuleHelper);
						$options[$position->id][$optionSet->id][$key]->price = $this->formatPrice($option, $price, $currency);
					}
				}
			}
		}

		return [$options, $optionSets];
	}

	private function calculatePrice($option, $position, $priceRuleHelper)
	{
		if($position->pricerulemaster) {
			$priceRules = $priceRuleHelper->getPriceRulePositions('sales', 'quotepos', $position->id);
			if(!in_array($option->price, [0, -1, -2])) {
				return $priceRuleHelper->usePriceRules($priceRules, $option->price);
			}
		}
		return $option->price;
	}

	private function formatPrice($option, $price, $currency)
	{
		if(in_array($option->price, [0, -1, -2])) {
			return $price;
		}
		return $currency->toCurrency($price);
	}
}
