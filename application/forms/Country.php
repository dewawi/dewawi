<?php

class Application_Form_Country extends Zend_Form
{
	public function init()
	{
		$this->setName('country');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$code = new Zend_Form_Element_Text('code');
		$code->setRequired(true)
			   ->addFilter('StripTags')
			   ->addFilter('StringTrim')
			   ->addValidator('NotEmpty');

		$name = new Zend_Form_Element_Text('name');
		$name->setRequired(true)
			   ->addFilter('StripTags')
			   ->addFilter('StringTrim')
			   ->addValidator('NotEmpty');

		$this->addElements(array($id, $code, $name));
		$this->setElementDecorators(array('ViewHelper'));
	}
}
