<?php

class Admin_Form_Toolbar extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbar');

		$form = array();

		$form['save'] = new Zend_Form_Element_Button('save');
		$form['save']->setLabel('TOOLBAR_SAVE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'save');

		$form['cancel'] = new Zend_Form_Element_Button('cancel');
		$form['cancel']->setLabel('TOOLBAR_CANCEL')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'cancel');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete');

		$form['clientid'] = new Zend_Form_Element_Select('clientid');
		$form['clientid']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '0');

		$form['parentid'] = new Zend_Form_Element_Select('parentid');
		$form['parentid']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'ADMIN_MAIN_CATEGORY')
			->setAttrib('default', '0');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->setDecorators(array('ViewHelper'))
			->addMultiOption('contact', 'CONTACTS')
			->addMultiOption('item', 'ITEMS')
			->addMultiOption('shop', 'SHOPS')
    		->setAttrib('style', 'display: none;');

		$form['shopid'] = new Zend_Form_Element_Select('shopid');
		$form['shopid']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'ADMIN_NONE');

		$form['language'] = new Zend_Form_Element_Select('language');
		$form['language']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['sortup'] = new Zend_Form_Element_Button('sortup');
		$form['sortup']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'up nolabel');

		$form['sortdown'] = new Zend_Form_Element_Button('sortdown');
		$form['sortdown']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'down nolabel')
			->setAttrib('default', '');

		$this->addElements($form);
	}
}
