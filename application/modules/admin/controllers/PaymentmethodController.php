<?php

class Admin_PaymentmethodController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'paymentmethods',
			'list' => 'Admin_Model_List_Paymentmethods',
			'entity' => Admin_Model_Entity_Paymentmethod::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Paymentmethod();

		return [
			'title' => $this->view->translate('ADMIN_NEW_PAYMENT_METHOD'),
			'ordering' => $db->getNextOrdering(),
		];
	}
}
