<?php

class Contacts_Form_Contact extends Zend_Form
{
	public function init()
	{
		$this->setName('contact');

		$form = array();

		$form['id'] = new Zend_Form_Element_Text('id');
		$form['id']->setLabel('CONTACTS_CONTACT_ID')
			->addFilter('Int')
			->setAttrib('readonly', 'readonly');

		$form['catid'] = new Zend_Form_Element_Select('catid');
		$form['catid']->setLabel('CONTACTS_CATEGORY')
			->addMultiOption(0, 'CONTACTS_NOT_CATEGORIZED')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->setLabel('CONTACTS_TYPE')
			//->setRequired(true)
			->addMultiOption('customer', 'CONTACTS_CUSTOMER')
			->addMultiOption('supplier', 'CONTACTS_SUPPLIER');
			//->addValidator('NotEmpty');

		$form['name1'] = new Zend_Form_Element_Text('name1');
		$form['name1']->setLabel('CONTACTS_NAME')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');	

		$form['name2'] = new Zend_Form_Element_Text('name2');
		$form['name2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['department'] = new Zend_Form_Element_Text('department');
		$form['department']->setLabel('CONTACTS_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['phone'] = new Zend_Form_Element_Text('phone');
		$form['phone']->setLabel('CONTACTS_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['street'] = new Zend_Form_Element_Textarea('street');
		$form['street']->setLabel('CONTACTS_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['postcode'] = new Zend_Form_Element_Text('postcode');
		$form['postcode']->setLabel('CONTACTS_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['city'] = new Zend_Form_Element_Text('city');
		$form['city']->setLabel('CONTACTS_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['country'] = new Zend_Form_Element_Select('country');
		$form['country']->setLabel('CONTACTS_COUNTRY')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['shippingname1'] = new Zend_Form_Element_Text('shippingname1');
		$form['shippingname1']->setLabel('CONTACTS_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingname2'] = new Zend_Form_Element_Text('shippingname2');
		$form['shippingname2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingdepartment'] = new Zend_Form_Element_Text('shippingdepartment');
		$form['shippingdepartment']->setLabel('CONTACTS_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingstreet'] = new Zend_Form_Element_Textarea('shippingstreet');
		$form['shippingstreet']->setLabel('CONTACTS_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['shippingpostcode'] = new Zend_Form_Element_Text('shippingpostcode');
		$form['shippingpostcode']->setLabel('CONTACTS_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcity'] = new Zend_Form_Element_Text('shippingcity');
		$form['shippingcity']->setLabel('CONTACTS_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcountry'] = new Zend_Form_Element_Text('shippingcountry');
		$form['shippingcountry']->setLabel('CONTACTS_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingphone'] = new Zend_Form_Element_Text('shippingphone');
		$form['shippingphone']->setLabel('CONTACTS_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('CONTACTS_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('CONTACTS_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['taxfree'] = new Zend_Form_Element_Checkbox('taxfree');
		$form['taxfree']->setLabel('CONTACTS_TAX_FREE');

		$this->addElements($form);
	}
}
