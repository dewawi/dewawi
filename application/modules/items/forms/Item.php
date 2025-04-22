<?php

class Items_Form_Item extends Zend_Form
{
	public function init()
	{
		$this->setName('item');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['shopid'] = new Zend_Form_Element_Select('shopid');
		$form['shopid']->addFilter('Int');

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

		$form['video'] = new Zend_Form_Element_Text('video');
		$form['video']->setLabel('ITEMS_VIDEO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setLabel('ITEMS_CATEGORY')
			->addMultiOption(0, 'CATEGORIES_MAIN_CATEGORY')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['gtin'] = new Zend_Form_Element_Text('gtin');
		$form['gtin']->setLabel('ITEMS_GTIN_EAN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ITEMS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '40')
			->setAttrib('rows', '20');

		$form['shopdescription'] = new Zend_Form_Element_Textarea('shopdescription');
		$form['shopdescription']->setLabel('ITEMS_SHOP_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['shopdescriptionshort'] = new Zend_Form_Element_Textarea('shopdescriptionshort');
		$form['shopdescriptionshort']->setLabel('ITEMS_SHOP_DESCRIPTION_SHORT')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['shopdescriptionmini'] = new Zend_Form_Element_Textarea('shopdescriptionmini');
		$form['shopdescriptionmini']->setLabel('ITEMS_SHOP_DESCRIPTION_MINI')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('ITEMS_INFO_INTERNAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '10');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('ITEMS_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');

		$form['quantity'] = new Zend_Form_Element_Text('quantity');
		$form['quantity']->setLabel('ITEMS_QUANTITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['minquantity'] = new Zend_Form_Element_Text('minquantity');
		$form['minquantity']->setLabel('ITEMS_MIN_QUANTITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['orderquantity'] = new Zend_Form_Element_Text('orderquantity');
		$form['orderquantity']->setLabel('ITEMS_ORDER_QUANTITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['inventory'] = new Zend_Form_Element_Checkbox('inventory');
		$form['inventory']->setLabel('ITEMS_INVENTORY_ACTIVATED');

		$form['quantityreminder'] = new Zend_Form_Element_Checkbox('quantityreminder');
		$form['quantityreminder']->setLabel('ITEMS_QUANTITY_REMINDER');

		$form['warehouseid'] = new Zend_Form_Element_Select('warehouseid');
		$form['warehouseid']->setLabel('ITEMS_WAREHOUSE')
			->setRequired(true)
			->addMultiOption('1', 'Hauptlager')
			->addValidator('NotEmpty');

		$form['deliverytime'] = new Zend_Form_Element_Select('deliverytime');
		$form['deliverytime']->setLabel('ITEMS_DELIVERY_TIME')
			->setRequired(true)
			->addMultiOption(0, 'ITEMS_NONE')
			->addValidator('NotEmpty');

		$form['deliverytimeoos'] = new Zend_Form_Element_Select('deliverytimeoos');
		$form['deliverytimeoos']->setLabel('ITEMS_DELIVERY_TIME_OOS')
			->setRequired(true)
			->addMultiOption(0, 'ITEMS_NONE')
			->addValidator('NotEmpty');

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

		$form['specialprice'] = new Zend_Form_Element_Text('specialprice');
		$form['specialprice']->setLabel('ITEMS_SPECIAL_PRICE')
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
			->addMultiOption(0, 'ITEMS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['uomid'] = new Zend_Form_Element_Select('uomid');
		$form['uomid']->setLabel('ITEMS_UOM')
			->addMultiOption(0, 'ITEMS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['currency'] = new Zend_Form_Element_Select('currency');
		$form['currency']->setLabel('ITEMS_CURRENCY')
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

		$form['manufacturergtin'] = new Zend_Form_Element_Text('manufacturergtin');
		$form['manufacturergtin']->setLabel('ITEMS_MANUFACTURER_GTIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['ctn'] = new Zend_Form_Element_Text('ctn');
		$form['ctn']->setLabel('ITEMS_CTN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['origincountry'] = new Zend_Form_Element_Text('origincountry');
		$form['origincountry']->setLabel('ITEMS_ORIGIN_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['originregion'] = new Zend_Form_Element_Text('originregion');
		$form['originregion']->setLabel('ITEMS_ORIGIN_REGION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['width'] = new Zend_Form_Element_Text('width');
		$form['width']->setLabel('ITEMS_WIDTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['length'] = new Zend_Form_Element_Text('length');
		$form['length']->setLabel('ITEMS_LENGTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['height'] = new Zend_Form_Element_Text('height');
		$form['height']->setLabel('ITEMS_HEIGHT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['weight'] = new Zend_Form_Element_Text('weight');
		$form['weight']->setLabel('ITEMS_WEIGHT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['packwidth'] = new Zend_Form_Element_Text('packwidth');
		$form['packwidth']->setLabel('ITEMS_WIDTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['packlength'] = new Zend_Form_Element_Text('packlength');
		$form['packlength']->setLabel('ITEMS_LENGTH')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['packheight'] = new Zend_Form_Element_Text('packheight');
		$form['packheight']->setLabel('ITEMS_HEIGHT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['packweight'] = new Zend_Form_Element_Text('packweight');
		$form['packweight']->setLabel('ITEMS_WEIGHT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
