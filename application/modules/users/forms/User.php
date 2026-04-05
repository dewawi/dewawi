<?php

class Users_Form_User extends DEEC_Form
{
	public function __construct()
	{
		$this->setMethod('post');

		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'username',
			'type' => 'text',
			'label' => 'USERS_USERNAME',
			'required' => true,
			'attribs' => [
				'readonly' => 'readonly',
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'password',
			'type' => 'password',
			'label' => 'USERS_PASSWORD',
			'required' => true,
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'new-password',
			],
		]);

		$this->addElement([
			'name' => 'name',
			'type' => 'text',
			'label' => 'USERS_NAME',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'email',
			'type' => 'email',
			'label' => 'USERS_EMAIL',
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'email',
			],
		]);

		$this->addElement([
			'name' => 'emailsender',
			'type' => 'text',
			'label' => 'USERS_EMAIL_SENDER',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'smtphost',
			'type' => 'text',
			'label' => 'USERS_SMTP_HOST',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'smtpauth',
			'type' => 'text',
			'label' => 'USERS_SMTP_AUTH',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'smtpsecure',
			'type' => 'text',
			'label' => 'USERS_SMTP_SECURE',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'smtpuser',
			'type' => 'text',
			'label' => 'USERS_SMTP_USER',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'smtppass',
			'type' => 'password',
			'label' => 'USERS_SMTP_PASSWORD',
			'attribs' => [
				'size' => 50,
			],
		]);

		$this->addElement([
			'name' => 'stayLoggedIn',
			'type' => 'checkbox',
			'label' => 'USERS_STAY_LOGGED_IN',
			'default' => 0,
		]);

		$this->addElement([
			'name' => 'submit',
			'type' => 'submit',
			'label' => 'SAVE',
			'attribs' => [
				'id' => 'submitbutton',
			],
		]);
	}
}
