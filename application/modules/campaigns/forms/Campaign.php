<?php

class Campaigns_Form_Campaign extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'responsible',
			'type' => 'select',
			'label' => 'CAMPAIGNS_RESPONSIBLE_PERSON',
			'source' => 'user',
			'required' => true,
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'CAMPAIGNS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
			'col' => 9,
		]);

		$this->addElement([
			'name' => 'contactcatid',
			'type' => 'select',
			'label' => 'CAMPAIGNS_RECIPIENT_CATEGORY',
			'options' => ['0' => 'CAMPAIGNS_ALL_CATEGORIES'],
			'source' => 'category:contact',
			'required' => true,
			'format' => ['type' => 'int'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'contactsubcat',
			'type' => 'checkbox',
			'label' => 'CAMPAIGNS_INCLUDE_SUBCATEGORIES',
			'format' => ['type' => 'bool'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'CAMPAIGNS_ACTIVATED',
			'format' => ['type' => 'bool'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'priority',
			'type' => 'select',
			'label' => 'CAMPAIGNS_PRIORITY',
			'options' => [
				'0' => 'CAMPAIGNS_PRIORITY_NORMAL',
				'1' => 'CAMPAIGNS_PRIORITY_LOWEST',
				'2' => 'CAMPAIGNS_PRIORITY_LOW',
				'3' => 'CAMPAIGNS_PRIORITY_HIGH',
				'4' => 'CAMPAIGNS_PRIORITY_HIGHEST',
			],
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'timezone',
			'type' => 'select',
			'label' => 'CAMPAIGNS_TIMEZONE',
			'default' => 'Europe/Berlin',
			'options' => [
				'Europe/Berlin' => 'Europe/Berlin',
			],
			'format' => ['type' => 'string'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'interval',
			'type' => 'number',
			'label' => 'CAMPAIGNS_INTERVAL',
			'description' => 'CAMPAIGNS_INTERVAL_INFO',
			'default' => 60,
			'format' => ['type' => 'int'],
			'attribs' => [
				'min' => 1,
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'batchsize',
			'type' => 'number',
			'label' => 'CAMPAIGNS_BATCH_SIZE',
			'description' => 'CAMPAIGNS_BATCH_SIZE_INFO',
			'default' => 1,
			'format' => ['type' => 'int'],
			'attribs' => [
				'min' => 1,
				'max' => 1000,
			],
			'col' => 3,
		]);

		foreach([
			'startwindow' => 'CAMPAIGNS_WINDOW_START',
			'endwindow' => 'CAMPAIGNS_WINDOW_END',
		] as $name => $label) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $label,
				'format' => ['type' => 'string'],
				'attribs' => ['class' => 'timePicker', 'size' => 6],
				'col' => 3,
			]);
		}

		foreach([
			'startdate' => 'CAMPAIGNS_START_DATE',
			'duedate' => 'CAMPAIGNS_DUE_DATE',
		] as $name => $label) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $label,
				'format' => ['type' => 'string'],
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'col' => 3,
			]);
		}

		$this->addElement([
			'name' => 'reminder',
			'type' => 'checkbox',
			'label' => 'CAMPAIGNS_REMINDER',
			'format' => ['type' => 'bool'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'remindertype',
			'type' => 'select',
			'label' => 'CAMPAIGNS_REMINDER_TYPE',
			'options' => [
				'email' => 'EMAIL',
			],
			'format' => ['type' => 'string'],
			'col' => 3,
		]);

		foreach([
			'description' => ['CAMPAIGNS_DESCRIPTION', 45, 6],
			'notes' => ['CAMPAIGNS_NOTES', 45, 6],
			'info' => ['CAMPAIGNS_INFO', 45, 15],
		] as $name => $cfg) {
			$this->addElement([
				'name' => $name,
				'type' => 'textarea',
				'label' => $cfg[0],
				'format' => ['type' => 'string'],
				'attribs' => ['cols' => $cfg[1], 'rows' => $cfg[2]],
				'col' => 6,
			]);
		}

		$this->addElement([
			'name' => 'unsubscribeurl',
			'type' => 'text',
			'label' => 'CAMPAIGNS_UNSUBSCRIBE_URL',
			'description' => 'CAMPAIGNS_UNSUBSCRIBE_URL_INFO',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);
	}
}
