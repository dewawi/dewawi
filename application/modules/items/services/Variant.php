<?php

class Items_Service_Variant
{
	protected Items_Model_DbTable_Item $_itemDb;
	protected Items_Model_DbTable_Itemopt $_optionDb;
	protected Items_Model_DbTable_Itemvariantopt $_variantOptionDb;

	public function __construct()
	{
		$this->_itemDb = new Items_Model_DbTable_Item();
		$this->_optionDb = new Items_Model_DbTable_Itemopt();
		$this->_variantOptionDb = new Items_Model_DbTable_Itemvariantopt();
	}

	public function create(int $parentId): int
	{
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

		return $this->_itemDb->create(
			$data
		);
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

	public function addOption(
		int $itemId,
		int $optionId
	): array {
		$item = $this->_itemDb->getById(
			$itemId
		);

		if(!$item || empty($item['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
			);
		}

		$option = $this->_optionDb->getById(
			$optionId
		);

		if(
			!$option
			|| (int)$option['parentid']
				!== (int)$item['parentid']
		) {
			throw new Exception(
				'MESSAGES_ITEM_OPTION_INVALID'
			);
		}

		$this->_variantOptionDb->addOption(
			$itemId,
			$optionId
		);

		return $this->update(
			$itemId
		);
	}

	public function deleteOption(
		int $itemId,
		int $optionId
	): array {
		$item = $this->_itemDb->getById(
			$itemId
		);

		if(!$item || empty($item['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
			);
		}

		$this->_variantOptionDb->deleteOption(
			$itemId,
			$optionId
		);

		return $this->update(
			$itemId
		);
	}
}
