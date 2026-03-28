<?php

class Contacts_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'add',
			'label' => 'TOOLBAR_NEW',
			'attribs'=> ['class' => 'add'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'addset',
			'label' => 'TOOLBAR_NEW_SET',
			'attribs'=> ['class' => 'add addSet'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'view',
			'label' => 'TOOLBAR_VIEW',
			'attribs'=> ['class' => 'view'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'label' => 'TOOLBAR_EDIT',
			'attribs'=> ['class' => 'edit hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'save',
			'label' => 'TOOLBAR_SAVE',
			'attribs'=> ['class' => 'save'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'label' => 'TOOLBAR_COPY',
			'attribs'=> ['class' => 'copy hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'pdf',
			'label' => 'TOOLBAR_PDF',
			'attribs'=> ['class' => 'pdf'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'label' => 'TOOLBAR_DELETE',
			'attribs'=> ['class' => 'delete hidden-sm'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'keyword',
			'format' => ['type' => 'string'],
			'attribs'=> ['class' => 'keyword'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'clear',
			'attribs'=> ['class' => 'clear'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'reset',
			'label' => 'TOOLBAR_RESET',
			'attribs'=> ['class' => 'reset hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'order',
			'label' => 'TOOLBAR_ORDERING',
			'options'=> [
				'id' => 'ORDERING_CREATION',
				'sku' => 'ORDERING_SKU',
				'price' => 'ORDERING_PRICE',
				'cost' => 'ORDERING_COST',
				'margin' => 'ORDERING_MARGIN',
				'quantity' => 'ORDERING_QUANTITY',
				'catid' => 'ORDERING_CATEGORY',
				'modified' => 'ORDERING_MODIFIED',
			],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'sort',
			'label' => 'TOOLBAR_ORDERING',
			'options'=> [
				'asc' => 'ORDERING_ASC',
				'desc' => 'ORDERING_DESC',
			],
			'default' => 'asc',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'country',
			'options'=> [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'states',
			'options'=> [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'state',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'controller',
			'options'=> [
				'0' => 'TOOLBAR_ALL',
				'contact' => 'CONTACTS',
				'creditnote' => 'CREDIT_NOTES',
				'deliveryorder' => 'DELIVERY_ORDERS',
				'invoice' => 'INVOICES',
				'quote' => 'QUOTES',
				'reminder' => 'REMINDERS',
				'salesorder' => 'SALES_ORDERS',
				'purchaseorder' => 'PURCHASE_ORDERS',
				'quoterequest' => 'QUOTE_REQUESTS',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'limit',
			'options'=> [
				'50' => '50',
				'100' => '100',
				'250' => '250',
				'500' => '500',
				'0' => 'TOOLBAR_ALL',
			],
			'default' => '50',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'catid',
			'options'=> ['all' => 'CATEGORIES_ALL'],
			'source' => 'category:contact',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'tagid',
			'options'=> [
				'0' => 'TAGS_ALL',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'page',
			'options'=> [
				'1' => '1',
			],
			'default' => '1',
		]);
	}
}
/*
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

		$form['addset'] = new Zend_Form_Element_Button('addset');
		$form['addset']->setLabel('TOOLBAR_NEW_SET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'addSet add');

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

		$form['controller'] = new Zend_Form_Element_Select('controller');
		$form['controller']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TOOLBAR_ALL')
			->addMultiOption('contact', 'CONTACTS')
			->addMultiOption('creditnote', 'CREDIT_NOTES')
			->addMultiOption('deliveryorder', 'DELIVERY_ORDERS')
			->addMultiOption('invoice', 'INVOICES')
			->addMultiOption('quote', 'QUOTES')
			->addMultiOption('reminder', 'REMINDERS')
			->addMultiOption('salesorder', 'SALES_ORDERS')
			->addMultiOption('purchaseorder', 'PURCHASE_ORDERS')
			->addMultiOption('quoterequest', 'QUOTE_REQUESTS')
			->setAttrib('default', '0')
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
}*/
