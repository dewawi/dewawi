<?php

class Contacts_Form_Tag extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'tag',
			'label' => 'CONTACTS_TAG',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);
	}
}
