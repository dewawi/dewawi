<?php

class Items_PriceruleposController extends DEEC_Controller_Action
{
	protected function getPriceRuleDb(): Items_Model_DbTable_Pricerulepos
	{
		return new Items_Model_DbTable_Pricerulepos();
	}

	protected function loadRow(int $id): ?array
	{
		$row = parent::loadRow($id);
		return $row && $this->canReadPriceRuleParent((int)$row['parentid'], (string)$row['module'], (string)$row['controller']) ? $row : null;
	}

	protected function canReadPriceRuleParent(int $id, string $module, string $controller): bool
	{
		return (new DEEC_Permission((array)$this->_user))->getObjectContext($id, $module, $controller) !== null;
	}

	protected function getRowsByParentId(int $parentId)
	{
		if (!$this->canReadPriceRuleParent($parentId, (string)$this->_getParam('parent_module', ''), (string)$this->_getParam('parent_controller', ''))) return $this->sendPriceRuleError('not_found');
		return parent::getRowsByParentId($parentId);
	}

	public function addAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPriceRuleError('Invalid request method');
		}

		$parentId = (int)$this->_getParam('parentid', 0);
		$parentModule = (string)$this->_getParam('parent_module', '');
		$parentController = (string)$this->_getParam('parent_controller', '');

		if ($parentId < 1) {
			return $this->sendPriceRuleError('Price rule parent ID is missing');
		}

		if ($parentModule === '' || $parentController === '') {
			return $this->sendPriceRuleError('Price rule parent context is missing');
		}

		$post = (array)$this->getRequest()->getPost();

		$data = [
			'action' => !empty($post['type'])
				? (string)$post['type']
				: 'bypercent',
			'masterid' => 0,
			'possetid' => 0,
		];

		$db = $this->getPriceRuleDb();
		$context = $this->getCalculationContext(['module' => $parentModule, 'controller' => $parentController, 'parentid' => $parentId]);
		$adapter = $db->getAdapter();
		$adapter->beginTransaction();

		try {
			$this->lockPriceRuleParent($context);
			$id = $db->createForParent(
				$parentId,
				$parentModule,
				$parentController,
				$data
			);
			$row = $db->getById($id);
			if (!$row) throw new RuntimeException('Price rule not found');
			$this->calculatePriceRuleParent($row);
			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			return $this->sendPriceRuleError('save_failed');
		}

		return $this->sendSaveResponse($id);
	}

	public function editAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPriceRuleError('Invalid request method');
		}

		$id = (int)$this->_getParam('id', 0);

		if ($id < 1) {
			return $this->sendPriceRuleError('Price rule ID is missing');
		}

		$db = $this->getPriceRuleDb();
		$row = $db->getById($id);

		if (!$row) {
			return $this->sendPriceRuleError('not_found');
		}

		$form = new Items_Form_Pricerulepos();
		$this->_helper->Options->applyFormOptions($form);

		$post = (array)$this->getRequest()->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);

		$context = $this->getCalculationContext($row);
		$adapter = $db->getAdapter();
		$adapter->beginTransaction();
		try {
			$this->lockPriceRuleParent($context);
			$db->updateById($id, $values);
			$row = $db->getById($id);
			if (!$row) throw new RuntimeException('Price rule not found');
			$this->calculatePriceRuleParent($row);
			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			return $this->sendPriceRuleError('save_failed');
		}

		$changedFields = array_keys($values);

		return $this->sendSaveResponse(
			$id,
			array_intersect_key($row, array_flip($changedFields)),
			DEEC_Display::fromRow($form, $row, $changedFields)
		);
	}

	public function deleteAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPriceRuleError('Invalid request method');
		}

		$id = (int)$this->_getParam('id', 0);

		if ($id < 1) {
			return $this->sendPriceRuleError('Price rule ID is missing');
		}

		$db = $this->getPriceRuleDb();
		$row = $db->getById($id);

		if (!$row) {
			return $this->sendPriceRuleError('not_found');
		}

		$context = $this->getCalculationContext($row);
		$adapter = $db->getAdapter();
		$adapter->beginTransaction();
		try {
			$this->lockPriceRuleParent($context);
			$db->deletePosition($id, false);
			$this->calculatePriceRuleParent($row);
			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			return $this->sendPriceRuleError('delete_failed');
		}

		return $this->sendSaveResponse();
	}

	protected function lockPriceRuleParent(array $context): void
	{
		$class = DEEC_Util::dbTableClassFromModuleController($context['module'], $context['controller']);
		$parent = (new $class())->setClientId($context['clientid'])->getByIdForUpdate($context['id']);
		if (!$parent || DEEC_Permission::isDocumentReadonly($context['module'], $context['controller'], $parent)
			|| ($context['module'] === 'processes' && in_array((int)($parent['state'] ?? 0), [105, 106], true))) {
			throw new RuntimeException('Price rule parent is not editable');
		}
	}

	protected function getCalculationContext(array $priceRule): array
	{
		$module = (string)$priceRule['module'];
		$positionController = (string)$priceRule['controller'];
		$documentController = substr($positionController, 0, -3);
		if (!DEEC_Permission::isPositionContext($module, $documentController, substr($positionController, -3))) throw new InvalidArgumentException('Invalid price rule parent');
		$context = (new DEEC_Permission((array)$this->_user))->getObjectContext($priceRule['parentid'], $module, $positionController, 'edit');
		if (!$context) throw new InvalidArgumentException('Invalid price rule parent');
		$position = $context['parent'];

		return [
			'id' => (int)$position['parentid'],
			'clientid' => $context['clientid'],
			'module' => $module,
			'controller' => $documentController,
		];
	}

	protected function calculatePriceRuleParent(array $priceRule): void
	{
		$context = $this->getCalculationContext($priceRule);

		$calculations = $this->_helper->Calculate(
			$context['id'],
			$context['module'],
			$context['controller']
		);

		$this->setSaveCalculation($calculations['locale']);
		$this->reloadPositionsAfterSave();
	}

	protected function sendPriceRuleError(string $message)
	{
		return $this->_helper->json([
			'ok' => false,
			'message' => $message,
		]);
	}
}
