<?php

class Admin_Form_Image extends DEEC_Form
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
			'name' => 'title',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
