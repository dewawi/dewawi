<?php

class Admin_Form_Client extends DEEC_Form
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
			'name' => 'parentid',
			'type' => 'text',
			'label' => 'ADMIN_PARENT_ID',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'company',
			'type' => 'text',
			'label' => 'ADMIN_COMPANY',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'address',
			'type' => 'text',
			'label' => 'ADMIN_ADDRESS',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'postcode',
			'type' => 'text',
			'label' => 'ADMIN_POSTCODE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'city',
			'type' => 'text',
			'label' => 'ADMIN_CITY',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'text',
			'label' => 'ADMIN_COUNTRY',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'email',
			'type' => 'text',
			'label' => 'ADMIN_EMAIL',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'website',
			'type' => 'text',
			'label' => 'ADMIN_WEBSITE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
