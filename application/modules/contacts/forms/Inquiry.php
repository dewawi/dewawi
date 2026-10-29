<?php

class Contacts_Form_Inquiry extends DEEC_Form
{
	public function __construct()
	{
		$this->setMethod('post');
		$this->addElement([
			'name' => 'status',
			'type' => 'select',
			'label' => 'CONTACT_INQUIRIES_STATUS',
			'required' => true,
			'options' => Contacts_Model_DbTable_Inquiry::STATUSES,
			'format' => ['type' => 'string'],
			'attribs' => ['pattern' => '(?:new|in_progress|completed)'],
		]);
		$this->addElement([
			'name' => 'save',
			'type' => 'submit',
			'label' => 'CONTACT_INQUIRIES_SAVE_STATUS',
			'wrap' => false,
			'attribs' => ['class' => 'dw-btn dw-btn--primary'],
		]);
	}
}
