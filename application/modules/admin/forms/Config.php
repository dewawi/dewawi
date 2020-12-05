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

		$form['analytics'] = new Zend_Form_Element_Textarea('analytics');
		$form['analytics']->setLabel('ADMIN_ANALYTICS')
			->setDecorators(array('ViewHelper'))
			->setAttrib('cols', '62')
			->setAttrib('rows', '30')
			->setAttrib('default', '');

		$form['logo'] = new Zend_Form_Element_Text('logo');
		$form['logo']->setLabel('ADMIN_LOGO')
			->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['footer'] = new Zend_Form_Element_Text('footer');
		$form['footer']->setLabel('ADMIN_FOOTER')
			->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$this->addElements($form);
	}
}
