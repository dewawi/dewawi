<?php

class Admin_ShippingmethodController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'shippingmethods',
			'list' => 'Admin_Model_List_Shippingmethods',
			'entity' => Admin_Model_Entity_Shippingmethod::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Shippingmethod();

		return [
			'title' => $this->view->translate('NEW_SHIPPING_METHOD'),
			'ordering' => $db->getNextOrdering(),
		];
	}
}
