<?php

class Items_Form_ToolbarPositions extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbarpositions');

		$form = array();

		$form['add'] = new Zend_Form_Element_Button('add');
		$form['add']->setLabel('TOOLBAR_NEW')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'addPosition add');

		$form['addset'] = new Zend_Form_Element_Button('addset');
		$form['addset']->setLabel('TOOLBAR_NEW_SET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'addSet add');

		$form['copyset'] = new Zend_Form_Element_Button('copyset');
		$form['copyset']->setLabel('TOOLBAR_COPY_SET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copySet copy');

		$form['deleteset'] = new Zend_Form_Element_Button('deleteset');
		$form['deleteset']->setLabel('TOOLBAR_DELETE_SET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'deleteSet delete');

		$form['select'] = new Zend_Form_Element_Button('select');
		$form['select']->setLabel('TOOLBAR_SELECT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'select poplight')
			->setAttrib('rel', 'selectPosition');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copyPosition copy');

		$form['copypos'] = new Zend_Form_Element_Button('copypos');
		$form['copypos']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copyPosition copy nolabel');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'deletePosition delete');

		$form['deletepos'] = new Zend_Form_Element_Button('deletepos');
		$form['deletepos']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'deletePosition delete nolabel');

		$form['sortup'] = new Zend_Form_Element_Button('sortup');
		$form['sortup']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'up nolabel');

		$form['sortdown'] = new Zend_Form_Element_Button('sortdown');
		$form['sortdown']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'down nolabel');

		$this->addElements($form);
	}
}
