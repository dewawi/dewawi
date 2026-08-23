<?php

class Items_Service_Stock
{
	protected Items_Model_DbTable_Item $_itemDb;
	protected Items_Model_DbTable_Itemstock $_stockDb;

	public function __construct()
	{
		$this->_itemDb = new Items_Model_DbTable_Item();
		$this->_stockDb = new Items_Model_DbTable_Itemstock();
	}

	public function prepareCreateData(array $data): array
	{
		$item = $this->findItem($data);

		$data['itemid'] = (int)$item['id'];

		unset($data['sku']);

		if(empty($data['ledgerdate'])) {
			$data['ledgerdate'] = date('Y-m-d H:i:s');
		}

		$this->validate($data);

		return $data;
	}

	public function apply(array $ledger): void
	{
		$quantity = $this->getSignedQuantity($ledger);

		$this->_stockDb->changeQuantity(
			(int)$ledger['itemid'],
			(int)$ledger['warehouseid'],
			$quantity
		);
	}

	public function revert(array $ledger): void
	{
		$quantity = -$this->getSignedQuantity($ledger);

		$this->_stockDb->changeQuantity(
			(int)$ledger['itemid'],
			(int)$ledger['warehouseid'],
			$quantity
		);
	}

	protected function findItem(array $data): array
	{
		if(!empty($data['sku'])) {
			$item = $this->_itemDb->getItemBySKU(
				$data['sku']
			);

			if($item) {
				return $item;
			}
		}

		if(!empty($data['itemid'])) {
			$item = $this->_itemDb->getById(
				(int)$data['itemid']
			);

			if($item) {
				return $item;
			}
		}

		throw new Exception('MESSAGES_ITEM_NOT_FOUND');
	}

	public function resolveItem(array $data): int
	{
		$item = $this->findItem($data);

		return (int)$item['id'];
	}

	protected function validate(array $data): void
	{
		if(
			empty($data['type'])
			|| !in_array(
				$data['type'],
				['inflow', 'outflow'],
				true
			)
		) {
			throw new Exception(
				'MESSAGES_INVALID_LEDGER_TYPE'
			);
		}

		if(
			empty($data['quantity'])
			|| (float)$data['quantity'] <= 0
		) {
			throw new Exception(
				'MESSAGES_INVALID_LEDGER_QUANTITY'
			);
		}

		if(empty($data['warehouseid'])) {
			throw new Exception(
				'MESSAGES_WAREHOUSE_REQUIRED'
			);
		}

		if(empty($data['reason'])) {
			throw new Exception(
				'MESSAGES_LEDGER_REASON_REQUIRED'
			);
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
