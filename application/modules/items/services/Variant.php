<?php

class Items_Service_Variant
{
	protected Items_Model_DbTable_Item $_itemDb;
	protected Items_Model_DbTable_Itemopt $_optionDb;
	protected Items_Model_DbTable_Itemoptset $_optionSetDb;
	protected Items_Model_DbTable_Itemvariantopt $_variantOptionDb;

	public function __construct()
	{
		$this->_itemDb = new Items_Model_DbTable_Item();
		$this->_optionDb = new Items_Model_DbTable_Itemopt();
		$this->_optionSetDb = new Items_Model_DbTable_Itemoptset();
		$this->_variantOptionDb = new Items_Model_DbTable_Itemvariantopt();
	}

	public function create(
		int $parentId,
		array $optionIds
	): int {
		$parent = $this->_itemDb->getById(
			$parentId
		);

		if(!$parent) {
			throw new Exception(
				'MESSAGES_ITEM_NOT_FOUND'
			);
		}

		if(!empty($parent['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
			);
		}

		$optionIds = $this->validateOptions(
			$parentId,
			$optionIds
		);

		$this->validateCombination(
			$parentId,
			$optionIds
		);

		$adapter = $this->_itemDb->getAdapter();
		$adapter->beginTransaction();

		try {
			$data = $parent;

			unset(
				$data['id'],
				$data['created'],
				$data['createdby'],
				$data['modified'],
				$data['modifiedby'],
				$data['locked'],
				$data['lockedtime']
			);

			$data['parentid'] = $parentId;
			$data['quantity'] = 0;

			$itemId = $this->_itemDb->create(
				$data
			);

			foreach($optionIds as $optionId) {
				$this->_variantOptionDb->addOption(
					$itemId,
					$optionId
				);
			}

			$this->update(
				$itemId
			);

			$adapter->commit();

			return $itemId;
		} catch(Throwable $exception) {
			$adapter->rollBack();

			throw $exception;
		}
	}

	protected function validateOptions(
		int $parentId,
		array $optionIds
	): array {
		$optionIds = array_values(
			array_unique(
				array_filter(
					array_map(
						'intval',
						$optionIds
					)
				)
			)
		);

		if(!$optionIds) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_OPTIONS_REQUIRED'
			);
		}

		foreach($optionIds as $optionId) {
			$option = $this->_optionDb->getById(
				$optionId
			);

			if(
				!$option
				|| (int)$option['parentid'] !== $parentId
			) {
				throw new Exception(
					'MESSAGES_ITEM_OPTION_INVALID'
				);
			}
		}

		sort($optionIds);

		return $optionIds;
	}

	protected function validateCombination(
		int $parentId,
		array $optionIds
	): void {
		foreach(
			$this->_itemDb->getVariants(
				$parentId
			) as $variant
		) {
			$currentOptionIds = [];

			foreach(
				$this->_variantOptionDb->getByItemId(
					(int)$variant->id
				) as $relation
			) {
				$currentOptionIds[] =
					(int)$relation->itemoptid;
			}

			sort($currentOptionIds);

			if($currentOptionIds === $optionIds) {
				throw new Exception(
					'MESSAGES_ITEM_VARIANT_EXISTS'
				);
			}
		}
	}

	public function syncInheritedFields(
		int $parentId,
		array $data
	): void {
		$parent = $this->_itemDb->getById(
			$parentId
		);

		if(!$parent) {
			throw new Exception(
				'MESSAGES_ITEM_NOT_FOUND'
			);
		}

		if(!empty($parent['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
			);
		}

		$data = array_intersect_key(
			$data,
			array_flip(
				Items_Model_Entity_Item::inheritedFields()
			)
		);

		if(!$data) {
			return;
		}

		foreach(
			$this->_itemDb->getVariants(
				$parentId
			) as $variant
		) {
			$this->_itemDb->updateById(
				(int)$variant->id,
				$data
			);
		}
	}

	public function calculate(int $itemId): array
	{
		$item = $this->_itemDb->getById(
			$itemId
		);

		if(!$item || empty($item['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
			);
		}

		$parent = $this->_itemDb->getById(
			(int)$item['parentid']
		);

		if(!$parent) {
			throw new Exception(
				'MESSAGES_ITEM_NOT_FOUND'
			);
		}

		$sku = (string)$parent['sku'];
		$price = (float)$parent['price'];

		foreach(
			$this->_variantOptionDb->getByItemId(
				$itemId
			) as $relation
		) {
			$option = $this->_optionDb->getById(
				(int)$relation->itemoptid
			);

			if(!$option) {
				continue;
			}

			if(
				(int)$option['parentid']
				!== (int)$item['parentid']
			) {
				continue;
			}

			if(!empty($option['sku'])) {
				$sku .= ' ' . trim(
					(string)$option['sku']
				);
			}

			$price += (float)$option['price'];
		}

		return [
			'sku' => trim($sku),
			'price' => $price,
		];
	}

	public function update(int $itemId): array
	{
		$data = $this->calculate(
			$itemId
		);

		$this->_itemDb->updateById(
			$itemId,
			[
				'sku' => $data['sku'],
				'price' => $data['price'],
			]
		);

		return $data;
	}

	public function getOptions(int $itemId): array
	{
		$options = [];

		foreach(
			$this->_variantOptionDb->getByItemId(
				$itemId
			) as $relation
		) {
			$option = $this->_optionDb->getById(
				(int)$relation->itemoptid
			);

			if($option) {
				$options[] = $option;
			}
		}

		return $options;
	}
}
