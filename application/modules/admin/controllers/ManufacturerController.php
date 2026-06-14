<?php

class Admin_ManufacturerController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'manufacturers',
			'list' => 'Admin_Model_List_Manufacturers',
			'entity' => Admin_Model_Entity_Manufacturer::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Manufacturer();

		return [
			'name' => $this->view->translate('ADMIN_NEW_MANUFACTURER'),
			'ordering' => $db->getNextOrdering(),
		];
	}
}
