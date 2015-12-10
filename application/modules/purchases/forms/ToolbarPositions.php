<?php

class Purchases_Form_ToolbarPositions extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbarpositions');

		$form = array();

		$form['add'] = new Zend_Form_Element_Button('add');
		$form['add']->setLabel('TOOLBAR_NEW')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'addPosition add');

		$form['select'] = new Zend_Form_Element_Button('select');
		$form['select']->setLabel('TOOLBAR_SELECT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'select poplight')
			->setAttrib('rel', 'selectPosition');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copyPosition copy');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'deletePosition delete');

		$form['sortup'] = new Zend_Form_Element_Button('sortup');
		$form['sortup']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'up');

		$form['sortdown'] = new Zend_Form_Element_Button('sortdown');
		$form['sortdown']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'down');

		$this->addElements($form);
	}
}
