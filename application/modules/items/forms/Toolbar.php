<?php

class Items_Form_Toolbar extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbar');

		$form = array();

		$form['add'] = new Zend_Form_Element_Button('add');
		$form['add']->setLabel('TOOLBAR_NEW')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'add');

		$form['edit'] = new Zend_Form_Element_Button('edit');
		$form['edit']->setLabel('TOOLBAR_EDIT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit');

		$form['editInline'] = new Zend_Form_Element_Button('edit');
		$form['editInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit');

		$form['save'] = new Zend_Form_Element_Button('save');
		$form['save']->setLabel('TOOLBAR_SAVE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'save');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy');

		$form['copyInline'] = new Zend_Form_Element_Button('copy');
		$form['copyInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete');

		$form['keyword'] = new Zend_Form_Element_Text('keyword');
		$form['keyword']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['clear'] = new Zend_Form_Element_Button('clear');
		$form['clear']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'clear')
			->setAttrib('rel', 'keyword');

		$form['reset'] = new Zend_Form_Element_Button('reset');
		$form['reset']->setLabel('TOOLBAR_RESET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'reset');

		$form['order'] = new Zend_Form_Element_Select('order');
		$form['order']->setDecorators(array('ViewHelper'))
			->addMultiOption('id', 'ORDERING_CREATION')
			->addMultiOption('sku', 'ORDERING_SKU')
			->addMultiOption('price', 'ORDERING_PRICE')
			->addMultiOption('cost', 'ORDERING_COST')
			->addMultiOption('margin', 'ORDERING_MARGIN')
			->addMultiOption('quantity', 'ORDERING_QUANTITY')
			->addMultiOption('catid', 'ORDERING_CATEGORY')
			->addMultiOption('modified', 'ORDERING_MODIFIED')
			->setAttrib('default', 'sku');

		$form['sort'] = new Zend_Form_Element_Select('sort');
		$form['sort']->setDecorators(array('ViewHelper'))
			->addMultiOption('asc', 'ORDERING_ASC')
			->addMultiOption('desc', 'ORDERING_DESC')
			->setAttrib('default', 'asc');

		$form['limit'] = new Zend_Form_Element_Select('limit');
		$form['limit']->setDecorators(array('ViewHelper'))
			->addMultiOption('50', '50')
			->addMultiOption('100', '100')
			->addMultiOption('250', '250')
			->addMultiOption('500', '500')
			->addMultiOption('0', 'TOOLBAR_ALL')
			->setAttrib('default', '50');

		$form['manufacturerid'] = new Zend_Form_Element_Select('manufacturerid');
		$form['manufacturerid']->setDecorators(array('ViewHelper'))
			->addMultiOption(0, 'TOOLBAR_ALL')
			->setAttrib('default', 0);

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'CATEGORIES_ALL')
			->setAttrib('default', '0');

		$this->addElements($form);
	}
}
