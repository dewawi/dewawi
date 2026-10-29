<?php

class Items_Form_ToolbarStock extends Items_Form_Toolbar
{
	public function setHistoricalMode(): void
	{
		unset($this->elements['availabilitydate'], $this->elements['availabilitystatus']);
	}

	public function __construct()
	{
		parent::__construct();

		foreach(['country', 'tagid'] as $name) {
			unset($this->elements[$name]);
		}

		$this->addElement([
			'name' => 'manufacturerid',
			'type' => 'select',
			'label' => 'ITEMS_MANUFACTURER',
			'default' => '0',
			'options' => [
				'0' => 'ITEMS_MANUFACTURERS_ALL',
			],
			'source' => 'manufacturer',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'stockstatus',
			'type' => 'select',
			'label' => 'ITEMS_STOCK_STATUS',
			'default' => 'all',
			'options' => [
				'all' => 'ITEMS_STOCK_STATUS_ALL',
				'positive' => 'ITEMS_STOCK_STATUS_POSITIVE',
				'zero' => 'ITEMS_STOCK_STATUS_ZERO',
				'negative' => 'ITEMS_STOCK_STATUS_NEGATIVE',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

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
			'name' => 'availabilitydate',
			'type' => 'text',
			'label' => 'ITEMS_STOCK_AVAILABILITY_DATE',
			'default' => date('Y-m-d'),
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'date', 'pattern' => 'Y-m-d'],
			'attribs' => ['class' => 'datePicker'],
		]);

		$this->addElement([
			'name' => 'availabilitystatus',
			'type' => 'select',
			'label' => 'ITEMS_STOCK_AVAILABILITY_STATUS',
			'default' => 'all',
			'options' => [
				'all' => 'ITEMS_STOCK_STATUS_ALL',
				'positive' => 'ITEMS_STOCK_STATUS_POSITIVE',
				'zero' => 'ITEMS_STOCK_STATUS_ZERO',
				'negative' => 'ITEMS_STOCK_STATUS_NEGATIVE',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
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
			    'forecastavailable' => 'ITEMS_STOCK_FORECAST_AVAILABLE',
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
