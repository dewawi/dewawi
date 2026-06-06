<?php

class Admin_Form_Template extends DEEC_Form
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
			'name' => 'description',
			'type' => 'text',
			'label' => 'ADMIN_NAME',
			'format' => ['type' => 'string'],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'website',
			'type' => 'text',
			'label' => 'ADMIN_WEBSITE',
			'format' => ['type' => 'string'],
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

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
