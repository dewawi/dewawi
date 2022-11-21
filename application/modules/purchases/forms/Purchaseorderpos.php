<?php

class Purchases_Form_Purchaseorderpos extends Zend_Form
{
	public function init()
	{
		$this->setName('purchaseorderpos');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['sku'] = new Zend_Form_Element_Text('sku');
		$form['sku']->setLabel('POSITIONS_SKU')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('POSITIONS_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['image'] = new Zend_Form_Element_Text('image');
		$form['image']->setLabel('POSITIONS_IMAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('POSITIONS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '3');

		$form['price'] = new Zend_Form_Element_Text('price');
		$form['price']->setLabel('POSITIONS_PRICE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '20');

		$form['taxrate'] = new Zend_Form_Element_Select('taxrate');
		$form['taxrate']->setLabel('POSITIONS_TAX_RATE')
			->addMultiOption(0, 'POSITIONS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['priceruleamount'] = new Zend_Form_Element_Text('priceruleamount');
		$form['priceruleamount']->setLabel('POSITIONS_PRICE_RULE_AMOUNT')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '20');

		$form['priceruleaction'] = new Zend_Form_Element_Select('priceruleaction');
		$form['priceruleaction']->setLabel('POSITIONS_PRICE_RULE_APPLY')
			->addMultiOption(0, 'POSITIONS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['pricerulemaster'] = new Zend_Form_Element_Checkbox('pricerulemaster');
		$form['pricerulemaster']->setLabel('POSITIONS_PRICE_RULE_MASTER');

		$form['quantity'] = new Zend_Form_Element_Text('quantity');
		$form['quantity']->setLabel('POSITIONS_QUANTITY')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '20');

		$form['total'] = new Zend_Form_Element_Text('total');
		$form['total']->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '20')
			->setDecorators(array('ViewHelper'));

		$form['uom'] = new Zend_Form_Element_Select('uom');
		$form['uom']->setLabel('POSITIONS_UOM')
			->addMultiOption(0, 'POSITIONS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['ordering'] = new Zend_Form_Element_Select('ordering');
		$form['ordering']->addFilter('Int')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setDecorators(array('ViewHelper'));

		$this->addElements($form);
		//$this->setElementDecorators(array('ViewHelper'));
	}
}
