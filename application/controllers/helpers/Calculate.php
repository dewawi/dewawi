<?php

class Application_Controller_Action_Helper_Calculate extends Zend_Controller_Action_Helper_Abstract
{
	public function direct(int $id, string $module, string $controller)
	{
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
		$classPos = $class . 'pos';

		if (!class_exists($class) || !class_exists($classPos)) {
			throw new RuntimeException(
				'Calculation classes not found: ' . $class . ', ' . $classPos
			);
		}

		$objectDb = new $class();
		$object = $objectDb->getById($id);

		if ($object === null) {
			throw new RuntimeException(
				'Calculation parent not found: ' . $id
			);
		}

		$positionsDb = new $classPos();
		$positions = $positionsDb->getPositions($id);
		$calculations = $this->calculate($object, $positions, $module, $controller);

		foreach ($positions as $position) {
			$positionsDb->updatePosition($position->id, ['total' => $calculations['row'][$position->id]['gross']]);
		}

		$objectDb->updateById($id, [
			'subtotal' => $calculations['row']['subtotal'],
			'taxes'    => $calculations['row']['taxes']['total'],
			'total'    => $calculations['row']['total'],
		]);

		return $calculations;
	}

	public function calculate(array $object, $positions, string $module, string $controller): array
	{
		$calculations = ['row' => ['subtotal' => 0, 'taxes' => []], 'locale' => []];
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency($object['currency'], 'USE_SYMBOL');
		$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
		$prices = $priceRuleHelper->usePriceRulesOnPositions($positions, $module, $controller . 'pos');

		foreach ($positions as $position) {
			$price = $prices['calculated'][$position->id];
			$net = round($price * $position->quantity, 2);
			$rate = empty($object['taxfree']) ? (float)$position->taxrate : 0;
			$tax = $net * $rate / 100;
			// String keys preserve fractional tax rates instead of truncating them to integers.
			$rateKey = (string)(float)$position->taxrate;
			$calculations['row'][$position->id] = ['price' => $price, 'total' => $net, 'gross' => round($net + $tax, 2)];
			$calculations['row']['subtotal'] += $net;
			$calculations['row']['taxes'][$rateKey] = ($calculations['row']['taxes'][$rateKey] ?? 0) + $tax;
			$calculations['locale'][$position->id] = [
				'price' => $currency->toCurrency($price, ['precision' => round($price, 2) == $price ? 2 : 4]),
				'total' => $currency->toCurrency($net),
			];
		}

		// Round each tax group before adding it to the document total.
		$calculations['row']['taxes'] = array_map(static function ($tax) { return round($tax, 2); }, $calculations['row']['taxes']);
		$calculations['row']['taxes']['total'] = round(array_sum($calculations['row']['taxes']), 2);
		$calculations['row']['subtotal'] = round($calculations['row']['subtotal'], 2);
		$calculations['row']['total'] = round($calculations['row']['subtotal'] + $calculations['row']['taxes']['total'], 2);
		$calculations['locale']['subtotal'] = $currency->toCurrency($calculations['row']['subtotal']);
		$calculations['locale']['total'] = $currency->toCurrency($calculations['row']['total']);
		foreach ($calculations['row']['taxes'] as $key => $value) {
			$calculations['locale']['taxes'][$key] = $currency->toCurrency($value);
		}
		return $calculations;
	}
}
