<?php

class Admin_Form_Module extends DEEC_Form
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
			'name' => 'menu',
			'type' => 'textarea',
			'label' => 'ADMIN_MENU',
			'format' => ['type' => 'string'],
			'attribs' => [
				'rows' => 5,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'ordering',
			'type' => 'text',
			'label' => 'ADMIN_ORDERING',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'active',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVE',
			'format' => ['type' => 'int'],
			'col' => 3,
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
