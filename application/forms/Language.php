<?php

class Application_Form_Language extends Zend_Form
{
	public function init()
	{
		$this->setName('language');

		$language = new Zend_Form_Element_Select('language');
		$language->removeDecorator('label');

		$this->addElements(array($language));
	}
}
