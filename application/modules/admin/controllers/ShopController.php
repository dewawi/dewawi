<?php

class Admin_ShopController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'shops',
			'list' => 'Admin_Model_List_Shops',
			'entity' => Admin_Model_Entity_Shop::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_SHOP'),
		];
	}

	protected function afterCreate(int $id, array $data): void
	{
		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->create([
			'shopid' => $id,
			'parentid' => 0,
			'type' => 'home',
			'title' => $this->view->translate('ADMIN_HOME_PAGE'),
			'ordering' => $pageDb->getNextOrdering([
				'shopid' => $id,
				'parentid' => 0,
				'type' => 'home',
			]),
			'activated' => 1,
		]);

		$menuDb = new Admin_Model_DbTable_Menu();

		foreach ([
			'header' => 'ADMIN_HEADER_MENU',
			'header-actions' => 'ADMIN_HEADER_ACTIONS_MENU',
			'footer' => 'ADMIN_FOOTER_MENU',
		] as $position => $title) {
			$menuDb->create([
				'shopid' => $id,
				'title' => $this->view->translate($title),
				'position' => $position,
				'ordering' => $menuDb->getNextOrdering([
					'shopid' => $id,
					'position' => $position,
				]),
				'activated' => 1,
			]);
		}
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByShopID($oldId);

		if (!$config) {
			return;
		}

		unset($config['id']);

		$config['shopid'] = $newId;
		$config['modified'] = null;
		$config['modifiedby'] = 0;
		$config['locked'] = 0;
		$config['lockedtime'] = null;

		$configDb->addConfig($config);
	}
}
