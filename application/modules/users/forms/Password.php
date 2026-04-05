<?php

class Users_Form_Password extends DEEC_Form
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
			'name' => 'passwordactual',
			'type' => 'password',
			'label' => 'USERS_PASSWORD_ACTUAL',
			'required' => true,
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'current-password',
			],
		]);

		$this->addElement([
			'name' => 'passwordnew',
			'type' => 'password',
			'label' => 'USERS_PASSWORD_NEW',
			'required' => true,
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'new-password',
			],
		]);

		$this->addElement([
			'name' => 'passwordconfirm',
			'type' => 'password',
			'label' => 'USERS_PASSWORD_CONFIRM',
			'required' => true,
			'attribs' => [
				'size' => 50,
				'autocomplete' => 'new-password',
			],
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
