<?php

class Statistics_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
			'toolbar' => 'actions',
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'toolbar' => 'filters',
			'default' => '0',
		]);

		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'filter'],
		]);

		$this->addElement([
			'name' => 'from',
			'type' => 'text',
			'wrap' => false,
			'format' => ['type' => 'date'],
			'toolbar' => 'filters',
		]);

		$this->addElement([
			'name' => 'to',
			'type' => 'text',
			'wrap' => false,
			'format' => ['type' => 'date'],
			'toolbar' => 'filters',
		]);

		$this->addElement([
			'name' => 'daterange',
			'type' => 'select',
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

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'all' => 'CATEGORIES_ALL',
				'0' => 'STATISTICS_UNCATEGORIZED',
			],
			'source' => 'category:contact',
			'toolbar' => 'filters',
			'default' => 'all',
		]);

		$this->addElement([
			'name' => 'export',
			'type' => 'button',
			'label' => 'TOOLBAR_EXPORT',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'export'],
		]);
	}
}
