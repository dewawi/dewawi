<?php

class Contacts_Form_Contactperson extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
		]);

		$this->addElement([
			'name' => 'salutation',
			'type' => 'select',
			'label' => 'CONTACTS_CONTACT_PERSONS_SALUTATION',
			'default' => '0',
			'options' => [
				'0' => 'keine',
				'Herr' => 'Herr',
				'Frau' => 'Frau',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'name1',
			'type' => 'text',
			'label' => 'CONTACTS_CONTACT_PERSONS_NAME',
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
			'label' => 'CONTACTS_CONTACT_PERSONS_NAME_AFFIX',
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
