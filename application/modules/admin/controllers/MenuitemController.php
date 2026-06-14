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
			'title' => $this->view->translate('ADMIN_NEW_MENU_ITEM'),
			'menuid' => (int)$this->_getParam('menuid', 0),
			'pageid' => (int)$this->_getParam('pageid', 0),
			'parentid' => (int)$this->_getParam('parentid', 0),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$data['menuid'] = (int)($data['menuid'] ?? 0);
		$data['parentid'] = (int)($data['parentid'] ?? 0);

		$db = new Admin_Model_DbTable_Menuitem();

		$data['ordering'] = $db->getNextOrdering([
			'menuid' => $data['menuid'],
			'parentid' => $data['parentid'],
		]);

		return $data;
	}

	protected function prepareEditRow(array $row): array
	{
		$shopId = $this->getShopIdByMenuId((int)($row['menuid'] ?? 0));

		if ($shopId <= 0 || empty($row['id'])) {
			$row['slug'] = '';
			return $row;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slug = $slugDb->getSlug(
			'shops',
			'menuitem',
			$shopId,
			(int)$row['id']
		);

		$row['slug'] = $slug['slug'] ?? '';

		return $row;
	}

	protected function afterCreate(int $id, array $data): void
	{
		$shopId = $this->getShopIdByMenuId((int)($data['menuid'] ?? 0));

		if ($shopId <= 0) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->addSlug(
			'shops',
			'menuitem',
			$shopId,
			(int)($data['parentid'] ?? 0),
			$id,
			$id
		);
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

		if (!array_key_exists('slug', $values) && !array_key_exists('parentid', $values)) {
			return;
		}

		$menuId = (int)($values['menuid'] ?? $oldRow['menuid']);
		$shopId = $this->getShopIdByMenuId($menuId);

		if ($shopId <= 0) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->saveSlug(
			'shops',
			'menuitem',
			$shopId,
			(int)($values['parentid'] ?? $oldRow['parentid']),
			$id,
			(string)($values['slug'] ?? '')
		);
	}

	protected function canDeleteRow(array $row): bool
	{
		return true;
	}

	protected function afterDelete(int $id, array $row): void
	{
		$shopId = $this->getShopIdByMenuId((int)($row['menuid'] ?? 0));

		if ($shopId <= 0) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->deleteSlug('shops', 'menuitem', $shopId, $id);
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

	protected function getShopIdByMenuId(int $menuId): int
	{
		if ($menuId <= 0) {
			return 0;
		}

		$menuDb = new Admin_Model_DbTable_Menu();
		$menu = $menuDb->getById($menuId);

		return (int)($menu['shopid'] ?? 0);
	}
}
