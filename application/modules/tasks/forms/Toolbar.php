<?php

class Tasks_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'text',
			'name' => 'keyword',
			'default' => '',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'clear',
			'wrap' => false,
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'reset',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'country',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'from',
			'wrap' => false,
			'format' => ['type' => 'date'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'to',
			'wrap' => false,
			'format' => ['type' => 'date'],
		]);

		$this->addElement([
			'type' => 'radio',
			'name' => 'daterange',
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
			'type' => 'select',
			'name' => 'catid',
			'wrap' => false,
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'default' => 'all',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'width',
			'default' => '1000',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'height',
			'default' => '400',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);
	}
}
