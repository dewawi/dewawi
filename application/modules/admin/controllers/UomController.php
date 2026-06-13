<?php

class Admin_UomController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'uoms',
			'list' => 'Admin_Model_List_Uoms',
			'entity' => Admin_Model_Entity_Uom::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('NEW_UOM'),
		];
	}
}
