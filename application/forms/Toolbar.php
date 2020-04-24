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
			->setAttrib('class', 'view nolabel');

		$form['edit'] = new Zend_Form_Element_Button('edit');
		$form['edit']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit nolabel');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy nolabel');

		$form['pdf'] = new Zend_Form_Element_Button('pdf');
		$form['pdf']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'pdf nolabel');

		$this->addElements($form);
	}
}
