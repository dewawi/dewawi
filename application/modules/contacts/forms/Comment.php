<?php

class Contacts_Form_Comment extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'comment',
			'label' => 'CONTACTS_COMMENTS',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);
	}
}
