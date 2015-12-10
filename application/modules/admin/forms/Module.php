<?php

class Admin_Form_Module extends Zend_Form
{
	public function init()
	{
		$this->setName('module');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('ADMIN_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['menu'] = new Zend_Form_Element_Textarea('menu');
		$form['menu']->setLabel('ADMIN_MENU')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['ordering'] = new Zend_Form_Element_Text('ordering');
		$form['ordering']->setLabel('ADMIN_ORDERING')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['active'] = new Zend_Form_Element_Text('active');
		$form['active']->setLabel('ADMIN_ACTIVE')
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
