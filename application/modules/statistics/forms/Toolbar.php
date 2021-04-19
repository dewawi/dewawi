<?php

class Statistics_Form_Toolbar extends Zend_Form
{
	public function init()
	{
		$this->setName('toolbar');

		$form = array();

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
			->setAttrib('class', 'reset');

		$form['country'] = new Zend_Form_Element_Select('country');
		$form['country']->setDecorators(array('ViewHelper'))
			->addMultiOption('0', 'TOOLBAR_ALL_COUNTRIES')
			->setAttrib('default', '0');

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

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setDecorators(array('ViewHelper'))
			->addMultiOption('all', 'CATEGORIES_ALL')
			->setAttrib('default', 'all');

		$form['width'] = new Zend_Form_Element_Text('width');
		$form['width']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '1000');

		$form['height'] = new Zend_Form_Element_Text('height');
		$form['height']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '400');

		$this->addElements($form);
	}
}
