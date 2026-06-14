<?php

class Admin_TaxrateController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'taxrates',
			'list' => 'Admin_Model_List_Taxrates',
			'entity' => Admin_Model_Entity_Taxrate::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Taxrate();

		return [
			'name' => $this->view->translate('ADMIN_NEW_TAX_RATE'),
			'rate' => 0,
			'ordering' => $db->getNextOrdering(),
		];
	}
}
