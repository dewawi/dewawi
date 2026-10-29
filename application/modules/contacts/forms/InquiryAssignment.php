<?php

class Contacts_Form_InquiryAssignment extends DEEC_Form
{
	public function __construct()
	{
		$this->setMethod('post');
		$this->addElement([
			'name' => 'contact_search',
			'type' => 'text',
			'label' => 'CONTACT_INQUIRIES_CUSTOMER_SEARCH',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'autocomplete',
				'data-autocomplete-source' => '/contacts/contact/suggest',
				'data-autocomplete-target' => 'contactid',
				'data-autocomplete-target-value' => 'id',
				'data-autocomplete-value' => 'label',
				'data-autocomplete-min-length' => 2,
			],
		]);
		$this->addElement([
			'name' => 'contactid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);
		$this->addElement([
			'name' => 'assign',
			'type' => 'submit',
			'label' => 'CONTACT_INQUIRIES_ASSIGN_CUSTOMER',
			'wrap' => false,
			'attribs' => ['class' => 'dw-btn dw-btn--primary'],
		]);
		$this->addElement([
			'name' => 'remove',
			'type' => 'submit',
			'label' => 'CONTACT_INQUIRIES_REMOVE_CUSTOMER',
			'wrap' => false,
			'attribs' => ['class' => 'dw-btn'],
		]);
	}
}
