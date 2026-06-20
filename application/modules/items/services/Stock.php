<?php

class Items_Service_Stock
{
	protected Items_Model_DbTable_Item $_itemDb;

	public function __construct()
	{
		$this->_itemDb = new Items_Model_DbTable_Item();
	}

	public function prepareCreateData(array $data): array
	{
		$item = $this->findItem($data);

		$data['itemid'] = (int)$item['id'];
		$data['sku'] = $item['sku'];

		if (empty($data['warehouseid'])) {
			$data['warehouseid'] = (int)$item['warehouseid'];
		}

		if (empty($data['ledgerdate'])) {
			$data['ledgerdate'] = date('Y-m-d');
		}

		$this->validate($data);

		return $data;
	}

	public function apply(array $ledger): void
	{
		$this->_itemDb->changeQuantity(
			(int)$ledger['itemid'],
			$this->getSignedQuantity($ledger)
		);
	}

	public function revert(array $ledger): void
	{
		$this->_itemDb->changeQuantity(
			(int)$ledger['itemid'],
			-$this->getSignedQuantity($ledger)
		);
	}

	protected function findItem(array $data): array
	{
		if (!empty($data['itemid'])) {
			$item = $this->_itemDb->getById((int)$data['itemid']);
			if ($item) return $item;
		}

		if (!empty($data['sku'])) {
			$item = $this->_itemDb->getItemBySKU($data['sku']);
			if ($item) return $item;
		}

		throw new Exception('MESSAGES_ITEM_NOT_FOUND');
	}

	protected function validate(array $data): void
	{
		if (empty($data['type']) || !in_array($data['type'], ['inflow', 'outflow'], true)) {
			throw new Exception('MESSAGES_INVALID_LEDGER_TYPE');
		}

		if (empty($data['quantity']) || (float)$data['quantity'] <= 0) {
			throw new Exception('MESSAGES_INVALID_LEDGER_QUANTITY');
		}
	}

	protected function getSignedQuantity(array $ledger): float
	{
		$quantity = (float)$ledger['quantity'];

		return $ledger['type'] === 'outflow'
			? -$quantity
			: $quantity;
	}
}
