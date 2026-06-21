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
		$itemid = (int)$this->_getParam('itemid', 0);

		$data = [
			'docid' => 0,
			'doctype' => '',
			'language' => '',
			'type' => 'inflow',
			'warehouseid' => 0,
			'comment' => 'Booking ' . date('d.m.Y'),
			'ledgerdate' => date('Y-m-d'),
		];

		if ($itemid > 0) {
			$data['itemid'] = $itemid;
		}

		return $data;
	}

	protected function beforeCreate(array $data): array
	{
		if (empty($data['itemid']) && empty($data['sku'])) {
			return $data;
		}

		$stock = new Items_Service_Stock();

		return $stock->prepareCreateData($data);
	}

	protected function afterCreate(int $id, array $data): void
	{
		$ledger = $this->getDb()->getById($id);

		if (!$ledger) {
			throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
		}

		$stock = new Items_Service_Stock();
		$stock->apply($ledger);
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		if (isset($values['ledgerdate']) && strpos($values['ledgerdate'], '.') !== false) {
			$date = new Zend_Date($values['ledgerdate'], Zend_Date::DATES, 'de');
			$values['ledgerdate'] = $date->get('yyyy-MM-dd');
		}

		$stock = new Items_Service_Stock();
		$prepared = $stock->prepareCreateData(array_merge($row, $values));

		return array_intersect_key($prepared, $values + [
			'itemid' => null,
			'sku' => null,
			'warehouseid' => null,
		]);
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		$newRow = $this->getDb()->getById($id);

		if (!$newRow) {
			throw new Exception('MESSAGES_LEDGER_NOT_FOUND');
		}

		$stock = new Items_Service_Stock();

		$stock->revert($oldRow);
		$stock->apply($newRow);
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		if (!$newRow) {
			return;
		}

		$stock = new Items_Service_Stock();
		$stock->apply($newRow);
	}

	protected function afterDelete(int $id, array $row): void
	{
		$stock = new Items_Service_Stock();
		$stock->revert($row);
	}
}
