<?php

class Application_Form_Footer extends Zend_Form
{
	public function init()
	{
		$this->setName('manufacturer');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$column = new Zend_Form_Element_Text('column');
		$column->setRequired(true)
			->addFilter('Int')
			->addValidator('NotEmpty');

		$text = new Zend_Form_Element_Textarea('text');
		$text->addFilter('StripTags')
			   ->addFilter('StringTrim')
			->setAttrib('cols', '20')
			->setAttrib('rows', '5');

		$width = new Zend_Form_Element_Text('width');
		$width->addFilter('Int');

		$this->addElements(array($id, $column, $text, $width));
		$this->setElementDecorators(array('ViewHelper'));
	}
}
