<?php

class Users_Form_Password extends Zend_Form
{
	public function init()
	{
		$this->setName('password');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['passwordactual'] = new Zend_Form_Element_Password('passwordactual');
		$form['passwordactual']->setLabel('USERS_PASSWORD_ACTUAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$form['passwordnew'] = new Zend_Form_Element_Password('passwordnew');
		$form['passwordnew']->setLabel('USERS_PASSWORD_NEW')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$form['passwordconfirm'] = new Zend_Form_Element_Password('passwordconfirm');
		$form['passwordconfirm']->setLabel('USERS_PASSWORD_CONFIRM')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '50');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
