<?php

class Users_Form_User extends Zend_Form
{
	public function init()
	{
		$this->setName('user');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$username = new Zend_Form_Element_Text('username');
		$username->setLabel('USERS_USERNAME')
			//->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			//->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$password = new Zend_Form_Element_Password('password');
		$password->setLabel('USERS_PASSWORD')
			//->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			//->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$client = new Zend_Form_Element_Select('client');
		$client->setLabel('USERS_CLIENT')
			->addFilter('Int');


		$stayLoggedIn = new Zend_Form_Element_Checkbox('stayLoggedIn');
		$stayLoggedIn->setLabel('USERS_STAY_LOGGED_IN');

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setAttrib('id', 'submitbutton');

		$this->addElements(array($id, $username, $password, $client, $stayLoggedIn, $submit));
	}
}
