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
	}
}
