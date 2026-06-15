<?php

class Admin_CategoryController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'categories',
			'list' => 'Admin_Model_List_Categories',
			'entity' => Admin_Model_Entity_Category::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_CATEGORY'),
			'parentid' => (int)$this->_getParam('parentid', 0),
			'shopid' => (int)$this->_getParam('shopid', 0),
			'type' => (string)$this->_getParam('type', ''),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$data['type'] = (string)($data['type'] ?? '');
		$data['parentid'] = (int)($data['parentid'] ?? 0);
		$data['shopid'] = (int)($data['shopid'] ?? 0);

		$db = new Admin_Model_DbTable_Category();

		$data['ordering'] = $db->getNextOrdering([
			'parentid' => $data['parentid'],
			'type' => $data['type'],
			'shopid' => $data['shopid'],
		]);

		return $data;
	}

	protected function afterCreate(int $id, array $data): void
	{
		if (empty($data['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->addSlug(
			'shops',
			'category',
			(int)$data['shopid'],
			(int)$data['parentid'],
			$id,
			$id
		);
	}

	protected function getEntityContext(array $row): array
	{
		$module = 'admin';

		if (($row['type'] ?? '') === 'shop') {
			$module = 'shops';
		}

		if (($row['type'] ?? '') === 'item') {
			$module = 'items';
		}

		if (($row['type'] ?? '') === 'contact') {
			$module = 'contacts';
		}

		return [
			'module' => $module,
			'controller' => 'category',
		];
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$row['parentid']) {
			$values['ordering'] = $this->getLatestOrdering(
				Admin_Model_DbTable_Category::class,
				'getCategories',
				'sortCategory',
				[(string)($row['type'] ?? ''), (int)$values['parentid'], (int)($row['shopid'] ?? 0)]
			) + 1;
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$oldRow['parentid']) {
			$this->resetOrdering(
				Admin_Model_DbTable_Category::class,
				'getCategories',
				'sortCategory',
				[(string)($oldRow['type'] ?? ''), (int)$oldRow['parentid'], (int)($oldRow['shopid'] ?? 0)]
			);

			if (!empty($oldRow['shopid'])) {
				$slugDb = new Admin_Model_DbTable_Slug();
				$slugDb->updateSlug(
					'shops',
					'category',
					(int)$oldRow['shopid'],
					(int)$values['parentid'],
					$id
				);
			}
		}

		if (array_key_exists('slug', $values) && !empty($oldRow['shopid'])) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->updateSlug(
				'shops',
				'category',
				(int)$oldRow['shopid'],
				(int)$oldRow['parentid'],
				$id,
				(string)$values['slug']
			);
		}
	}

	protected function canDeleteRow(array $row): bool
	{
		if (($row['type'] ?? '') === 'contact') {
			$db = new Contacts_Model_DbTable_Contact();
			return empty($db->getContactsByCategory((int)$row['id']));
		}

		if (($row['type'] ?? '') === 'item' || ($row['type'] ?? '') === 'shop') {
			$db = new Items_Model_DbTable_Item();
			return empty($db->getItemsByCategory((int)$row['id']));
		}

		return true;
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		if (!empty($newRow['shopid'])) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->addSlug('shops', 'category', (int)$newRow['shopid'], (int)$newRow['parentid'], $newId, $newId);
		}
	}

	protected function afterDelete(int $id, array $row): void
	{
		if (!empty($row['shopid'])) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->deleteSlug('shops', 'category', (int)$row['shopid'], $id);
		}
	}
}
