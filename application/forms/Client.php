<?php

class Application_Form_Client extends Zend_Form
{
    public function init()
    {
        $this->setName('client');

        $id = new Zend_Form_Element_Hidden('id');
        $id->addFilter('Int');

        $company = new Zend_Form_Element_Text('company');
        $company->setLabel('SETTINGS_COMPANY')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $address = new Zend_Form_Element_Text('address');
        $address->setLabel('SETTINGS_ADDRESS')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $postcode = new Zend_Form_Element_Text('postcode');
        $postcode->setLabel('SETTINGS_POSTCODE')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $city = new Zend_Form_Element_Text('city');
        $city->setLabel('SETTINGS_CITY')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $country = new Zend_Form_Element_Text('country');
        $country->setLabel('SETTINGS_COUNTRY')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $email = new Zend_Form_Element_Text('email');
        $email->setLabel('SETTINGS_EMAIL')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $website = new Zend_Form_Element_Text('website');
        $website->setLabel('SETTINGS_WEBSITE')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $language = new Zend_Form_Element_Text('language');
        $language->setLabel('SETTINGS_LANGUAGE')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $this->addElements(array($id, $company, $address, $postcode, $city, $country, $email, $website, $language));
    }
}
