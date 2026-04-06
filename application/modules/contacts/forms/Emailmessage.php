<?php

class Contacts_Form_Emailmessage extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'sender',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL_SENDER',
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'recipient',
			'type' => 'select',
			'label' => 'CONTACTS_EMAIL_RECIPIENT',
			'attribs' => [
				'class' => 'required',
			],
			'options' => [],
		]);

		$this->addElement([
			'name' => 'cc',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL_CC',
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'bcc',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL_BCC',
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'replyto',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL_REPLY_TO',
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'subject',
			'type' => 'text',
			'label' => 'CONTACTS_EMAIL_SUBJECT',
			'attribs' => [
				'size' => 40,
			],
		]);

		$this->addElement([
			'name' => 'attachment',
			'type' => 'checkbox',
			'label' => 'CONTACTS_ATTACHMENTS',
			'default' => 1,
		]);

		$this->addElement([
			'name' => 'body',
			'type' => 'textarea',
			'label' => 'CONTACTS_EMAIL_BODY',
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
		]);
	}
}
