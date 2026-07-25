<?php

abstract class DEEC_Controller_PositionAction extends DEEC_Controller_Action
{
	protected function getPositionModule(): string
	{
		return $this->getRequest()->getModuleName();
	}

	protected function getPositionParams(): array
	{
		return [
			'parent' => (string)$this->_getParam('parent'),
			'type' => (string)$this->_getParam('type', 'pos'),
			'parentid' => (int)$this->_getParam('parentid'),
			'setid' => (int)$this->_getParam('setid', 0),
			'masterid' => $this->normalizeMasterId(
				$this->_getParam('masterid')
			),
		];
	}

	protected function getParentDb()
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
		);

		return new $className();
	}

	protected function getPositionDb(): DEEC_Model_DbTable_Position
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent'] . $params['type']
		);

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Position) {
			throw new RuntimeException(
				$className
				. ' must extend DEEC_Model_DbTable_Position'
			);
		}

		return $db;
	}

	protected function getPositionSetDb()
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
			. $params['type']
			. 'set'
		);

		return new $className();
	}

	protected function buildDbTableClass(
		string $name
	): string {
		return ucfirst($this->getPositionModule())
			. '_Model_DbTable_'
			. ucfirst($name);
	}

	protected function normalizeMasterId($masterId): ?int
	{
		$masterId = (int)$masterId;

		return $masterId > 0
			? $masterId
			: null;
	}

	protected function disablePositionRendering(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyPositions($oldId, $newId);
	}

	protected function copyPositions(int $oldId, int $newId): void
	{
		$positionsDbClass = $this->getPositionsDbTableClass();

		if(!class_exists($positionsDbClass)) {
			return;
		}

		$positionsDb = new $positionsDbClass();

		if(!method_exists($positionsDb, 'getPositions')) {
			return;
		}

		$positions = $positionsDb->getPositions($oldId);

		$this->_helper->Position->copyPositions(
			$positions,
			$newId,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName(),
			$this->_date
		);
	}

	public function sortAction()
	{
		$this->disablePositionRendering();

		if (!$this->getRequest()->isPost()) {
			return $this->sendSortResponse(
				false,
				'Invalid request method'
			);
		}

		$id = (int)$this->_getParam('id', 0);
		$direction = (string)$this->_getParam(
			'direction',
			''
		);
		$ordering = (int)$this->_getParam(
			'ordering',
			0
		);

		if ($id < 1) {
			return $this->sendSortResponse(
				false,
				'Position ID is missing'
			);
		}

		$positionDb = $this->getPositionDb();

		if (
			$direction === 'up'
			|| $direction === 'down'
		) {
			$sorted = $positionDb->moveOrdering(
				$id,
				$direction
			);
		} elseif ($ordering > 0) {
			$sorted = $positionDb->moveToOrdering(
				$id,
				$ordering
			);
		} else {
			return $this->sendSortResponse(
				false,
				'Invalid sort target'
			);
		}

		return $this->sendSortResponse(
			$sorted,
			$sorted
				? null
				: 'Position could not be sorted'
		);
	}

	protected function sendSortResponse(
		bool $success,
		?string $message = null
	) {
		return $this->_helper->json([
			'ok' => $success,
			'message' => $message,
		]);
	}

	public function deleteAction()
	{
		$this->disablePositionRendering();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$data = $this->getRequest()->getPost();

		if (($data['delete'] ?? null) !== 'Yes') {
			return;
		}

		$ids = $data['id'] ?? [];

		if (!is_array($ids)) {
			$ids = [$ids];
		}

		$positionDb = $this->getPositionDb();
		$positionDb->deletePositions($ids);

		$params = $this->getPositionParams();

		$calculations = $this->_helper->Calculate(
			$params['parentid'],
			$this->_date,
			$this->_user['id']
		);

		return $this->_helper->json(
			$calculations['locale']
		);
	}

	protected function getPositionsDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName()
			. 'pos';
	}
}
