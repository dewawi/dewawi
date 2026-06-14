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
