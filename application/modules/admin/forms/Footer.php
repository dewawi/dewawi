<?php

class Admin_Form_Footer extends Zend_Form
{
	public function init()
	{
		$this->setName('footer');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['templateid'] = new Zend_Form_Element_Text('templateid');
		$form['templateid']->setLabel('ADMIN_TEMPLATE_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['column'] = new Zend_Form_Element_Text('column');
		$form['column']->setLabel('ADMIN_COLUMN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['text'] = new Zend_Form_Element_Textarea('text');
		$form['text']->setLabel('ADMIN_TEXT')
			->setAttrib('cols', '62')
			->setAttrib('rows', '5')
			->setAttrib('default', '');

		$form['width'] = new Zend_Form_Element_Text('width');
		$form['width']->setLabel('ADMIN_WIDTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['clientid'] = new Zend_Form_Element_Select('clientid');
		$form['clientid']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '0');

		$this->addElements($form);
	}
}
