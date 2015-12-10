<?php

class Application_Form_Toolbar extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbar');

		$form = array();

		$form['view'] = new Zend_Form_Element_Button('view');
		$form['view']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'view');

		$form['edit'] = new Zend_Form_Element_Button('edit');
		$form['edit']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy');

		$form['pdf'] = new Zend_Form_Element_Button('pdf');
		$form['pdf']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'pdf');

		$this->addElements($form);
	}
}
