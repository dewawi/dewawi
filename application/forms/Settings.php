<?php

class Application_Form_Settings extends Zend_Form
{
	public function init()
	{
		$this->setName('invoice');

		$company = new Zend_Form_Element_Text('company');
		$company->setLabel('SETTINGS_COMPANY')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '30');

		$address = new Zend_Form_Element_Textarea('address');
		$address->setLabel('SETTINGS_ADDRESS')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '2');

		$postcode = new Zend_Form_Element_Text('postcode');
		$postcode->setLabel('SETTINGS_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$city = new Zend_Form_Element_Text('city');
		$city->setLabel('SETTINGS_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$country = new Zend_Form_Element_Text('country');
		$country->setLabel('SETTINGS_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$website = new Zend_Form_Element_Text('website');
		$website->setLabel('SETTINGS_WEBSITE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$language = new Zend_Form_Element_Select('language');
		$language->setLabel('SETTINGS_LANGUAGE')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$this->addElements(array($company, $address, $postcode, $city, $country, $website, $language));
	}
}
