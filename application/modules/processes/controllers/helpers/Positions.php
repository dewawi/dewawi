<?php

class Application_Controller_Action_Helper_Positions extends Zend_Controller_Action_Helper_Abstract
{
	public function getPositions($id, $document, $locale, $controller)
	{
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency($document['currency'], 'USE_SYMBOL');
		$positionsDb = $this->getDocumentDb($controller);
		$positions = $positionsDb->getPositions($id);
		$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate($document, $positions, 'processes', $controller);

		if (empty($document[$controller . 'id'])) {
			$document['subtotal'] = $calculation['row']['subtotal'];
			$document['taxes'] = $calculation['row']['taxes']['total'];
			$document['total'] = $calculation['row']['total'];
		}

		$document['taxgroups'] = [];
		if (abs((float)$document['taxes'] - $calculation['row']['taxes']['total']) < 0.005) {
			foreach ($calculation['locale']['taxes'] as $rate => $value) {
				if ($rate === 'total') continue;
				$document['taxgroups'][empty($document['taxfree']) ? $rate : 0] = $value;
			}
		}
		$rates = array_unique(array_map(static function ($position) { return (float)$position['taxrate']; }, $positions->toArray()));
		$document['taxrate'] = count($rates) === 1 || !empty($document['taxfree'])
			? Zend_Locale_Format::toNumber(empty($document['taxfree']) ? reset($rates) : 0, ['precision' => 2, 'locale' => $locale])
			: null;
		$document['total_raw'] = (float)$document['total'];
		$document['taxes'] = $currency->toCurrency($document['taxes']);
		$document['subtotal'] = $currency->toCurrency($document['subtotal']);
		$document['total'] = $currency->toCurrency($document['total']);

		foreach ($positions as $position) {
			$precision = floor($position->quantity) == $position->quantity ? 0 : (round($position->quantity, 2) == $position->quantity ? 2 : 4);
			$position->manufacturerid = $calculation['row'][$position->id]['price'];
			$position->total = $calculation['locale'][$position->id]['total'];
			$position->price = $calculation['locale'][$position->id]['price'];
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity, ['precision' => $precision, 'locale' => $locale]);
		}

		list($options, $optionSets) = $this->getOptions($positions, [], $currency, $controller . 'pos');
		return [$positions, $document, $options, $optionSets];
	}

	public function getOptions($positions, $price, $currency, $positionTable)
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
						$price = $this->calculatePrice($option, $position, $priceRuleHelper, $positionTable);
						$options[$position->id][$optionSet->id][$key]->price = $this->formatPrice($option, $price, $currency);
					}
				}
			}
		}

		return [$options, $optionSets];
	}

	private function calculatePrice($option, $position, $priceRuleHelper, $positionTable)
	{
		if($position->pricerulemaster) {
			$priceRules = $priceRuleHelper->getPriceRulePositions('processes', $positionTable, $position->id);
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

	protected function getDocumentDb(string $controller)
	{
		$class = 'Processes_Model_DbTable_' . ucfirst($controller) . 'pos';
		return new $class();
	}
}
