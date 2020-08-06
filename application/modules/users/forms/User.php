<?php

class Users_Form_User extends Zend_Form
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

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('USERS_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['email'] = new Zend_Form_Element_Text('email');
		$form['email']->setLabel('USERS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['smtphost'] = new Zend_Form_Element_Text('smtphost');
		$form['smtphost']->setLabel('USERS_SMTP_HOST')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['smtpauth'] = new Zend_Form_Element_Text('smtpauth');
		$form['smtpauth']->setLabel('USERS_SMTP_AUTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['smtpsecure'] = new Zend_Form_Element_Text('smtpsecure');
		$form['smtpsecure']->setLabel('USERS_SMTP_SECURE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['smtpuser'] = new Zend_Form_Element_Text('smtpuser');
		$form['smtpuser']->setLabel('USERS_SMTP_USER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['smtppass'] = new Zend_Form_Element_Text('smtppass');
		$form['smtppass']->setLabel('USERS_SMTP_PASSWORD')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['stayLoggedIn'] = new Zend_Form_Element_Checkbox('stayLoggedIn');
		$form['stayLoggedIn']->setLabel('USERS_STAY_LOGGED_IN');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
