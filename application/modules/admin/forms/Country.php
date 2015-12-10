<?php

class Admin_Form_Country extends Zend_Form
{
	public function init()
	{
		$this->setName('country');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['code'] = new Zend_Form_Element_Text('code');
		$form['code']->setLabel('ADMIN_COUNTRY_CODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('ADMIN_COUNTRY')
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
