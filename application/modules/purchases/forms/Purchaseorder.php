<?php

class Purchases_Form_Purchaseorder extends Zend_Form
{
	public function init()
	{
		$this->setName('purchaseorder');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['purchaseorderid'] = new Zend_Form_Element_Text('purchaseorderid');
		$form['purchaseorderid']->setLabel('PURCHASE_ORDERS_PURCHASE_ORDER_ID')
			->addFilter('Int')
			->setAttrib('size', '5')
			->setAttrib('readonly', 'readonly');

		$form['opportunityid'] = new Zend_Form_Element_Hidden('opportunityid');
		$form['opportunityid']->addFilter('Int');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('PURCHASE_ORDERS_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('PURCHASE_ORDERS_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '50')
			->setAttrib('rows', '15');

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('PURCHASE_ORDERS_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('PURCHASE_ORDERS_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('PURCHASE_ORDERS_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['purchaseorderdate'] = new Zend_Form_Element_Text('purchaseorderdate');
		$form['purchaseorderdate']->setLabel('PURCHASE_ORDERS_PURCHASE_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['orderdate'] = new Zend_Form_Element_Text('orderdate');
		$form['orderdate']->setLabel('PURCHASE_ORDERS_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverydate'] = new Zend_Form_Element_Text('deliverydate');
		$form['deliverydate']->setLabel('PURCHASE_ORDERS_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['paymentmethod'] = new Zend_Form_Element_Select('paymentmethod');
		$form['paymentmethod']->setLabel('PURCHASE_ORDERS_PAYMENT_METHOD')
			->addMultiOption('', 'PURCHASE_ORDERS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['shippingmethod'] = new Zend_Form_Element_Select('shippingmethod');
		$form['shippingmethod']->setLabel('PURCHASE_ORDERS_SHIPPING_METHOD')
			->addMultiOption('', 'PURCHASE_ORDERS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['contactid'] = new Zend_Form_Element_Text('contactid');
		$form['contactid']->setLabel('CONTACTS_CONTACT_ID')
			->addFilter('Int')
			->setAttrib('readonly', 'readonly');

		$form['billingname1'] = new Zend_Form_Element_Text('billingname1');
		$form['billingname1']->setLabel('CONTACTS_NAME')
			->setAttrib('size', '40')
			->setAttrib('readonly', 'readonly');

		$form['billingname2'] = new Zend_Form_Element_Text('billingname2');
		$form['billingname2']->setLabel('')
			->setAttrib('size', '40')
			->setAttrib('readonly', 'readonly');

		$form['billingdepartment'] = new Zend_Form_Element_Text('billingdepartment');
		$form['billingdepartment']->setLabel('CONTACTS_DEPARTMENT')
			->setAttrib('size', '40')
			->setAttrib('readonly', 'readonly');

		$form['billingstreet'] = new Zend_Form_Element_Textarea('billingstreet');
		$form['billingstreet']->setLabel('CONTACTS_STREET')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3')
			->setAttrib('readonly', 'readonly');

		$form['billingpostcode'] = new Zend_Form_Element_Text('billingpostcode');
		$form['billingpostcode']->setLabel('CONTACTS_POSTCODE')
			->setAttrib('size', '30')
			->setAttrib('readonly', 'readonly');

		$form['billingcity'] = new Zend_Form_Element_Text('billingcity');
		$form['billingcity']->setLabel('CONTACTS_CITY')
			->setAttrib('size', '30')
			->setAttrib('readonly', 'readonly');

		$form['billingcountry'] = new Zend_Form_Element_Text('billingcountry');
		$form['billingcountry']->setLabel('CONTACTS_COUNTRY')
			->setAttrib('size', '30')
			->setAttrib('readonly', 'readonly');

		$form['shippingname1'] = new Zend_Form_Element_Text('shippingname1');
		$form['shippingname1']->setLabel('PURCHASE_ORDERS_SHIPPING_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingname2'] = new Zend_Form_Element_Text('shippingname2');
		$form['shippingname2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingdepartment'] = new Zend_Form_Element_Text('shippingdepartment');
		$form['shippingdepartment']->setLabel('PURCHASE_ORDERS_SHIPPING_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingstreet'] = new Zend_Form_Element_Textarea('shippingstreet');
		$form['shippingstreet']->setLabel('PURCHASE_ORDERS_SHIPPING_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['shippingpostcode'] = new Zend_Form_Element_Text('shippingpostcode');
		$form['shippingpostcode']->setLabel('PURCHASE_ORDERS_SHIPPING_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcity'] = new Zend_Form_Element_Text('shippingcity');
		$form['shippingcity']->setLabel('PURCHASE_ORDERS_SHIPPING_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcountry'] = new Zend_Form_Element_Text('shippingcountry');
		$form['shippingcountry']->setLabel('PURCHASE_ORDERS_SHIPPING_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingphone'] = new Zend_Form_Element_Text('shippingphone');
		$form['shippingphone']->setLabel('PURCHASE_ORDERS_SHIPPING_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['taxfree'] = new Zend_Form_Element_Checkbox('taxfree');
		$form['taxfree']->setLabel('CONTACTS_TAX_FREE')
			->setAttrib('disabled', 'disabled');

		$form['contactinfo'] = new Zend_Form_Element_Textarea('contactinfo');
		$form['contactinfo']->setLabel('PURCHASE_ORDERS_CONTACT_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30')
			->setAttrib('readonly', 'readonly');

		$form['templateid'] = new Zend_Form_Element_Select('templateid');
		$form['templateid']->setLabel('PURCHASE_ORDERS_TEMPLATE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['language'] = new Zend_Form_Element_Select('language');
		$form['language']->setLabel('PURCHASE_ORDERS_LANGUAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['state'] = new Zend_Form_Element_Text('state');
		$form['state']->setLabel('PURCHASE_ORDERS_STATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$this->addElements($form);
	}
}
