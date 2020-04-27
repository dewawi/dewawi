<?php

class Admin_Form_Client extends Zend_Form
{
	public function init()
	{
		$this->setName('client');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['company'] = new Zend_Form_Element_Text('company');
		$form['company']->setLabel('ADMIN_COMPANY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['address'] = new Zend_Form_Element_Text('address');
		$form['address']->setLabel('ADMIN_ADDRESS')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['postcode'] = new Zend_Form_Element_Text('postcode');
		$form['postcode']->setLabel('ADMIN_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['city'] = new Zend_Form_Element_Text('city');
		$form['city']->setLabel('ADMIN_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['country'] = new Zend_Form_Element_Text('country');
		$form['country']->setLabel('ADMIN_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['email'] = new Zend_Form_Element_Text('email');
		$form['email']->setLabel('ADMIN_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['website'] = new Zend_Form_Element_Text('website');
		$form['website']->setLabel('ADMIN_WEBSITE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['language'] = new Zend_Form_Element_Text('language');
		$form['language']->setLabel('ADMIN_LANGUAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		//$form['language'] = new Zend_Form_Element_Select('language');
		//$form['language']->setDecorators(array('ViewHelper'))
		//	->setAttrib('default', '');

		$this->addElements($form);
	}
}
