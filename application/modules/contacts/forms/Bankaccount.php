<?php

class Contacts_Form_Bankaccount extends Zend_Form
{
	public function init()
	{
		$this->setName('bankaccount');

		$form = array();

		$form['iban'] = new Zend_Form_Element_Text('iban');
		$form['iban']->setLabel('CONTACTS_BANK_IBAN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '35')
			->removeDecorator('label');

		$form['bic'] = new Zend_Form_Element_Text('bic');
		$form['bic']->setLabel('CONTACTS_BANK_BIC')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '2')
			->removeDecorator('label');

		$this->addElements($form);
	}
}
