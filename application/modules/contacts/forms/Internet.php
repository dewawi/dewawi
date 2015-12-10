<?php

class Contacts_Form_Internet extends Zend_Form
{
	public function init()
	{
		$this->setName('internet');

		$form = array();

		$form['internet'] = new Zend_Form_Element_Text('internet');
		$form['internet']->setLabel('CONTACTS_INTERNET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30')
			->removeDecorator('label');

		$this->addElements($form);
	}
}
