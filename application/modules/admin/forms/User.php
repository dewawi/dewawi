<?php

class Admin_Form_User extends Zend_Form
{
	public function init()
	{
		$this->setName('user');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['username'] = new Zend_Form_Element_Text('username');
		$form['username']->setLabel('ADMIN_USER_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['password'] = new Zend_Form_Element_Text('password');
		$form['password']->setLabel('ADMIN_PASSWORD')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['email'] = new Zend_Form_Element_Text('email');
		$form['email']->setLabel('ADMIN_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['language'] = new Zend_Form_Element_Select('language');
		$form['language']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['clientid'] = new Zend_Form_Element_Select('clientid');
		$form['clientid']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '0');

		$this->addElements($form);
	}
}
