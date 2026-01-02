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
			->setAttrib('class', 'edit hidden-sm');

		$form['editInline'] = new Zend_Form_Element_Button('edit');
		$form['editInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit nolabel');

		$form['save'] = new Zend_Form_Element_Button('save');
		$form['save']->setLabel('TOOLBAR_SAVE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'save');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy hidden-sm');

		$form['copyInline'] = new Zend_Form_Element_Button('copy');
		$form['copyInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy nolabel');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete hidden-sm');

		$form['deleteInline'] = new Zend_Form_Element_Button('delete');
		$form['deleteInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete nolabel');

		$form['pdfInline'] = new Zend_Form_Element_Button('pdf');
		$form['pdfInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'pdf nolabel');

		$form['keyword'] = new Zend_Form_Element_Text('keyword');
		$form['keyword']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['clear'] = new Zend_Form_Element_Button('clear');
		$form['clear']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'clear nolabel')
			->setAttrib('rel', 'keyword');

		$form['reset'] = new Zend_Form_Element_Button('reset');
		$form['reset']->setLabel('TOOLBAR_RESET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'reset hidden-sm');

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
			->setAttrib('default', 'sku')
			->setAttrib('class', 'hidden-sm');

		$form['sort'] = new Zend_Form_Element_Select('sort');
		$form['sort']->setDecorators(array('ViewHelper'))
			->addMultiOption('asc', 'ORDERING_ASC')
			->addMultiOption('desc', 'ORDERING_DESC')
			->setAttrib('default', 'asc')
			->setAttrib('class', 'hidden-sm');

		$form['limit'] = new Zend_Form_Element_Select('limit');
		$form['limit']->setDecorators(array('ViewHelper'))
			->addMultiOption('50', '50')
			->addMultiOption('100', '100')
			->addMultiOption('250', '250')
			->addMultiOption('500', '500')
			->addMultiOption('0', 'TOOLBAR_ALL')
			->setAttrib('default', '50')
			->setAttrib('class', 'hidden-sm');

		$form['manufacturerid'] = new Zend_Form_Element_Select('manufacturerid');
		$form['manufacturerid']->setDecorators(array('ViewHelper'))
			->addMultiOption(0, 'TOOLBAR_ALL')
			->setAttrib('default', 0);

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setDecorators(array('ViewHelper'))
			->addMultiOption('all', 'CATEGORIES_ALL')
			->setAttrib('default', 'all')
			->setAttrib('class', 'hidden-sm');

		$form['tagid'] = new Zend_Form_Element_Select('tagid');
		$form['tagid']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TAGS_ALL')
			->setAttrib('default', '0')
			->setAttrib('class', 'hidden-sm');

		$form['page'] = new Zend_Form_Element_Select('page');
		$form['page']->setDecorators(array('ViewHelper'))
			->addMultiOption(1, 1)
			->setAttrib('default', '1');

		$this->addElements($form);
	}
}
