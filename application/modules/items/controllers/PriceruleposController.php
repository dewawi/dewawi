<?php

class Items_PriceruleposController extends DEEC_Controller_Action
{
	protected function getPriceRuleDb(): Items_Model_DbTable_Pricerulepos
	{
		return new Items_Model_DbTable_Pricerulepos();
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

		try {
			$id = $db->createForParent(
				$parentId,
				$parentModule,
				$parentController,
				$data
			);
		} catch (Throwable $exception) {
			return $this->sendPriceRuleError('save_failed');
		}

		$row = $db->getById($id);

		if (!$row) {
			return $this->sendPriceRuleError('not_found');
		}

		$this->calculatePriceRuleParent($row);

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

		try {
			$db->updateById($id, $values);
		} catch (Throwable $exception) {
			return $this->sendPriceRuleError('save_failed');
		}

		$row = $db->getById($id);

		if (!$row) {
			return $this->sendPriceRuleError('not_found');
		}

		$this->calculatePriceRuleParent($row);

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

		try {
			$db->deletePosition($id);
		} catch (Throwable $exception) {
			return $this->sendPriceRuleError('delete_failed');
		}

		$this->calculatePriceRuleParent($row);

		return $this->sendSaveResponse();
	}

	protected function getCalculationContext(array $priceRule): array
	{
		$module = (string)$priceRule['module'];
		$positionController = (string)$priceRule['controller'];
		$documentController = substr($positionController, 0, -3);

		$className = DEEC_Util::dbTableClassFromModuleController(
			$module,
			$positionController
		);

		$positionDb = new $className();
		$position = $positionDb->getPosition((int)$priceRule['parentid']);

		return [
			'id' => (int)$position['parentid'],
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
