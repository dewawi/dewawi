<?php

class Admin_ModuleController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'modules',
			'list' => 'Admin_Model_List_Modules',
			'entity' => Admin_Model_Entity_Module::listConfig(),
		]);
	}
}
