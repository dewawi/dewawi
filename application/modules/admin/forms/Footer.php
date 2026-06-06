<?php

class Admin_Form_Footer extends DEEC_Form
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
			'name' => 'templateid',
			'type' => 'text',
			'label' => 'ADMIN_TEMPLATE_ID',
			'format' => ['type' => 'int'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'column',
			'type' => 'text',
			'label' => 'ADMIN_COLUMN',
			'format' => ['type' => 'int'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'text',
			'type' => 'textarea',
			'label' => 'ADMIN_TEXT',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 62,
				'rows' => 5,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'width',
			'type' => 'text',
			'label' => 'ADMIN_WIDTH',
			'format' => ['type' => 'int'],
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
