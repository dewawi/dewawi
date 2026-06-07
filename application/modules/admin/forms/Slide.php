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
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'label' => 'ADMIN_SHOP',
			'format' => ['type' => 'int'],
			'source' => 'shop',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'target',
			'type' => 'text',
			'label' => 'ADMIN_TARGET',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'position',
			'type' => 'text',
			'label' => 'ADMIN_POSITION',
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
