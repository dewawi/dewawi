<?php

class Sales_Form_Invoice extends Zend_Form
{
	public function init()
	{
		$this->setName('invoice');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['invoiceid'] = new Zend_Form_Element_Text('invoiceid');
		$form['invoiceid']->setLabel('INVOICES_INVOICE_ID')
			->addFilter('Int')
			->setAttrib('size', '5')
			->setAttrib('readonly', 'readonly');

		$form['salesorderid'] = new Zend_Form_Element_Text('salesorderid');
		$form['salesorderid']->setLabel('INVOICES_SALES_ORDER_ID')
			->setAttrib('size', '5');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('INVOICES_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('INVOICES_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '50')
			->setAttrib('rows', '10');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('INVOICES_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('INVOICES_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('INVOICES_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('INVOICES_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['invoicedate'] = new Zend_Form_Element_Text('invoicedate');
		$form['invoicedate']->setLabel('INVOICES_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['salesorderdate'] = new Zend_Form_Element_Text('salesorderdate');
		$form['salesorderdate']->setLabel('INVOICES_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['orderdate'] = new Zend_Form_Element_Text('orderdate');
		$form['orderdate']->setLabel('INVOICES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverydate'] = new Zend_Form_Element_Text('deliverydate');
		$form['deliverydate']->setLabel('INVOICES_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['paymentmethod'] = new Zend_Form_Element_Select('paymentmethod');
		$form['paymentmethod']->setLabel('INVOICES_PAYMENT_METHOD')
			->addMultiOption('', 'INVOICES_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['shippingmethod'] = new Zend_Form_Element_Select('shippingmethod');
		$form['shippingmethod']->setLabel('INVOICES_SHIPPING_METHOD')
			->addMultiOption('', 'INVOICES_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['contactid'] = new Zend_Form_Element_Text('contactid');
		$form['contactid']->setLabel('CONTACTS_CONTACT_ID')
			->addFilter('Int')
			->setAttrib('readonly', 'readonly');

		$form['billingname1'] = new Zend_Form_Element_Text('billingname1');
		$form['billingname1']->setLabel('CONTACTS_NAME')
			->setAttrib('size', '40');

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
		$form['shippingname1']->setLabel('INVOICES_SHIPPING_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingname2'] = new Zend_Form_Element_Text('shippingname2');
		$form['shippingname2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingdepartment'] = new Zend_Form_Element_Text('shippingdepartment');
		$form['shippingdepartment']->setLabel('INVOICES_SHIPPING_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingstreet'] = new Zend_Form_Element_Textarea('shippingstreet');
		$form['shippingstreet']->setLabel('INVOICES_SHIPPING_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['shippingpostcode'] = new Zend_Form_Element_Text('shippingpostcode');
		$form['shippingpostcode']->setLabel('INVOICES_SHIPPING_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcity'] = new Zend_Form_Element_Text('shippingcity');
		$form['shippingcity']->setLabel('INVOICES_SHIPPING_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcountry'] = new Zend_Form_Element_Text('shippingcountry');
		$form['shippingcountry']->setLabel('INVOICES_SHIPPING_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingphone'] = new Zend_Form_Element_Text('shippingphone');
		$form['shippingphone']->setLabel('INVOICES_SHIPPING_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['prepayment'] = new Zend_Form_Element_Text('prepayment');
		$form['prepayment']->setLabel('INVOICES_PREPAYMENT')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '20');

		$form['currency'] = new Zend_Form_Element_Select('currency');
		$form['currency']->setLabel('INVOICES_CURRENCY')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['taxfree'] = new Zend_Form_Element_Checkbox('taxfree');
		$form['taxfree']->setLabel('CONTACTS_TAX_FREE');

		$form['contactinfo'] = new Zend_Form_Element_Textarea('contactinfo');
		$form['contactinfo']->setLabel('INVOICES_CONTACT_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30')
			->setAttrib('readonly', 'readonly');

		$form['templateid'] = new Zend_Form_Element_Select('templateid');
		$form['templateid']->setLabel('INVOICES_TEMPLATE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['language'] = new Zend_Form_Element_Select('language');
		$form['language']->setLabel('INVOICES_LANGUAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['state'] = new Zend_Form_Element_Text('state');
		$form['state']->setLabel('INVOICES_STATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$this->addElements($form);
	}
}
