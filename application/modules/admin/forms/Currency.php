<?php

class Admin_Form_Currency extends Zend_Form
{
	public function init()
	{
		$this->setName('currency');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['code'] = new Zend_Form_Element_Text('code');
		$form['code']->setLabel('ADMIN_CURRENCY_CODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['name'] = new Zend_Form_Element_Text('name');
		$form['name']->setLabel('ADMIN_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['symbol'] = new Zend_Form_Element_Text('symbol');
		$form['symbol']->setLabel('ADMIN_CURRENCY_SYMBOL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['clientid'] = new Zend_Form_Element_Select('clientid');
		$form['clientid']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '0');

		$this->addElements($form);
	}
}
