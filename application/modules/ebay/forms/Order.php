<?php

class Ebay_Form_Order extends Zend_Form
{
	public function init()
	{
		$this->setName('order');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['userid'] = new Zend_Form_Element_Text('userid');
		$form['userid']->setLabel('EBAY_USER_ID')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['orderid'] = new Zend_Form_Element_Text('orderid');
		$form['orderid']->setLabel('EBAY_ORDER_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['contactid'] = new Zend_Form_Element_Text('contactid');
		$form['contactid']->setLabel('EBAY_CONTACT_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['invoiceid'] = new Zend_Form_Element_Text('invoiceid');
		$form['invoiceid']->setLabel('EBAY_INVOICE_ID')
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
