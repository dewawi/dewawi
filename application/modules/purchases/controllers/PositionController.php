<?php

class Purchases_PositionController extends DEEC_Controller_PositionAction
{
	protected function buildPositionIndexContext(
		array $params,
		array $parent,
		$positions
	): array {
		$priceRules = [];
		$priceRuleMasters = [];

		foreach ($positions as $position) {
			if ($position->masterid) {
				continue;
			}

			$positionId = (int)$position->id;

			$priceRules[$positionId] =
				$this->_helper->PriceRule
					->getPriceRulePositions(
						'purchases',
						$params['parent']
							. $params['type'],
						$positionId
					);

			$priceRuleMasters[$positionId] =
				(bool)$position->pricerulemaster;
		}

		$context = [
			'priceRules' => $priceRules,
			'priceRuleMasters' => $priceRuleMasters,
			'calculations' => [],
		];

		$currencyHelper = $this->_helper->Currency;
		$currency = $currencyHelper->getCurrency();
		$locale = Zend_Registry::get('Zend_Locale');

		foreach ($positions as $position) {
			$positionId = (int)$position->id;
			$costPrice = (float)$position->cost;

			if ($costPrice <= 0) {
				$context['calculations'][$positionId] = null;
				continue;
			}

			$quantity = (float)$position->quantity;

			$revenue =
				$this->getPositionDisplayPrice(
				    $position,
				    $context
				) * $quantity;

			$cost = $costPrice * $quantity;
			$profit = $revenue - $cost;

			$margin = $revenue > 0
				? ($profit / $revenue) * 100
				: null;

			$currencyHelper->setCurrency(
				$currency,
				$position->currency,
				'USE_SYMBOL'
			);

			$context['calculations'][$positionId] = [
				'cost' => $currency->toCurrency($cost),
				'profit' => $currency->toCurrency($profit),
				'margin' => $margin !== null
				    ? Zend_Locale_Format::toNumber(
				        $margin,
				        [
				            'precision' => 2,
				            'locale' => $locale,
				        ]
				    )
				    : null,
			];
		}

		return $context;
	}

	protected function afterPositionCopy(
		int $oldId,
		int $newId,
		array $params
	): void {
		$priceRules =
			$this->_helper->PriceRule
				->getPriceRulePositions(
					'purchases',
					$params['parent']
						. $params['type'],
					$oldId
				);

		if (!$priceRules) {
			return;
		}

		$priceRuleDb =
			new Items_Model_DbTable_Pricerulepos();

		foreach ($priceRules as $priceRule) {
			unset($priceRule['id']);

			$priceRule['parentid'] = $newId;

			$priceRuleDb->create(
				$priceRule
			);
		}
	}
}
