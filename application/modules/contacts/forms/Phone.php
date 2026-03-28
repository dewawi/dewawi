<?php

class Contacts_Form_Phone extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'phone',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'options' => [
				'phone' => 'CONTACTS_PHONE',
				'mobile' => 'CONTACTS_MOBILE',
				'fax' => 'CONTACTS_FAX',
			],
			'format' => ['type' => 'string'],
		]);
	}
}
