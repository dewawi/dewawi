<?php

class Admin_Form_Shop extends Zend_Form
{
	public function init()
	{
		$this->setName('shop');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('ADMIN_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['url'] = new Zend_Form_Element_Text('url');
		$form['url']->setLabel('ADMIN_URL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['logo'] = new Zend_Form_Element_Text('logo');
		$form['logo']->setLabel('ADMIN_LOGO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['footer'] = new Zend_Form_Element_Text('footer');
		$form['footer']->setLabel('ADMIN_FOOTER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['emailsender'] = new Zend_Form_Element_Text('emailsender');
		$form['emailsender']->setLabel('ADMIN_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['activated'] = new Zend_Form_Element_Checkbox('activated');
		$form['activated']->addFilter('Int')->removeDecorator('Label');

		//$form['language'] = new Zend_Form_Element_Select('language');
		//$form['language']->setDecorators(array('ViewHelper'))
		//	->setAttrib('default', '');

		$this->addElements($form);
	}
}
