<?php

class Contacts_Form_Downloadset extends Zend_Form
{
	public function init()
	{
		$this->setName('downloadset');

		$form = array();

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('CONTACTS_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ITEMS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '40')
			->setAttrib('rows', '20');

		$this->addElements($form);
	}
}
