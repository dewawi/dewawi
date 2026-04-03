<?php

class Contacts_Form_Contactperson extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 40,
			],
		]);

		$this->addElement([
			'name' => 'salutation',
			'type' => 'select',
			'options' => [
				'0' => 'keine',
				'Herr' => 'Herr',
				'Frau' => 'Frau',
			],
			'default' => '0',
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'name1',
			'type' => 'text',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 40,
				'class' => 'required',
			],
		]);

		$this->addElement([
			'name' => 'name2',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 40,
			],
		]);

		$this->addElement([
			'name' => 'department',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 40,
			],
		]);
	}
}
