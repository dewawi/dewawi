<?php

class Items_Service_Stock
{
	protected Items_Model_DbTable_Item $_itemDb;
	protected Items_Model_DbTable_Ledger $_ledgerDb;

	public function __construct()
	{
		$this->_itemDb = new Items_Model_DbTable_Item();
		$this->_ledgerDb = new Items_Model_DbTable_Ledger();
	}

	public function createLedger(array $data): int
	{
		$adapter = $this->_ledgerDb->getAdapter();

		try {
			$adapter->beginTransaction();

			$data = $this->prepareLedgerData($data);

			$id = $this->_ledgerDb->create($data);
			$ledger = $this->_ledgerDb->getById($id);

			if (!$ledger) {
				throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
			}

			$this->apply($ledger);

			$adapter->commit();

			return $id;
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function updateLedger(int $id, array $data): array
	{
		$adapter = $this->_ledgerDb->getAdapter();

		try {
			$adapter->beginTransaction();

			$oldLedger = $this->_ledgerDb->getById($id);

			if (!$oldLedger) {
				throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
			}

			$data = $this->prepareLedgerData(array_merge($oldLedger, $data));
			$data = array_intersect_key($data, $data);

			$this->_ledgerDb->updateById($id, $data);

			$newLedger = $this->_ledgerDb->getById($id);

			if (!$newLedger) {
				throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
			}

			$this->revert($oldLedger);
			$this->apply($newLedger);

			$adapter->commit();

			return $newLedger;
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function deleteLedger(int $id): void
	{
		$adapter = $this->_ledgerDb->getAdapter();

		try {
			$adapter->beginTransaction();

			$ledger = $this->_ledgerDb->getById($id);

			if (!$ledger) {
				throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
			}

			$this->revert($ledger);
			$this->_ledgerDb->deleteById($id);

			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function copyLedger(int $id): int
	{
		$adapter = $this->_ledgerDb->getAdapter();

		try {
			$adapter->beginTransaction();

			$newId = $this->_ledgerDb->copyById($id);
			$newLedger = $this->_ledgerDb->getById($newId);

			if (!$newLedger) {
				throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
			}

			$this->apply($newLedger);

			$adapter->commit();

			return $newId;
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function prepareLedgerData(array $data): array
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

			if ($item) {
				return $item;
			}
		}

		if (!empty($data['sku'])) {
			$item = $this->_itemDb->getItemBySKU($data['sku']);

			if ($item) {
				return $item;
			}
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
