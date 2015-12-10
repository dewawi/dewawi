<?php

class Admin_Form_Template extends Zend_Form
{
	public function init()
	{
		$this->setName('template');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['description'] = new Zend_Form_Element_Text('description');
		$form['description']->setLabel('ADMIN_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['logo'] = new Zend_Form_Element_Text('logo');
		$form['logo']->setLabel('ADMIN_LOGO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['website'] = new Zend_Form_Element_Text('website');
		$form['website']->setLabel('ADMIN_WEBSITE')
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
