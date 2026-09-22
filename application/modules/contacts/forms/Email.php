<?php

class Contacts_Form_Email extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'email',
			'label' => 'CONTACTS_EMAIL',
			'type' => 'text',
			'format' => ['type' => 'email'],
		]);

		$this->addElement([
			'name' => 'suppressed',
			'label' => 'CONTACTS_EMAIL_SUPPRESSED',
			'type' => 'checkbox',
			'format' => ['type' => 'bool'],
		]);
	}
}
