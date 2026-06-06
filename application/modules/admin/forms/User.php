<?php

class Admin_Form_User extends DEEC_Form
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
			'name' => 'username',
			'type' => 'text',
			'label' => 'ADMIN_USER_NAME',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'password',
			'type' => 'password',
			'label' => 'ADMIN_PASSWORD',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'email',
			'type' => 'text',
			'label' => 'ADMIN_EMAIL',
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

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
