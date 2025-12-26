<?php

class Items_Form_Ledger extends Zend_Form
{
	public function init()
	{
		$this->setName('item');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['sku'] = new Zend_Form_Element_Text('sku');
		$form['sku']->setLabel('ITEMS_SKU')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('ITEMS_TITLE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->setLabel('ITEMS_LEDGER_TYPE')
			->addMultiOption("inflow", 'ITEMS_LEDGER_INFLOW')
			->addMultiOption("outflow", 'ITEMS_LEDGER_OUTFLOW')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['quantity'] = new Zend_Form_Element_Text('quantity');
		$form['quantity']->setLabel('ITEMS_LEDGER_QUANTITY')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '10')
			->setAttrib('class', 'number required');

		$form['comment'] = new Zend_Form_Element_Text('comment');
		$form['comment']->setLabel('ITEMS_LEDGER_COMMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['ledgerdate'] = new Zend_Form_Element_Text('ledgerdate');
		$form['ledgerdate']->setLabel('ITEMS_LEDGER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
