<?php

class Contacts_Form_Address extends Zend_Form
{
	public function init()
	{
		$this->setName('address');

		$form = array();

		$form['name1'] = new Zend_Form_Element_Text('name1');
		$form['name1']->removeDecorator('label')
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
		$form['street']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '25')
			->setAttrib('rows', '2');

		$form['postcode'] = new Zend_Form_Element_Text('postcode');
		$form['postcode']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['city'] = new Zend_Form_Element_Text('city');
		$form['city']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['country'] = new Zend_Form_Element_Select('country');
		$form['country']->removeDecorator('label')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['phone'] = new Zend_Form_Element_Text('phone');
		$form['phone']->removeDecorator('label')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->removeDecorator('label')
			//->setRequired(true)
			->addMultiOption('none', '')
			->addMultiOption('billing', 'CONTACTS_BILLING_ADDRESS')
			->addMultiOption('shipping', 'CONTACTS_SHIPPING_ADDRESS')
			->addMultiOption('other', 'CONTACTS_OTHER_ADDRESS');

		$this->addElements($form);
	}
}
