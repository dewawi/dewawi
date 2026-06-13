<?php

class Admin_CountryController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'countries',
			'list' => 'Admin_Model_List_Countries',
			'entity' => Admin_Model_Entity_Country::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'code' => '--',
			'name' => $this->view->translate('NEW_COUNTRY'),
			'language' => (string) Zend_Registry::get('Zend_Locale'),
		];
	}
}
