<?php

class Items_LedgerController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'ledgers',
			'list' => 'Items_Model_List_Ledgers',
			'entity' => Items_Model_Entity_Ledger::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$data = [
			'itemid' => 0,
			'type' => 'inflow',
			'reason' => 'correction',
			'quantity' => 0,
			'warehouseid' => 0,
			'ledgerdate' => date('Y-m-d H:i:s'),
		];

		$itemId = (int)$this->_getParam('itemid', 0);

		if($itemId > 0) {
			$data['itemid'] = $itemId;
		}

		return $data;
	}

	protected function beforeCreate(array $data): array
	{
		if(
			empty($data['itemid'])
			&& empty($data['sku'])
		) {
			return $data;
		}

		return $this->getStockService()
			->prepareCreateData($data);
	}

	protected function afterCreate(
		int $id,
		array $data
	): void {
		$ledger = $this->getDb()->getById($id);

		if(!$ledger) {
			throw new Exception(
				'MESSAGES_LEDGER_NOT_FOUND'
			);
		}

		$this->getStockService()->apply($ledger);
	}

	protected function beforeEditSave(
		array $values,
		array $row
	): array {
		if(array_key_exists('sku', $values)) {
			$values['itemid'] = $this->getStockService()
				->resolveItem($values);

			unset($values['sku']);
		}

		return $values;
	}

	protected function afterEditSave(
		int $id,
		array $values,
		array $oldRow
	): void {
		$stockFields = [
			'itemid',
			'warehouseid',
			'type',
			'quantity',
		];

		if(!array_intersect(array_keys($values), $stockFields)) {
			return;
		}

		$newRow = $this->getDb()->getById($id);

		if(!$newRow) {
			throw new Exception(
				'MESSAGES_LEDGER_NOT_FOUND'
			);
		}

		$stock = $this->getStockService();

		if($this->isStockBookingComplete($oldRow)) {
			$stock->revert($oldRow);
		}

		if($this->isStockBookingComplete($newRow)) {
			$stock->apply($newRow);
		}
	}

	protected function isStockBookingComplete(array $row): bool
	{
		return !empty($row['itemid'])
			&& !empty($row['warehouseid'])
			&& !empty($row['type'])
			&& (float)($row['quantity'] ?? 0) > 0;
	}

	protected function afterCopy(
		int $oldId,
		int $newId,
		array $oldRow,
		array $newRow
	): void {
		if(!$newRow) {
			return;
		}

		$this->getStockService()->apply($newRow);
	}

	protected function afterDelete(
		int $id,
		array $row
	): void {
		$this->getStockService()->revert($row);
	}

	protected function getStockService(): Items_Service_Stock
	{
		return new Items_Service_Stock();
	}
}
