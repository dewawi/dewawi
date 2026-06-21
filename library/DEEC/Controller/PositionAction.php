<?php

abstract class DEEC_Controller_PositionAction extends DEEC_Controller_Action
{
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

	protected function getPositionsDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName()
			. 'pos';
	}
}
