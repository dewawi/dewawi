<?php

class Statistics_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'default' => '',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'wrap' => false,
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
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
		]);

		$this->addElement([
			'name' => 'from',
			'type' => 'text',
			'wrap' => false,
			'format' => ['type' => 'date'],
		]);

		$this->addElement([
			'name' => 'to',
			'type' => 'text',
			'wrap' => false,
			'format' => ['type' => 'date'],
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
			'name' => 'catid',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'default' => 'all',
		]);

		$this->addElement([
			'name' => 'width',
			'type' => 'text',
			'default' => '1000',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'height',
			'type' => 'text',
			'default' => '400',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);
	}
}
