<?php

class Admin_CurrencyController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'currencies',
			'list' => 'Admin_Model_List_Currencies',
			'entity' => Admin_Model_Entity_Currency::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Currency();

		return [
			'code' => '---',
			'name' => $this->view->translate('ADMIN_NEW_CURRENCY'),
			'symbol' => '',
			'ordering' => $db->getNextOrdering(),
		];
	}
}
