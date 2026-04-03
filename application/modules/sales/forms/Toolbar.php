<?php

class Sales_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'wrap' => false,
			'attribs' => [
				'class' => 'add',
			],
		]);

		$this->addElement([
			'name' => 'view',
			'type' => 'button',
			'label' => 'TOOLBAR_VIEW',
			'wrap' => false,
			'attribs' => [
				'class' => 'view',
			],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'wrap' => false,
			'attribs' => [
				'class' => 'edit hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'select',
			'type' => 'button',
			'label' => 'TOOLBAR_SELECT',
			'wrap' => false,
			'attribs' => [
				'class' => 'select poplight',
				'rel' => 'addCustomer',
			],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'attribs' => [
				'class' => 'copy hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'pdf',
			'type' => 'button',
			'label' => 'TOOLBAR_PDF',
			'wrap' => false,
			'attribs' => [
				'class' => 'pdf',
			],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => [
				'class' => 'delete hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'wrap' => false,
			'default' => '',
			'attribs' => [
			],
		]);

		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'label' => '',
			'wrap' => false,
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'wrap' => false,
			'attribs' => [
				'class' => 'filter hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => [
				'class' => 'reset hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'state',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_PROCESS',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
			],
		]);

		$this->addElement([
			'name' => 'states',
			'type' => 'multicheckbox',
			'wrap' => false,
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_PROCESS',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
				'105' => 'STATES_COMPLETED',
				'106' => 'STATES_CANCELLED',
			],
			'default' => ['100', '101', '102', '103', '104'],
		]);

		$this->addElement([
			'name' => 'order',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'id' => 'ORDERING_CREATION',
				'title' => 'ORDERING_TITLE',
				'contactid' => 'ORDERING_CUSTOMER_ID',
				'documentid' => 'ORDERING_DOCUMENT_ID',
				'billingname1' => 'ORDERING_CUSTOMER',
				'billingpostcode' => 'ORDERING_POSTCODE',
				'billingcity' => 'ORDERING_CITY',
				'modified' => 'ORDERING_MODIFIED',
				'total' => 'ORDERING_TOTAL',
				'state' => 'ORDERING_STATE',
			],
			'default' => 'id',
		]);

		$this->addElement([
			'name' => 'sort',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'asc' => 'ORDERING_ASC',
				'desc' => 'ORDERING_DESC',
			],
			'default' => 'asc',
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
			'attribs' => [
				'class' => 'hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'from',
			'type' => 'text',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'to',
			'type' => 'text',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'daterange',
			'type' => 'radio',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL',
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
			'default' => '0',
		]);

		$this->addElement([
			'name' => 'limit',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'50' => '50',
				'100' => '100',
				'250' => '250',
				'500' => '500',
				'0' => 'TOOLBAR_ALL',
			],
			'default' => '50',
			'attribs' => [
				'class' => 'hidden-sm',
			],
		]);

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'source' => 'category:contact',
			'default' => 'all',
			'attribs' => [
				'class' => 'hidden-sm',
			],
		]);
	}
}
