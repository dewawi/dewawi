<?php

class Admin_Form_Slide extends DEEC_Form
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
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'ADMIN_SHOP',
			'format' => ['type' => 'int'],
			'db' => 'Admin_Model_DbTable_Shop',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'image',
			'type' => 'text',
			'label' => 'ADMIN_IMAGE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'url',
			'type' => 'text',
			'label' => 'ADMIN_URL',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'textarea',
			'label' => 'ADMIN_DESCRIPTION',
			'format' => ['type' => 'string'],
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
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);
	}
}
