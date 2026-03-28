<?php

class Contacts_Form_Internet extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'internet',
			'label' => 'CONTACTS_INTERNET',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);
	}
}
