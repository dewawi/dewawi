<?php

class Items_Form_Pricerulepos extends Zend_Form
{
	public function init()
	{
		$this->setName('pricerulepos');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['action'] = new Zend_Form_Element_Select('action');
		$form['action']->setLabel('PRICE_RULES_ACTION')
			->addMultiOption(0, 'POSITIONS_NONE')
			->addMultiOption('bypercent', 'ITEMS_PRICE_RULE_BY_PERCENT')
			->addMultiOption('byfixed', 'ITEMS_PRICE_RULE_BY_FIXED')
			->addMultiOption('topercent', 'ITEMS_PRICE_RULE_TO_PERCENT')
			->addMultiOption('tofixed', 'ITEMS_PRICE_RULE_TO_FIXED')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['amount'] = new Zend_Form_Element_Text('amount');
		$form['amount']->setLabel('PRICE_RULES_AMOUNT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['activated'] = new Zend_Form_Element_Checkbox('activated');
		$form['activated']->setLabel('PRICE_RULES_ACTIVATED')
			->addFilter('Int');

		$this->addElements($form);
	}
}
