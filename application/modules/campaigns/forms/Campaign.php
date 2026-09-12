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
			'name' => 'customerid',
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
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'CAMPAIGNS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
		]);

		$this->addElement([
			'name' => 'contactcatid',
			'type' => 'select',
			'label' => 'PRICE_RULES_CONTACT_CATEGORY',
			'options' => ['0' => 'PRICE_RULES_ITEMS_ALL_CATEGORIES'],
			'source' => 'category:contact',
			'required' => true,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'contactsubcat',
			'type' => 'checkbox',
			'label' => 'PRICE_RULES_CONTACT_APPLY_TO_SUBCATEGORIES',
			'format' => ['type' => 'bool'],
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
		]);

		$this->addElement([
			'name' => 'interval',
			'type' => 'text',
			'label' => 'CAMPAIGNS_INTERVAL',
			'default' => 60,
			'format' => ['type' => 'int'],
			'attribs' => ['size' => 6],
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
			]);
		}

		$this->addElement([
			'name' => 'reminder',
			'type' => 'checkbox',
			'label' => 'CAMPAIGNS_REMINDER',
			'format' => ['type' => 'bool'],
		]);

		$this->addElement([
			'name' => 'remindertype',
			'type' => 'select',
			'label' => 'CAMPAIGNS_REMINDER_TYPE',
			'options' => [
				'email' => 'EMAIL',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'CAMPAIGNS_ACTIVATED',
			'format' => ['type' => 'bool'],
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
			]);
		}
	}
}
