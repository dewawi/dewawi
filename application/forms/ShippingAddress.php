<?php

class Application_Form_ShippingAddress extends Zend_Form
{
	public function init()
	{
		$this->setName('shipping_address');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$customerid = new Zend_Form_Element_Hidden('customerid');
		$customerid->addFilter('Int');

		$address1 = new Zend_Form_Element_Text('shipping_address1');
		$address1->setLabel('Street Address')
			->setAttrib('id', 'address1')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty');

		$address2 = new Zend_Form_Element_Text('shipping_address2');
		$address2->setLabel('')
			->setAttrib('id', 'address2')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$address3 = new Zend_Form_Element_Text('shipping_address3');
		$address3->setLabel('')
			->setAttrib('id', 'address3')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$postcode = new Zend_Form_Element_Text('shipping_postcode');
		$postcode->setLabel('Postcode')
			->setAttrib('id', 'postcode')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$country = new Zend_Form_Element_Text('shipping_country');
		$country->setLabel('Country')
			->setAttrib('id', 'country')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty');

		$city = new Zend_Form_Element_Text('shipping_city');
		$city->setLabel('City')
			->setAttrib('id', 'city')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty');

		$phone = new Zend_Form_Element_Text('shipping_phone');
		$phone->setLabel('Phone')
			->setAttrib('id', 'phone')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$fax = new Zend_Form_Element_Text('shipping_fax');
		$fax->setLabel('Fax')
			->setAttrib('id', 'fax')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$email = new Zend_Form_Element_Text('shipping_email');
		$email->setLabel('E-Mail')
			->setAttrib('id', 'email')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$internet = new Zend_Form_Element_Text('shipping_internet');
		$internet->setLabel('Internet')
			->setAttrib('id', 'internet')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$submit = new Zend_Form_Element_Button('shipping_submit');
		$submit->setAttrib('onclick', 'addAddress()');

		$this->addElements(array($id, $customerid, $address1, $address2, $address3, $postcode, $city, $country, $phone, $fax, $email, $internet, $submit));
	}
}
