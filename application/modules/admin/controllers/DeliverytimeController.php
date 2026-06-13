<?php

class Admin_DeliverytimeController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'deliverytimes',
			'list' => 'Admin_Model_List_Deliverytimes',
			'entity' => Admin_Model_Entity_Deliverytime::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Deliverytime();

		return [
			'title' => $this->view->translate('NEW_DELIVERY_TIME'),
			'ordering' => $db->getNextOrdering(),
		];
	}
}
