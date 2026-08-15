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
		return [
			'title' => $this->view->translate('ADMIN_NEW_WAREHOUSE'),
			'active' => 1,
			'isdefault' => 0,
		];
	}
}
