<?php

abstract class DEEC_Controller_AdminAction extends DEEC_Controller_Action
{
	protected function getAdminDb(): DEEC_Model_DbTable_Entity
	{
		$class = $this->getAdminDbClass();

		if (!class_exists($class)) {
			throw new RuntimeException('Admin DB class not found: ' . $class);
		}

		$db = new $class();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			throw new RuntimeException($class . ' must extend DEEC_Model_DbTable_Entity');
		}

		return $db;
	}

	public function sortAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$id = (int)$this->_getParam('id', 0);
		$targetOrdering = $this->_getParam('ordering', '');

		$db = $this->getAdminDb();
		$row = $db->getById($id);

		if (!$row) {
			return;
		}

		$parentField = $db->getParentField();
		$orderingField = $db->getOrderingField();

		if ($orderingField === null) {
			return;
		}

		$module = $row['module'] ?? $this->getRequest()->getModuleName();
		$controller = $row['controller'] ?? $this->getRequest()->getControllerName();
		$parentId = (int)($row[$parentField] ?? 0);

		$orderings = $this->getOrdering($parentId, $module, $controller);
		$currentOrdering = array_search($id, $orderings, true);

		if (!$currentOrdering) {
			return;
		}

		if ($targetOrdering === 'down' && isset($orderings[$currentOrdering + 1])) {
			$db->updateById($id, [$orderingField => $currentOrdering + 1]);
			$db->updateById((int)$orderings[$currentOrdering + 1], [$orderingField => $currentOrdering]);
		} elseif ($targetOrdering === 'up' && isset($orderings[$currentOrdering - 1])) {
			$db->updateById($id, [$orderingField => $currentOrdering - 1]);
			$db->updateById((int)$orderings[$currentOrdering - 1], [$orderingField => $currentOrdering]);
		} elseif ((int)$targetOrdering > 0) {
			$this->moveToOrdering($orderings, $id, $currentOrdering, (int)$targetOrdering);
		}

		$this->setOrdering($parentId, $module, $controller);
	}

	protected function moveToOrdering(array $orderings, int $id, int $currentOrdering, int $targetOrdering): void
	{
		if ($targetOrdering === $currentOrdering) {
			return;
		}

		$db = $this->getAdminDb();
		$orderingField = $db->getOrderingField();

		if ($orderingField === null) {
			return;
		}

		$db->updateById($id, [$orderingField => $targetOrdering]);

		foreach ($orderings as $ordering => $rowId) {
			if ((int)$rowId === $id) {
				continue;
			}

			if ($targetOrdering < $currentOrdering && $ordering >= $targetOrdering && $ordering < $currentOrdering) {
				$db->updateById((int)$rowId, [$orderingField => $ordering + 1]);
			}

			if ($targetOrdering > $currentOrdering && $ordering <= $targetOrdering && $ordering > $currentOrdering) {
				$db->updateById((int)$rowId, [$orderingField => $ordering - 1]);
			}
		}
	}

	protected function setOrdering(int $parentId, string $module, string $controller): void
	{
		$db = $this->getAdminDb();
		$orderingField = $db->getOrderingField();

		if ($orderingField === null) {
			return;
		}

		$items = $db->getByParentId($parentId, $module, $controller);

		$i = 1;

		foreach ($items as $item) {
			if (!empty($item['id'])) {
				$db->updateById((int)$item['id'], [$orderingField => $i]);
				$i++;
			}
		}
	}

	protected function getOrdering(int $parentId, string $module, string $controller): array
	{
		$db = $this->getAdminDb();
		$items = $db->getByParentId($parentId, $module, $controller);

		$orderings = [];
		$i = 1;

		foreach ($items as $item) {
			if (!empty($item['id'])) {
				$orderings[$i] = (int)$item['id'];
				$i++;
			}
		}

		return $orderings;
	}

	protected function getLatestOrdering(int $parentId, string $module, string $controller): int
	{
		$orderings = $this->getOrdering($parentId, $module, $controller);

		if (!$orderings) {
			return 0;
		}

		end($orderings);

		return (int)key($orderings);
	}

	protected function copyChilds(int $oldId, int $newId, string $module, string $controller): void
	{
		$db = $this->getAdminDb();
		$parentField = $db->getParentField();

		$children = $db->getByParentId($oldId, $module, $controller);

		foreach ($children as $child) {
			$oldChildId = (int)$child['id'];
			unset($child['id']);

			$child[$parentField] = $newId;
			$child['modified'] = null;
			$child['modifiedby'] = 0;
			$child['locked'] = 0;
			$child['lockedtime'] = null;

			$newChildId = $db->create($child);

			$this->afterCopyChild($child, $newChildId);

			$this->copyChilds($oldChildId, $newChildId, $module, $controller);
		}
	}

	protected function afterCopyChild(array $data, int $newId): void
	{
	}

	protected function getSubfolders($directory): array
	{
		$subfolders = [];

		if (!is_dir($directory)) {
			return $subfolders;
		}

		$items = scandir($directory);

		if (!$items) {
			return $subfolders;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if (is_dir(rtrim($directory, '/') . '/' . $item)) {
				$subfolders[] = $item;
			}
		}

		return $subfolders;
	}
}
