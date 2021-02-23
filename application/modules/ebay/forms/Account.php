<?php

class Ebay_Form_Account extends Zend_Form
{
	public function init()
	{
		$this->setName('account');

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

		$form['locale'] = new Zend_Form_Element_Text('locale');
		$form['locale']->setLabel('EBAY_LOCALE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['measurementsystem'] = new Zend_Form_Element_Text('measurementsystem');
		$form['measurementsystem']->setLabel('EBAY_MEASUREMENT_SYSTEM')
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
