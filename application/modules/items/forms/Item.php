<?php

class Items_Form_Item extends Zend_Form
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
		$form['type']->setLabel('ITEMS_TYPE')
			->addMultiOption("stockItem", 'ITEMS_STOCK_ITEM')
			->addMultiOption("deliveryItem", 'ITEMS_DELIVERY_ITEM')
			->addMultiOption("service", 'ITEMS_SERVICE')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['image'] = new Zend_Form_Element_Text('image');
		$form['image']->setLabel('ITEMS_IMAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setLabel('ITEMS_CATEGORY')
			->addMultiOption(0, 'CATEGORIES_MAIN_CATEGORY')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['barcode'] = new Zend_Form_Element_Text('barcode');
		$form['barcode']->setLabel('ITEMS_BARCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ITEMS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '25');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('ITEMS_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '10');

		$form['quantity'] = new Zend_Form_Element_Text('quantity');
		$form['quantity']->setLabel('ITEMS_QUANTITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['weight'] = new Zend_Form_Element_Text('weight');
		$form['weight']->setLabel('ITEMS_WEIGHT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['cost'] = new Zend_Form_Element_Text('cost');
		$form['cost']->setLabel('ITEMS_COST')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '15');

		$form['price'] = new Zend_Form_Element_Text('price');
		$form['price']->setLabel('ITEMS_PRICE')
			//->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			//->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '15');

		$form['margin'] = new Zend_Form_Element_Text('margin');
		$form['margin']->setLabel('ITEMS_MARGIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '15')
			->setAttrib('readonly', 'readonly');

		$form['taxid'] = new Zend_Form_Element_Select('taxid');
		$form['taxid']->setLabel('ITEMS_VAT')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['uomid'] = new Zend_Form_Element_Select('uomid');
		$form['uomid']->setLabel('ITEMS_UOM')
			->addMultiOption(0, 'ITEMS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['manufacturerid'] = new Zend_Form_Element_Select('manufacturerid');
		$form['manufacturerid']->setLabel('ITEMS_MANUFACTURER')
			->addMultiOption(0, 'ITEMS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['manufacturersku'] = new Zend_Form_Element_Text('manufacturersku');
		$form['manufacturersku']->setLabel('ITEMS_MANUFACTURER_SKU')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
