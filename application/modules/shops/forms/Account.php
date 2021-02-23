<?php

class Shops_Form_Account extends Zend_Form
{
	public function init()
	{
		$this->setName('account');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('SHOPS_TITLE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['type'] = new Zend_Form_Element_Text('type');
		$form['type']->setLabel('SHOPS_TYPE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['host'] = new Zend_Form_Element_Text('host');
		$form['host']->setLabel('SHOPS_HOST')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['username'] = new Zend_Form_Element_Text('username');
		$form['username']->setLabel('SHOPS_USERNAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['key'] = new Zend_Form_Element_Text('key');
		$form['key']->setLabel('SHOPS_KEY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
