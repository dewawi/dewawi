<?php

class Contacts_Form_Contactperson extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'salutation',
			'type' => 'select',
			'label' => 'CONTACTS_CONTACT_PERSONS_SALUTATION',
			'default' => '0',
			'options' => [
				'0' => 'CONTACTS_CONTACT_PERSONS_SALUTATION_NONE',
				'Herr' => 'CONTACTS_CONTACT_PERSONS_SALUTATION_MR',
				'Frau' => 'CONTACTS_CONTACT_PERSONS_SALUTATION_MS',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 20],
		]);

		$this->addElement([
			'name' => 'firstname',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_FIRSTNAME',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
		]);

		$this->addElement([
			'name' => 'lastname',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_LASTNAME',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 40,
				'class' => 'required',
			],
		]);

		$this->addElement([
			'name' => 'position',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_POSITION',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
		]);

		$this->addElement([
			'name' => 'department',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_DEPARTMENT',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
		]);

		$this->addElement([
			'name' => 'email',
			'type' => 'multi',
			'label' => 'CONTACTS_CONTACT_PERSONS_EMAIL',
			'module' => 'contacts',
			'controller' => 'email',
			'rows' => [],
		]);
	}
}
