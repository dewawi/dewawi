<?php

class Admin_Form_Image extends Zend_Form
{
	public function init()
	{
		$this->setName('image');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->removeDecorator('Label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['activated'] = new Zend_Form_Element_Checkbox('activated');
		$form['activated']->addFilter('Int')->removeDecorator('Label');

		$this->addElements($form);
	}
}
