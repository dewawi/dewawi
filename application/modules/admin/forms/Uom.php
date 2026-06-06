<?php

class Admin_Form_Uom extends DEEC_Form
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
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_NAME',
			'format' => ['type' => 'string'],
			'col' => 12,
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
