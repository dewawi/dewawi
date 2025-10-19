<?php

class Contacts_Form_Contactperson extends Zend_Form
{
	public function init()
	{
		$this->setName('contactperson');

		$form = array();

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['salutation'] = new Zend_Form_Element_Select('salutation');
		$form['salutation']->removeDecorator('label')
			->addMultiOption('0', 'keine')
			->addMultiOption('Herr', 'Herr')
			->addMultiOption('Frau', 'Frau');

		$form['name1'] = new Zend_Form_Element_Text('name1');
		$form['name1']->removeDecorator('label')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');	

		$form['name2'] = new Zend_Form_Element_Text('name2');
		$form['name2']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['department'] = new Zend_Form_Element_Text('department');
		$form['department']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$this->addElements($form);
	}
}
