<?php

class Contacts_Form_Bankaccount extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'iban',
			'label' => 'CONTACTS_BANK_IBAN',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'bic',
			'label' => 'CONTACTS_BANK_BIC',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);
	}
}
