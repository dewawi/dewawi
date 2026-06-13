<?php

class Admin_FooterController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'footers',
			'list' => 'Admin_Model_List_Footers',
			'entity' => Admin_Model_Entity_Footer::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'templateid' => 0,
			'column' => 0,
			'text' => '',
			'width' => 0,
		];
	}
}
