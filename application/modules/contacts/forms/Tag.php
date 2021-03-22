<?php

class Contacts_Form_Tag extends Zend_Form
{
	public function init()
	{
		$this->setName('tag');

		$form = array();

		$form['tag'] = new Zend_Form_Element_Text('tag');
		$form['tag']->setLabel('CONTACTS_TAG')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30')
			->removeDecorator('label');

		$this->addElements($form);
	}
}
