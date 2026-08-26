<?php

class Contacts_Form_Address extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'street',
			'label' => 'CONTACTS_STREET',
			'type' => 'textarea',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 40,
				'rows' => 5,
			],
		]);

		$this->addElement([
			'name' => 'postcode',
			'label' => 'CONTACTS_POSTCODE',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'city',
			'label' => 'CONTACTS_CITY',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'country',
			'label' => 'CONTACTS_COUNTRY',
			'options'=> [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'phone',
			'label' => 'CONTACTS_PHONE',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'options' => [
				'billing' => 'CONTACTS_BILLING_ADDRESS',
				'shipping' => 'CONTACTS_SHIPPING_ADDRESS',
				'other' => 'CONTACTS_OTHER_ADDRESS',
			],
			'format' => ['type' => 'string'],
		]);
	}
}
