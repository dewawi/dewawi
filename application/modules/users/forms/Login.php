<?php

class Users_Form_Login extends Zend_Form
{
	public function init()
	{
		$this->setName('user');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['username'] = new Zend_Form_Element_Text('username');
		$form['username']->setLabel('USERS_USERNAME')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$form['password'] = new Zend_Form_Element_Password('password');
		$form['password']->setLabel('USERS_PASSWORD')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$form['stayLoggedIn'] = new Zend_Form_Element_Checkbox('stayLoggedIn');
		$form['stayLoggedIn']->setLabel('USERS_STAY_LOGGED_IN');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
