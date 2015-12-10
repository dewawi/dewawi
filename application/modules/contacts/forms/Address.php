<?php

class Contacts_Form_Address extends Zend_Form
{
	public function init()
	{
		$this->setName('internet');

		$form = array();

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

		$form['street'] = new Zend_Form_Element_Textarea('street');
		$form['street']->setLabel('CONTACTS_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3')
			->setAttrib('data-controller', '3')
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

		$form['phone'] = new Zend_Form_Element_Text('phone');
		$form['phone']->setLabel('CONTACTS_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$this->addElements($form);
	}
}
