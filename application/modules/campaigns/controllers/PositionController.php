<?php

class Campaigns_PositionController extends DEEC_Controller_PositionAction
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
						'campaigns',
						$params['parent']
							. $params['type'],
						$positionId
					);

			$priceRuleMasters[$positionId] =
				(bool)$position->pricerulemaster;
		}

		return [
			'priceRules' => $priceRules,
			'priceRuleMasters' =>
				$priceRuleMasters,
		];
	}

	protected function afterPositionCopy(
		int $oldId,
		int $newId,
		array $params
	): void {
		$priceRules =
			$this->_helper->PriceRule
				->getPriceRulePositions(
					'campaigns',
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
