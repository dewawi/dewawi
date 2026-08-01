<?php

class Admin_WarehouseController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'warehouses',
			'list' => 'Admin_Model_List_Warehouses',
			'entity' => Admin_Model_Entity_Warehouse::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Warehouse();

		return [
			'name' => $this->view->translate('ADMIN_NEW_WAREHOUSE'),
			'ordering' => $db->getNextOrdering(),
		];
	}
}
