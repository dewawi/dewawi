<?php

class Contacts_Form_Toolbar extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbar');

		$form = array();

		$form['add'] = new Zend_Form_Element_Button('add');
		$form['add']->setLabel('TOOLBAR_NEW')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'add');

		$form['view'] = new Zend_Form_Element_Button('view');
		$form['view']->setLabel('TOOLBAR_VIEW')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'view');

		$form['edit'] = new Zend_Form_Element_Button('edit');
		$form['edit']->setLabel('TOOLBAR_EDIT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit hidden-sm');

		$form['editInline'] = new Zend_Form_Element_Button('edit');
		$form['editInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit nolabel');

		$form['copy'] = new Zend_Form_Element_Button('copy');
		$form['copy']->setLabel('TOOLBAR_COPY')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy hidden-sm');

		$form['copyInline'] = new Zend_Form_Element_Button('copy');
		$form['copyInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'copy nolabel');

		$form['pdf'] = new Zend_Form_Element_Button('pdf');
		$form['pdf']->setLabel('TOOLBAR_PDF')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'pdf');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete hidden-sm');

		$form['deleteInline'] = new Zend_Form_Element_Button('delete');
		$form['deleteInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete nolabel');

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
			->addMultiOption('name1', 'ORDERING_NAME')
			->addMultiOption('postcode', 'ORDERING_POSTCODE')
			->addMultiOption('city', 'ORDERING_CITY')
			->addMultiOption('country', 'ORDERING_COUNTRY')
			->addMultiOption('catid', 'ORDERING_CATEGORY')
			->addMultiOption('modified', 'ORDERING_MODIFIED')
			->setAttrib('default', 'id')
			->setAttrib('class', 'hidden-sm');

		$form['sort'] = new Zend_Form_Element_Select('sort');
		$form['sort']->setDecorators(array('ViewHelper'))
			->addMultiOption('asc', 'ORDERING_ASC')
			->addMultiOption('desc', 'ORDERING_DESC')
			->setAttrib('default', 'desc')
			->setAttrib('class', 'hidden-sm');

		$form['country'] = new Zend_Form_Element_Select('country');
		$form['country']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TOOLBAR_ALL_COUNTRIES')
			->setAttrib('default', '0')
			->setAttrib('class', 'hidden-sm hidden-md');

		$form['limit'] = new Zend_Form_Element_Select('limit');
		$form['limit']->setDecorators(array('ViewHelper'))
			->addMultiOption('50', '50')
			->addMultiOption('100', '100')
			->addMultiOption('250', '250')
			->addMultiOption('500', '500')
			->addMultiOption('0', 'TOOLBAR_ALL')
			->setAttrib('default', '50')
			->setAttrib('class', 'hidden-sm');

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'CATEGORIES_ALL')
			->setAttrib('default', '0')
			->setAttrib('class', 'hidden-sm');

		$this->addElements($form);
	}
}
