<?php

class Items_Form_Pricerule extends Zend_Form
{
	public function init()
	{
		$this->setName('pricerule');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('PRICE_RULES_TITLE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');

		$form['action'] = new Zend_Form_Element_Select('action');
		$form['action']->setLabel('PRICE_RULES_ACTION')
			->addMultiOption(0, 'POSITIONS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['amount'] = new Zend_Form_Element_Text('amount');
		$form['amount']->setLabel('PRICE_RULES_AMOUNT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['from'] = new Zend_Form_Element_Text('from');
		$form['from']->setLabel('PRICE_RULES_FROM')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['to'] = new Zend_Form_Element_Text('to');
		$form['to']->setLabel('PRICE_RULES_TO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['priority'] = new Zend_Form_Element_Text('priority');
		$form['priority']->setLabel('PRICE_RULES_PRIORITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['subsequent'] = new Zend_Form_Element_Checkbox('subsequent');
		$form['subsequent']->setLabel('PRICE_RULES_DISCARD_SUBSEQUENT_RULES');

		$form['itemcatid'] = new Zend_Form_Element_Select('itemcatid');
		$form['itemcatid']->setLabel('PRICE_RULES_ITEM_CATEGORY')
			->addMultiOption(0, 'PRICE_RULES_ITEMS_ALL_CATEGORIES')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['itemsubcat'] = new Zend_Form_Element_Checkbox('itemsubcat');
		$form['itemsubcat']->setLabel('PRICE_RULES_ITEM_APPLY_TO_SUBCATEGORIES');

		$form['itemtype'] = new Zend_Form_Element_Select('itemtype');
		$form['itemtype']->setLabel('PRICE_RULES_ITEM_TYPE')
			->addMultiOption(0, 'PRICE_RULES_ITEMS_ALL_TYPES')
			->addMultiOption("stockItem", 'ITEMS_STOCK_ITEM')
			->addMultiOption("deliveryItem", 'ITEMS_DELIVERY_ITEM')
			->addMultiOption("service", 'ITEMS_SERVICE')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['itemmanufacturer'] = new Zend_Form_Element_Select('itemmanufacturer');
		$form['itemmanufacturer']->setLabel('PRICE_RULES_ITEM_MANUFACTURER')
			->addMultiOption(0, 'PRICE_RULES_ITEMS_ALL_MANUFACTURERS')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['contactcatid'] = new Zend_Form_Element_Select('contactcatid');
		$form['contactcatid']->setLabel('PRICE_RULES_CONTACT_CATEGORY')
			->addMultiOption(0, 'PRICE_RULES_ITEMS_ALL_CATEGORIES')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['contactsubcat'] = new Zend_Form_Element_Checkbox('contactsubcat');
		$form['contactsubcat']->setLabel('PRICE_RULES_CONTACT_APPLY_TO_SUBCATEGORIES');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['activated'] = new Zend_Form_Element_Checkbox('activated');
		$form['activated']->setLabel('PRICE_RULES_ACTIVATED')
            ->addFilter('Int');

		$this->addElements($form);
	}
}
