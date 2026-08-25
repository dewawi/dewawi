<?php

class Items_Form_ToolbarStock extends Items_Form_Toolbar
{
	public function __construct()
	{
		parent::__construct();

		$this->addElement([
			'name' => 'stockdate',
			'type' => 'text',
			'label' => 'ITEMS_STOCK_DATE',
			'default' => '',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
		]);

		$this->addElement([
			'name' => 'warehouseid',
			'type' => 'select',
			'label' => 'ITEMS_WAREHOUSE',
			'default' => '0',
			'options' => [
				'0' => 'TOOLBAR_ALL',
			],
			'source' => 'warehouse',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'order',
			'type' => 'radio',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'modified',
			'options' => [
			    'modified' => 'TOOLBAR_MODIFIED',
			    'sku' => 'ITEMS_SKU',
			    'itemtitle' => 'ITEMS_TITLE',
			    'warehousecode' => 'ITEMS_WAREHOUSE_CODE',
			    'warehousetitle' => 'ITEMS_WAREHOUSE',
			    'quantity' => 'ITEMS_STOCK_QUANTITY',
			    'reserved' => 'ITEMS_STOCK_RESERVED',
			    'available' => 'ITEMS_STOCK_AVAILABLE',
			    'incoming' => 'ITEMS_STOCK_INCOMING',
			    'cost' => 'ITEMS_COST',
			    'stockvalue' => 'ITEMS_STOCK_VALUE',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}
}
