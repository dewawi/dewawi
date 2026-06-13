<?php

class Admin_TemplateController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'templates',
			'list' => 'Admin_Model_List_Templates',
			'entity' => Admin_Model_Entity_Template::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'description' => $this->view->translate('NEW_TEMPLATE'),
		];
	}
}
