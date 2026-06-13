<?php

class Admin_MenuitemController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'menuitems',
			'list' => 'Admin_Model_List_Menuitems',
			'entity' => Admin_Model_Entity_Menuitem::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'menuid' => (int)$this->_getParam('menuid', 0),
			'pageid' => (int)$this->_getParam('pageid', 0),
			'parentid' => (int)$this->_getParam('parentid', 0),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$db = new Admin_Model_DbTable_Menuitem();

		$data['menuid'] = (int)($data['menuid'] ?? 0);
		$data['parentid'] = (int)($data['parentid'] ?? 0);

		$data['ordering'] = $db->getNextOrdering([
			'menuid' => $data['menuid'],
			'parentid' => $data['parentid'],
		]);

		return $data;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		if (array_key_exists('parentid', $values) && (int)$values['parentid'] !== (int)$row['parentid']) {
			$db = new Admin_Model_DbTable_Menuitem();

			$values['ordering'] = $db->getNextOrdering([
				'menuid' => (int)$row['menuid'],
				'parentid' => (int)$values['parentid'],
			]);
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (array_key_exists('parentid', $values) && (int)$values['parentid'] !== (int)$oldRow['parentid']) {
			$db = new Admin_Model_DbTable_Menuitem();
			$db->normalizeOrderingByRow($oldRow);
		}
	}

	protected function canDeleteRow(array $row): bool
	{
		return true;
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyMenuitemChildren($oldId, $newId);
	}

	protected function copyMenuitemChildren(int $oldParentId, int $newParentId): void
	{
		$db = new Admin_Model_DbTable_Menuitem();
		$children = $db->getChildren($oldParentId);

		foreach ($children as $child) {
			$oldChildId = (int)$child['id'];

			unset($child['id']);

			$child['parentid'] = $newParentId;
			$child['locked'] = 0;
			$child['lockedtime'] = null;
			$child['created'] = null;
			$child['createdby'] = 0;
			$child['modified'] = null;
			$child['modifiedby'] = 0;

			$newChildId = $db->create($child);

			$this->copyMenuitemChildren($oldChildId, $newChildId);
		}
	}
}
