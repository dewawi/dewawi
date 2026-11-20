<?php

class Shops_Form_OrderToolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'icon' => 'filter',
			'iconFormat' => 'svg',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'filter'],
		]);
		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'icon' => 'reset',
			'iconFormat' => 'svg',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
		]);
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
			'label' => 'CLEAR',
			'icon' => 'clear',
			'iconFormat' => 'svg',
			'iconOnly' => true,
			'toolbar' => 'search',
			'wrap' => false,
			'attribs' => ['class' => 'clear nolabel', 'rel' => 'keyword'],
		]);
		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'SHOPS_ORDER_SHOP',
			'default' => 'all',
			'options' => ['all' => 'TOOLBAR_ALL'],
			'source' => 'shop',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'status',
			'type' => 'select',
			'label' => 'SHOPS_ORDER_STATUS',
			'default' => 'all',
			'options' => ['all' => 'TOOLBAR_ALL'] + ['open' => 'SHOPS_ORDER_OPEN', 'unknown' => 'SHOPS_ORDER_UNKNOWN'] + Shops_Model_DbTable_Order::STATUSES,
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'order',
			'type' => 'radio',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'orderdate',
			'options' => [
				'orderdate' => 'SHOPS_ORDER_DATE',
				'created' => 'SHOPS_ORDER_RECEIVED_AT',
				'orderid' => 'SHOPS_ORDER_ID',
				'customername' => 'SHOPS_ORDER_CUSTOMER',
				'total' => 'SHOPS_ORDER_NET',
				'status' => 'SHOPS_ORDER_STATUS',
				'modified' => 'TOOLBAR_MODIFIED',
				'website_title' => 'SHOPS_ORDER_SHOP',
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
}
