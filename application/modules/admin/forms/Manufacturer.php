<?php

class Admin_Form_Manufacturer extends Zend_Form
{
	public function init()
	{
		$this->setName('manufacturer');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('ADMIN_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('ADMIN_NAME')
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
