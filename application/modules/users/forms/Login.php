<?php

class Users_Form_Login extends DEEC_Form
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
				'size' => 50,
				'autocomplete' => 'username',
			],
		]);

		$this->addElement([
			'name' => 'password',
			'type' => 'password',
			'label' => 'USERS_PASSWORD',
			'required' => true,
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'current-password',
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
			'label' => 'USERS_LOGIN',
			'attribs' => [
				'id' => 'submitbutton',
			],
		]);
	}
}
