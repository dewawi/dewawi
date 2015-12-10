<?php

class Admin_Form_Config extends Zend_Form
{
	public function init()
	{
		$this->setName('config');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['timezone'] = new Zend_Form_Element_Text('timezone');
		$form['timezone']->setLabel('ADMIN_TIMEZONE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['language'] = new Zend_Form_Element_Text('language');
		$form['language']->setLabel('ADMIN_LANGUAGE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$this->addElements($form);
	}
}
