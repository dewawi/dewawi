<?php

class Items_Model_List_Stocks extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'sku',
				'label' => 'ITEMS_SKU',
				'type' => 'text',
				'class' => 'dw-col-sku',
			],
			[
				'name' => 'itemtitle',
				'label' => 'ITEMS_TITLE',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'warehousecode',
				'label' => 'ITEMS_WAREHOUSE_CODE',
				'type' => 'text',
			],
			[
				'name' => 'warehousetitle',
				'label' => 'ITEMS_WAREHOUSE',
				'type' => 'text',
			],
			[
				'name' => 'quantity',
				'label' => 'ITEMS_STOCK_QUANTITY',
				'type' => 'text',
			],
			[
				'name' => 'reserved',
				'label' => 'ITEMS_STOCK_RESERVED',
				'type' => 'text',
			],
			[
				'name' => 'available',
				'label' => 'ITEMS_STOCK_AVAILABLE',
				'type' => 'text',
			],
			[
				'name' => 'incoming',
				'label' => 'ITEMS_STOCK_INCOMING',
				'type' => 'text',
			],
		];
	}
}
