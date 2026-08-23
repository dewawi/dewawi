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
		$warehouseDb = new Application_Model_DbTable_Warehouse();
		$warehouse = $warehouseDb->getDefault();

		$data = [
			'itemid' => 0,
			'type' => 'inflow',
			'reason' => 'correction',
			'quantity' => 0,
			'warehouseid' => $warehouse ? (int)$warehouse['id'] : 0,
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

	public function addAction()
	{
		$request = $this->getRequest();
		$data = $this->getCreateData();

		if(!$request->isPost()) {
			$formData = $this->getEditForm();
			$form = $formData['form'];

			$locale = Zend_Registry::get('Zend_Locale');

			$form->setValues(
				DEEC_Display::rowToFormValues(
					$form,
					$data,
					$locale
				)
			);

			$this->view->assign([
				'form' => $form,
				'options' => $formData['options'],
				'positions' => [
					[
						'itemid' => 0,
						'sku' => '',
						'quantity' => '',
					],
				],
			]);

			$this->assignMessages();

			return;
		}

		$post = (array)$request->getPost();
		$positions = (array)($post['positions'] ?? []);

		$common = [
			'warehouseid' => (int)($post['warehouseid'] ?? 0),
			'type' => (string)($post['type'] ?? ''),
			'reason' => (string)($post['reason'] ?? ''),
			'ledgerdate' => (string)($post['ledgerdate'] ?? ''),
			'comment' => (string)($post['comment'] ?? ''),
		];

		$db = $this->getDb();
		$adapter = $db->getAdapter();
		$stock = $this->getStockService();

		try {
			$adapter->beginTransaction();

			foreach($positions as $position) {

				if(
					empty($position['itemid'])
					&& trim((string)($position['sku'] ?? '')) === ''
					&& (float)($position['quantity'] ?? 0) === 0.0
				) {
					continue;
				}

				$ledger = array_merge($common, [
					'itemid' => (int)($position['itemid'] ?? 0),
					'sku' => trim((string)($position['sku'] ?? '')),
					'quantity' => $position['quantity'] ?? 0,
				]);

				$ledger = $stock->prepareCreateData($ledger);

				$id = $db->create($ledger);
				$row = $db->getById($id);

				if(!$row) {
					throw new Exception(
						'MESSAGES_LEDGER_NOT_FOUND'
					);
				}

				$stock->apply($row);
			}

			$adapter->commit();
		} catch(Exception $e) {
			$adapter->rollBack();
			throw $e;
		}

		return $this->_helper->redirector->gotoSimple(
			'index',
			'ledger'
		);
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

	protected function prepareEditRow(array $row): array
	{
		if(!empty($row['itemid'])) {
			$itemDb = new Items_Model_DbTable_Item();
			$item = $itemDb->getById((int)$row['itemid']);

			if($item) {
				$row['sku'] = $item['sku'];
			}
		}

		return $row;
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
