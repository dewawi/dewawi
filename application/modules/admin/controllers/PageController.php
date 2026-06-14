<?php

class Admin_PageController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'pages',
			'list' => 'Admin_Model_List_Pages',
			'entity' => Admin_Model_Entity_Page::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_PAGE'),
			'shopid' => (int)$this->_getParam('shopid', 0),
			'parentid' => (int)$this->_getParam('parentid', 0),
			'type' => (string)$this->_getParam('type', ''),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$data['shopid'] = (int)($data['shopid'] ?? 0);
		$data['parentid'] = (int)($data['parentid'] ?? 0);
		$data['type'] = (string)($data['type'] ?? '');

		$db = new Admin_Model_DbTable_Page();

		$data['ordering'] = $db->getNextOrdering([
			'parentid' => $data['parentid'],
			'type' => $data['type'],
			'shopid' => $data['shopid'],
		]);

		return $data;
	}

	protected function prepareEditRow(array $row): array
	{
		if (empty($row['shopid']) || empty($row['id'])) {
			$row['slug'] = '';
			return $row;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slug = $slugDb->getSlug(
			'shops',
			'page',
			(int)$row['shopid'],
			(int)$row['id']
		);

		$row['slug'] = $slug['slug'] ?? '';

		return $row;
	}

	protected function afterCreate(int $id, array $data): void
	{
		if (empty($data['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->addSlug(
			'shops',
			'page',
			(int)$data['shopid'],
			(int)$data['parentid'],
			$id,
			$id
		);
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (empty($oldRow['shopid'])) {
			return;
		}

		if (!array_key_exists('slug', $values) && !array_key_exists('parentid', $values)) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->saveSlug(
			'shops',
			'page',
			(int)$oldRow['shopid'],
			(int)($values['parentid'] ?? $oldRow['parentid']),
			$id,
			(string)($values['slug'] ?? '')
		);
	}

	protected function afterDelete(int $id, array $row): void
	{
		if (empty($row['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->deleteSlug('shops', 'page', (int)$row['shopid'], $id);
	}
}
