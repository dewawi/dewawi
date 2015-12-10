<?php

class Contacts_Form_Email extends Zend_Form
{
	public function init()
	{
		$this->setName('email');

		$form = array();

		$form['email'] = new Zend_Form_Element_Text('email');
		$form['email']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30')
			->removeDecorator('label');

		$this->addElements($form);
	}
}
