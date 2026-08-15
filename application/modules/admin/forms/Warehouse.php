<?php

class Admin_Form_Warehouse extends DEEC_Form
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
			'label' => 'ADMIN_WAREHOUSE_CODE',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 50,
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 9,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'text',
			'label' => 'ADMIN_DESCRIPTION',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'active',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVE',
			'format' => ['type' => 'int'],
			'default' => 1,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'default',
			'type' => 'checkbox',
			'label' => 'ADMIN_WAREHOUSE_DEFAULT',
			'format' => ['type' => 'int'],
			'default' => 0,
			'col' => 3,
		]);
	}
}
