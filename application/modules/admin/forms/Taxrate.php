<?php

class Admin_Form_Taxrate extends DEEC_Form
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
			'name' => 'name',
			'type' => 'text',
			'label' => 'ADMIN_NAME',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'rate',
			'type' => 'text',
			'label' => 'ADMIN_RATE',
			'format' => ['type' => 'float'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'options' => [],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'options' => [],
			'default' => 0,
			'col' => 6,
		]);
	}
}
