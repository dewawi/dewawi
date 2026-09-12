<?php

class Admin_Form_Config extends DEEC_Form
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
			'name' => 'timezone',
			'type' => 'text',
			'label' => 'ADMIN_TIMEZONE',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'source' => 'language',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'text',
			'label' => 'ADMIN_FOOTER',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'analytics',
			'type' => 'textarea',
			'label' => 'ADMIN_ANALYTICS',
			'format' => ['type' => 'string'],
			'attribs' => [
				'rows' => 12,
			],
			'col' => 12,
		]);
	}
}
