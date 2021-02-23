<?php

class Ebay_Form_Listing extends Zend_Form
{
	public function init()
	{
		$this->setName('listing');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['ebayuserid'] = new Zend_Form_Element_Text('ebayuserid');
		$form['ebayuserid']->setLabel('EBAY_USER_ID')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['itemid'] = new Zend_Form_Element_Text('itemid');
		$form['itemid']->setLabel('EBAY_ITEM_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['category1'] = new Zend_Form_Element_Text('category1');
		$form['category1']->setLabel('EBAY_CATEGORY1')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['category2'] = new Zend_Form_Element_Text('category2');
		$form['category2']->setLabel('EBAY_CATEGORY2')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['ebaystorecategory1'] = new Zend_Form_Element_Text('ebaystorecategory1');
		$form['ebaystorecategory1']->setLabel('EBAY_STORE_CATEGORY1')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['ebaystorecategory2'] = new Zend_Form_Element_Text('ebaystorecategory2');
		$form['ebaystorecategory2']->setLabel('EBAY_STORE_CATEGORY2')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['shippingpolicy'] = new Zend_Form_Element_Text('shippingpolicy');
		$form['shippingpolicy']->setLabel('EBAY_SHIPPING_POLICY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['paymentpolicy'] = new Zend_Form_Element_Text('paymentpolicy');
		$form['paymentpolicy']->setLabel('EBAY_PAYMENT_POLICY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['returnpolicy'] = new Zend_Form_Element_Text('returnpolicy');
		$form['returnpolicy']->setLabel('EBAY_RETURN_POLICY')
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
