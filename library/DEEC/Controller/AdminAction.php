<?php

abstract class DEEC_Controller_AdminAction extends DEEC_Controller_Action
{
	protected function copyChilds(int $oldId, int $newId, string $module, string $controller): void
	{
		$db = $this->getDb();
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
