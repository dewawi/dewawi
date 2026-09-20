<?php

class Admin_PageController extends DEEC_Controller_AdminAction
{
	protected int $shopId = 0;

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

	protected function getEditForm(): array
	{
		$formData = parent::getEditForm();

		if ($this->shopId <= 0) {
			return $formData;
		}

		$pageDb = new Admin_Model_DbTable_Page();
		$options = ['0' => 'ADMIN_MAIN_PAGE'] + $pageDb->getSelectOptions($this->shopId, (int)$this->view->id);

		$formData['form']->addOptions('parentid', $options, 'replace');
		$formData['options']['parentid'] = $options;

		return $formData;
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
		$this->shopId = (int)($row['shopid'] ?? 0);

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
		if (empty($data['shopid']) || ($data['type'] ?? '') === 'home') {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->saveSlug(
			'shops',
			'page',
			(int)$data['shopid'],
			(int)$data['parentid'],
			$id,
			(string)$id
		);
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (!array_intersect_key($values, array_flip(['slug', 'title', 'type', 'parentid', 'shopid']))) {
			return;
		}

		$type = (string)($values['type'] ?? $oldRow['type'] ?? '');
		$shopId = (int)($values['shopid'] ?? $oldRow['shopid'] ?? 0);
		$parentId = (int)($values['parentid'] ?? $oldRow['parentid'] ?? 0);
		$slugDb = new Admin_Model_DbTable_Slug();

		if ($type === 'home' || $shopId <= 0) {
			$slugDb->saveSlug('shops', 'page', 0, 0, $id);
			return;
		}

		$existing = $slugDb->getEntitySlug('shops', 'page', $id);
		$slug = null;

		if (array_key_exists('slug', $values)) {
			$slug = DEEC_Filter::slug((string)$values['slug']);
		} elseif (!$existing || empty($existing['slug']) || (string)$existing['slug'] === (string)$id) {
			$title = (string)($values['title'] ?? $oldRow['title'] ?? '');
			if ($title !== '') $slug = DEEC_Filter::slug($title);
		}

		$slugDb->saveSlug('shops', 'page', $shopId, $parentId, $id, $slug);
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
