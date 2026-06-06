<?php

class Admin_Form_Currency extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'code',
			'type' => 'text',
			'label' => 'ADMIN_CURRENCY_CODE',
			'format' => ['type' => 'string'],
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'name',
			'type' => 'text',
			'label' => 'ADMIN_NAME',
			'format' => ['type' => 'string'],
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'symbol',
			'type' => 'text',
			'label' => 'ADMIN_CURRENCY_SYMBOL',
			'format' => ['type' => 'string'],
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'label' => 'ADMIN_CLIENT',
			'options' => [],
			'default' => 0,
			'col' => 12,
		]);
	}
}
