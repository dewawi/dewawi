<?php

class Application_Form_Manufacturer extends Zend_Form
{
	public function init()
	{
		$this->setName('manufacturer');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$name = new Zend_Form_Element_Text('name');
		$name->setRequired(true)
			   ->addFilter('StripTags')
			   ->addFilter('StringTrim')
			   ->addValidator('NotEmpty');

		$this->addElements(array($id, $name));
		$this->setElementDecorators(array('ViewHelper'));
	}
}
