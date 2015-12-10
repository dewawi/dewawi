<?php

class Contacts_Form_Phone extends Zend_Form
{
	public function init()
	{
		$this->setName('phone');

		$form = array();

		$form['phone'] = new Zend_Form_Element_Text('phone');
		$form['phone']->setLabel('CONTACTS_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30')
			->removeDecorator('label');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->removeDecorator('label')
			//->setRequired(true)
			->addMultiOption('phone', 'CONTACTS_PHONE')
			->addMultiOption('mobile', 'CONTACTS_MOBILE')
			->addMultiOption('fax', 'CONTACTS_FAX');

		$this->addElements($form);
	}
}
