<?php

class Items_StockplanningController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'stockplannings',
			'list' => 'Items_Model_List_Stockplannings',
			'entity' => Items_Model_Entity_Stockplanning::listConfig(),
			'toolbar' => 'Items_Form_ToolbarStockplanning',
		]);
	}

	protected function getCreateData(): array
	{
		$type = (string)$this->_getParam('type', 'incoming');
		if(!in_array($type, ['incoming', 'reservation'], true)) $type = 'incoming';
		$warehouse = (new Application_Model_DbTable_Warehouse())->getDefault();
		$itemId = max(0, (int)$this->_getParam('itemid', 0));
		if($itemId > 0 && !(new Items_Model_DbTable_Item())->getById($itemId)) $itemId = 0;

		return [
			'type' => $type,
			'itemid' => $itemId,
			'warehouseid' => $warehouse ? (int)$warehouse['id'] : 0,
			'quantity' => 0,
			'fulfilled' => 0,
			'planningdate' => date('Y-m-d'),
			'dateconfirmed' => 0,
			'status' => 'draft',
			'allowoverbooking' => 0,
		];
	}

	protected function prepareEditRow(array $row): array
	{
		if(!empty($row['itemid'])) {
			$item = (new Items_Model_DbTable_Item())->getById((int)$row['itemid']);
			if($item) $row['sku'] = $item['sku'];
		}
		return $row;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$locked = $this->getDb()->getByIdForUpdate((int)$row['id']);
		if(!$locked) throw new RuntimeException('ITEMS_PLANNING_NOT_FOUND');
		unset($values['fulfilled']);

		if(array_key_exists('sku', $values)) {
			$values['itemid'] = (new Items_Service_Stock())->resolveItem(array_replace($locked, $values));
			unset($values['sku']);
		}

		$combined = array_replace($locked, $values);
		if((float)$locked['fulfilled'] > 0 && (
			(int)$combined['itemid'] !== (int)$locked['itemid']
			|| (int)$combined['warehouseid'] !== (int)$locked['warehouseid']
			|| (string)$combined['type'] !== (string)$locked['type']
		)) {
			throw new RuntimeException('ITEMS_PLANNING_FULFILLED_IMMUTABLE');
		}
		if($locked['status'] === 'completed' && $combined['status'] !== 'completed') {
			throw new RuntimeException('ITEMS_PLANNING_COMPLETED_IMMUTABLE');
		}

		$availabilityFields = ['itemid', 'warehouseid', 'quantity', 'planningdate', 'status', 'type', 'allowoverbooking'];
		$checkAvailability = (bool)array_intersect(array_keys($values), $availabilityFields);
		$service = new Items_Service_StockPlanning();
		$service->lockScope((int)$combined['itemid'], (int)$combined['warehouseid']);
		$service->validate($combined, (int)$row['id'], $checkAvailability);
		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		$service = new Items_Service_StockPlanning();
		$service->sync((int)$oldRow['itemid'], (int)$oldRow['warehouseid']);
		$newRow = $this->getDb()->getById($id);
		if($newRow) $service->sync((int)$newRow['itemid'], (int)$newRow['warehouseid']);
	}

	protected function afterDelete(int $id, array $row): void
	{
		(new Items_Service_StockPlanning())->sync((int)$row['itemid'], (int)$row['warehouseid']);
	}

	protected function buildEditViewModel(int $id, array $row): array
	{
		$availability = null;
		if((int)$row['itemid'] > 0 && (int)$row['warehouseid'] > 0) {
			$availability = (new Items_Service_StockPlanning())->getAvailability(
				(int)$row['itemid'],
				(int)$row['warehouseid'],
				(string)($row['planningdate'] ?: date('Y-m-d')),
				$row['type'] === 'reservation' ? max(0, (float)$row['quantity'] - (float)$row['fulfilled']) : 0,
				$id
			);
		}
		return ['stockplanning' => $row, 'availability' => $availability];
	}

	public function applyAction()
	{
		$this->disableView();
		if(!$this->getRequest()->isPost()) return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_INVALID_REQUEST']);
		$id = (int)$this->_getParam('id', 0);
		$operation = (string)$this->_getParam('operation', '');
		$row = $this->getDb()->getById($id);
		if(!$row) return $this->_helper->json(['ok' => false, 'message' => 'ITEMS_PLANNING_NOT_FOUND']);

		try {
			if(in_array($operation, ['receive', 'fulfill'], true)) {
				$expected = $row['type'] === 'incoming' ? 'receive' : 'fulfill';
				if($operation !== $expected) throw new RuntimeException('ITEMS_PLANNING_INVALID_OPERATION');
				$quantity = Zend_Locale_Format::getNumber((string)$this->_getParam('quantity', ''), [
					'locale' => Zend_Registry::get('Zend_Locale'),
				]);
				$date = trim((string)$this->_getParam('ledgerdate', date('Y-m-d')));
				$parsed = DateTime::createFromFormat('!Y-m-d', $date);
				if(!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_DATE');
				$ledgerId = (new Items_Service_StockPlanning())->fulfill(
					$id,
					(float)$quantity,
					$date . ' ' . date('H:i:s'),
					$row['type'] === 'incoming' ? 'incoming' : 'reservation'
				);
				return $this->_helper->json(['ok' => true, 'ledgerid' => $ledgerId]);
			}

			if(!in_array($operation, ['cancel', 'release'], true)) throw new RuntimeException('ITEMS_PLANNING_INVALID_OPERATION');
			if($operation === 'release' && $row['type'] !== 'reservation') throw new RuntimeException('ITEMS_PLANNING_INVALID_OPERATION');
			$db = $this->getDb();
			$adapter = $db->getAdapter();
			$adapter->beginTransaction();
			try {
				$locked = $db->getByIdForUpdate($id);
				if(!$locked || !in_array($locked['status'], ['draft', 'planned'], true)) throw new RuntimeException('ITEMS_PLANNING_NOT_OPEN');
				if($operation === 'release' && $locked['type'] !== 'reservation') throw new RuntimeException('ITEMS_PLANNING_INVALID_OPERATION');
				$db->updateById($id, ['status' => $operation === 'release' ? 'released' : 'cancelled']);
				(new Items_Service_StockPlanning())->sync((int)$locked['itemid'], (int)$locked['warehouseid']);
				$adapter->commit();
			} catch(Throwable $exception) {
				$adapter->rollBack();
				throw $exception;
			}
			return $this->_helper->json(['ok' => true]);
		} catch(Throwable $exception) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate($exception->getMessage()),
			]);
		}
	}

	protected function handleEditAjaxSave(DEEC_Form $form, DEEC_Model_DbTable_Entity $db, int $id, array $row)
	{
		$this->disableView();
		$post = (array)$this->getRequest()->getPost();
		if(!$form->isValidPartial($post)) {
			return $this->_helper->json(['ok' => false, 'errors' => $this->toErrorMessages($form->getErrors(), $form)]);
		}
		$values = $form->getFilteredValuesPartial($post);
		$adapter = $db->getAdapter();
		try {
			$adapter->beginTransaction();
			$values = $this->beforeEditSave($values, $row);
			$db->updateById($id, $values);
			$this->afterEditSave($id, $values, $row);
			$adapter->commit();
		} catch(Throwable $exception) {
			$adapter->rollBack();
			$message = $exception->getMessage();
			$shortage = null;
			if(strpos($message, 'ITEMS_PLANNING_SHORTAGE:') === 0) {
				$shortage = (float)substr($message, strlen('ITEMS_PLANNING_SHORTAGE:'));
				$message = $this->view->translate('ITEMS_PLANNING_SHORTAGE') . ': ' . $shortage;
			} else {
				$message = $this->view->translate($message);
			}
			return $this->_helper->json(['ok' => false, 'message' => $message, 'shortage' => $shortage]);
		}
		$newRow = $db->getById($id);
		$fields = array_keys($values);
		return $this->sendSaveResponse($id, array_intersect_key($newRow, array_flip($fields)), DEEC_Display::fromRow($form, $newRow, $fields));
	}
}
