<?php

class Application_Form_Address extends Zend_Form
{
	public function init()
	{
		$this->setName('address');

		$id = new Zend_Form_Element_Hidden('id');
		$id->addFilter('Int');

		$customerid = new Zend_Form_Element_Hidden('customerid');
		$customerid->addFilter('Int');

		$street = new Zend_Form_Element_Textarea('street');
		$street->setLabel('Street Address')
			->setAttrib('id', 'street')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('cols', '23')
			->setAttrib('rows', '2');

		$postcode = new Zend_Form_Element_Text('postcode');
		$postcode->setLabel('Postcode')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$city = new Zend_Form_Element_Text('city');
		$city->setLabel('City')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$country = new Zend_Form_Element_Text('country');
		$country->setLabel('Country')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$submit = new Zend_Form_Element_Button('submit');
		$submit->setAttrib('onclick', 'addAddress()');

		$this->addElements(array($id, $customerid, $street, $postcode, $city, $country, $submit));
	}
}
