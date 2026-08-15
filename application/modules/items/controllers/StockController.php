<?php

class Items_StockController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'stocks',
			'list' => 'Items_Model_List_Stocks',
			'entity' => Items_Model_Entity_Stock::listConfig(),
		]);
	}
}
