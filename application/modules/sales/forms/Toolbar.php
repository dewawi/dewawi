<?php

class Sales_Form_Toolbar extends Zend_Form
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

		$form['viewInline'] = new Zend_Form_Element_Button('view');
		$form['viewInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'view nolabel');

		$form['edit'] = new Zend_Form_Element_Button('edit');
		$form['edit']->setLabel('TOOLBAR_EDIT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit hidden-sm');

		$form['editInline'] = new Zend_Form_Element_Button('edit');
		$form['editInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'edit nolabel');

		$form['select'] = new Zend_Form_Element_Button('select');
		$form['select']->setLabel('TOOLBAR_SELECT')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'select poplight')
			->setAttrib('rel', 'addCustomer');

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

		$form['pdfInline'] = new Zend_Form_Element_Button('pdf');
		$form['pdfInline']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'pdf nolabel');

		$form['delete'] = new Zend_Form_Element_Button('delete');
		$form['delete']->setLabel('TOOLBAR_DELETE')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'delete hidden-sm');

		$form['keyword'] = new Zend_Form_Element_Text('keyword');
		$form['keyword']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['clear'] = new Zend_Form_Element_Button('clear');
		$form['clear']->setLabel('')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'clear nolabel')
			->setAttrib('rel', 'keyword');

		$form['filter'] = new Zend_Form_Element_Button('TOOLBAR_FILTER');
		$form['filter']->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'filter hidden-sm');

		$form['reset'] = new Zend_Form_Element_Button('reset');
		$form['reset']->setLabel('TOOLBAR_RESET')
			->setDecorators(array('ViewHelper'))
			->setAttrib('class', 'reset hidden-sm');

		$form['state'] = new Zend_Form_Element_Select('state');
		$form['state']->setDecorators(array('ViewHelper'))
			->addMultiOption('100', 'STATES_CREATED')
			->addMultiOption('101', 'STATES_IN_PROCESS')
			->addMultiOption('102', 'STATES_PLEASE_CHECK')
			->addMultiOption('103', 'STATES_PLEASE_DELETE')
			->addMultiOption('104', 'STATES_RELEASED');

		$form['states'] = new Zend_Form_Element_MultiCheckbox('states');
		$form['states']->setDecorators(array('ViewHelper'))
			->addMultiOption('100', 'STATES_CREATED')
			->addMultiOption('101', 'STATES_IN_PROCESS')
			->addMultiOption('102', 'STATES_PLEASE_CHECK')
			->addMultiOption('103', 'STATES_PLEASE_DELETE')
			->addMultiOption('104', 'STATES_RELEASED')
			->addMultiOption('105', 'STATES_COMPLETED')
			->addMultiOption('106', 'STATES_CANCELLED')
			->setAttrib('default', array('100', '101', '102', '103', '104'));

		$form['order'] = new Zend_Form_Element_Select('order');
		$form['order']->setDecorators(array('ViewHelper'))
			->addMultiOption('id', 'ORDERING_CREATION')
			->addMultiOption('title', 'ORDERING_TITLE')
			->addMultiOption('contactid', 'ORDERING_CUSTOMER_ID')
			->addMultiOption('billingname1', 'ORDERING_CUSTOMER')
			->addMultiOption('billingpostcode', 'ORDERING_POSTCODE')
			->addMultiOption('billingcity', 'ORDERING_CITY')
			->addMultiOption('modified', 'ORDERING_MODIFIED')
			->addMultiOption('total', 'ORDERING_TOTAL')
			->addMultiOption('state', 'ORDERING_STATE')
			->setAttrib('default', 'id');

		$form['sort'] = new Zend_Form_Element_Select('sort');
		$form['sort']->setDecorators(array('ViewHelper'))
			->addMultiOption('asc', 'ORDERING_ASC')
			->addMultiOption('desc', 'ORDERING_DESC')
			->setAttrib('default', 'asc');

		$form['country'] = new Zend_Form_Element_Select('country');
		$form['country']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TOOLBAR_ALL_COUNTRIES')
			->setAttrib('default', '0')
			->setAttrib('class', 'hidden-sm');

		$form['from'] = new Zend_Form_Element_Text('from');
		$form['from']->setDecorators(array('ViewHelper'));

		$form['to'] = new Zend_Form_Element_Text('to');
		$form['to']->setDecorators(array('ViewHelper'));

		$form['daterange'] = new Zend_Form_Element_Radio('daterange');
		$form['daterange']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TOOLBAR_ALL')
			->addMultiOption('today', 'TOOLBAR_TODAY')
			->addMultiOption('yesterday', 'TOOLBAR_YESTERDAY')
			->addMultiOption('last7days', 'TOOLBAR_LAST_7_DAYS')
			->addMultiOption('last14days', 'TOOLBAR_LAST_14_DAYS')
			->addMultiOption('last30days', 'TOOLBAR_LAST_30_DAYS')
			->addMultiOption('thisMonth', 'TOOLBAR_THIS_MONTH')
			->addMultiOption('lastMonth', 'TOOLBAR_LAST_MONTH')
			->addMultiOption('thisYear', 'TOOLBAR_THIS_YEAR')
			->addMultiOption('lastYear', 'TOOLBAR_LAST_YEAR')
			->addMultiOption('custom', 'TOOLBAR_CUSTOM')
			->setAttrib('default', '0');

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

		$this->addElements($form);
	}
}
