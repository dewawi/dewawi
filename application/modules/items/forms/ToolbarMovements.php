<?php

class Items_Form_ToolbarMovements extends Items_Form_Toolbar
{
	public function __construct()
	{
		parent::__construct();
		$listActions = array_intersect_key($this->elements, array_flip(['filter', 'reset']));

		foreach(array_keys($this->elements) as $name) unset($this->elements[$name]);

		$this->addMovementAction('receipt', 'ITEMS_MOVEMENT_BOOK_RECEIPT', 'ledger', 'inflow', 'receipt');
		$this->addMovementAction('delivery', 'ITEMS_MOVEMENT_BOOK_DELIVERY', 'ledger', 'outflow', 'delivery');
		$this->addMovementAction('correction', 'ITEMS_MOVEMENT_BOOK_CORRECTION', 'ledger', 'inflow', 'correction');
		$this->addMovementAction('incoming', 'ITEMS_MOVEMENT_PLAN_INCOMING', 'stockplanning', 'incoming');
		$this->addMovementAction('reservation', 'ITEMS_MOVEMENT_RESERVE_ITEM', 'stockplanning', 'reservation');

		foreach($listActions as $name => $element) $this->elements[$name] = $element;

		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'default' => '',
			'toolbar' => 'search',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);
		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'toolbar' => 'search',
			'wrap' => false,
			'attribs' => ['class' => 'clear nolabel', 'rel' => 'keyword'],
		]);
		$this->addElement([
			'name' => 'movement',
			'type' => 'select',
			'label' => 'ITEMS_MOVEMENT_FILTER',
			'default' => 'all',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'booked' => 'ITEMS_MOVEMENT_FILTER_BOOKED',
				'planned' => 'ITEMS_MOVEMENT_FILTER_PLANNED',
				'incoming' => 'ITEMS_MOVEMENT_FILTER_INCOMING',
				'reservation' => 'ITEMS_MOVEMENT_FILTER_RESERVATIONS',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'itemid',
			'type' => 'hidden',
			'default' => '0',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);
		$this->addElement([
			'name' => 'item',
			'type' => 'text',
			'label' => 'ITEM',
			'default' => '',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'autocomplete',
				'autocomplete' => 'off',
				'data-autocomplete-source' => '/items/item/suggest',
				'data-autocomplete-value' => 'sku',
				'data-autocomplete-target' => 'itemid',
				'data-autocomplete-target-value' => 'id',
				'data-autocomplete-min-length' => 2,
			],
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
			'name' => 'status',
			'type' => 'select',
			'label' => 'ITEMS_MOVEMENT_STATUS',
			'default' => 'all',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'booked' => 'ITEMS_MOVEMENT_STATUS_BOOKED',
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
		$this->addDateRangeElements();
		$this->addElement([
			'name' => 'order',
			'type' => 'radio',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'movementdate',
			'options' => [
				'movementdate' => 'ITEMS_MOVEMENT_DATE',
				'sku' => 'ITEMS_SKU',
				'itemtitle' => 'ITEMS_TITLE',
				'warehousetitle' => 'ITEMS_WAREHOUSE',
				'movementtype' => 'ITEMS_MOVEMENT_TYPE',
				'signedquantity' => 'ITEMS_MOVEMENT_QUANTITY',
				'status' => 'ITEMS_MOVEMENT_STATUS',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'sort',
			'type' => 'radio',
			'label' => 'TOOLBAR_SORT',
			'default' => 'DESC',
			'options' => ['ASC' => 'TOOLBAR_ASC', 'DESC' => 'TOOLBAR_DESC'],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}

	protected function addMovementAction(string $name, string $label, string $controller, string $type, string $reason = ''): void
	{
		$attribs = [
			'class' => 'add',
			'data-action' => 'add',
			'data-route-module' => 'items',
			'data-route-controller' => $controller,
			'data-route-type' => $type,
		];
		if($reason !== '') $attribs['data-route-reason'] = $reason;

		$this->addElement([
			'name' => $name,
			'type' => 'button',
			'label' => $label,
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => $attribs,
		]);
	}

	protected function addDateRangeElements(): void
	{
		$this->addElement([
			'name' => 'daterange',
			'type' => 'radio',
			'label' => 'TOOLBAR_DATE_RANGE',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'today' => 'TOOLBAR_TODAY',
				'yesterday' => 'TOOLBAR_YESTERDAY',
				'last7days' => 'TOOLBAR_LAST_7_DAYS',
				'last14days' => 'TOOLBAR_LAST_14_DAYS',
				'last30days' => 'TOOLBAR_LAST_30_DAYS',
				'thisMonth' => 'TOOLBAR_THIS_MONTH',
				'lastMonth' => 'TOOLBAR_LAST_MONTH',
				'thisYear' => 'TOOLBAR_THIS_YEAR',
				'lastYear' => 'TOOLBAR_LAST_YEAR',
				'custom' => 'TOOLBAR_CUSTOM',
			],
			'default' => 'all',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		foreach(['from' => date('Y-m-d', strtotime('-1 month')), 'to' => date('Y-m-d')] as $name => $default) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $name === 'from' ? 'TOOLBAR_FROM' : 'TOOLBAR_TO',
				'default' => $default,
				'filter' => true,
				'toolbar' => 'filters',
				'wrap' => false,
				'format' => ['type' => 'date'],
				'attribs' => ['class' => 'datePicker'],
			]);
		}
	}
}
