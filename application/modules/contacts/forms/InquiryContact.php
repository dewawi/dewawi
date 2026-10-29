<?php

class Contacts_Form_InquiryContact extends DEEC_Form
{
	public function __construct()
	{
		$this->setMethod('post');

		foreach ([
			['name1', 'CONTACTS_NAME', true],
			['name2', 'CONTACTS_NAME_AFFIX', false],
			['firstname', 'CONTACTS_CONTACT_PERSONS_FIRSTNAME', false],
			['lastname', 'CONTACTS_CONTACT_PERSONS_LASTNAME', false],
			['street', 'CONTACTS_STREET', false],
			['postcode', 'CONTACTS_POSTCODE', false],
			['city', 'CONTACTS_CITY', false],
			['country', 'CONTACTS_COUNTRY', false],
			['phone', 'CONTACTS_PHONE', false],
		] as [$name, $label, $required]) {
			$this->addElement([
				'name' => $name,
				'type' => $name === 'street' ? 'textarea' : 'text',
				'label' => $label,
				'required' => $required,
				'format' => ['type' => 'string'],
			]);
		}

		$this->addElement([
			'name' => 'email',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL',
			'format' => ['type' => 'email'],
		]);
		$this->addElement([
			'name' => 'confirm_duplicate',
			'type' => 'checkbox',
			'label' => 'CONTACT_INQUIRIES_CONFIRM_DUPLICATE',
			'format' => ['type' => 'bool'],
		]);
		$this->addElement([
			'name' => 'create',
			'type' => 'submit',
			'label' => 'CONTACT_INQUIRIES_CREATE_CONTACT',
			'wrap' => false,
			'attribs' => ['class' => 'dw-btn dw-btn--primary'],
		]);
	}
}
