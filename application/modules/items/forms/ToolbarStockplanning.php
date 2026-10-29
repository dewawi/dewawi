<?php

class Items_Form_ToolbarStockplanning extends Items_Form_Toolbar
{
	public function __construct()
	{
		parent::__construct();
		foreach(['catid', 'country', 'tagid'] as $name) unset($this->elements[$name]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_TYPE',
			'default' => 'all',
			'options' => ['all' => 'TOOLBAR_ALL', 'incoming' => 'ITEMS_PLANNING_INCOMING', 'reservation' => 'ITEMS_PLANNING_RESERVATION'],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'status',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_STATUS',
			'default' => 'all',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'draft' => 'ITEMS_PLANNING_STATUS_DRAFT',
				'planned' => 'ITEMS_PLANNING_STATUS_PLANNED',
				'completed' => 'ITEMS_PLANNING_STATUS_COMPLETED',
				'cancelled' => 'ITEMS_PLANNING_STATUS_CANCELLED',
				'released' => 'ITEMS_PLANNING_STATUS_RELEASED',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'planningdate',
			'type' => 'text',
			'label' => 'ITEMS_PLANNING_DATE',
			'default' => '',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'date'],
			'attribs' => ['class' => 'datePicker'],
		]);
		$this->addElement([
			'name' => 'warehouseid',
			'type' => 'select',
			'label' => 'ITEMS_WAREHOUSE',
			'default' => '0',
			'options' => ['0' => 'TOOLBAR_ALL'],
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
			'default' => 'planningdate',
			'options' => [
				'planningdate' => 'ITEMS_PLANNING_DATE',
				'sku' => 'ITEMS_SKU',
				'itemtitle' => 'ITEMS_TITLE',
				'type' => 'ITEMS_PLANNING_TYPE',
				'status' => 'ITEMS_PLANNING_STATUS',
				'modified' => 'TOOLBAR_MODIFIED',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}
}
