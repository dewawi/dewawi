<?php

class Admin_Form_Country extends DEEC_Form
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
			'label' => 'ADMIN_COUNTRY_CODE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 10,
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'name',
			'type' => 'text',
			'label' => 'ADMIN_COUNTRY',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'label' => 'ADMIN_CLIENT',
			'options' => [],
			'default' => 0,
			'col' => 6,
		]);
	}
}
