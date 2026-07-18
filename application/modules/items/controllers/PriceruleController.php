<?php

class Items_PriceruleController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'pricerules',
			'list' => 'Items_Model_List_Pricerules',
			'entity' => Items_Model_Entity_Pricerule::listConfig(),
		]);
	}
}
