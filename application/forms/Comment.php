<?php

class Application_Form_Comment extends Zend_Form
{
	public function init()
	{
		$this->setName('comment');

		$form = array();

		$form['comment'] = new Zend_Form_Element_Textarea('comment');
		$form['comment']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '100')
			->setAttrib('rows', '2');

		$this->addElements($form);
	}
}
