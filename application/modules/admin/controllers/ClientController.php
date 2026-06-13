<?php

class Admin_ClientController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'clients',
			'list' => 'Admin_Model_List_Clients',
			'entity' => Admin_Model_Entity_Client::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'company' => $this->view->translate('NEW_CLIENT'),
		];
	}

	protected function afterCreate(int $id, array $data): void
	{
		$initializer = new Admin_Model_Service_ClientInitializer();
		$initializer->initialize($id);
	}
}
