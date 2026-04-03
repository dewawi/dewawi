<?php

class Contacts_Form_Address extends DEEC_Form
{
	public function __construct()
	{
		/*$this->addElement([
			'name' => 'name1',
			'type' => 'text',
			'required' => true,
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'name2',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'department',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 12,
		]);*/

		$this->addElement([
			'name' => 'street',
			'type' => 'textarea',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 40,
				'rows' => 5,
			],
		]);

		$this->addElement([
			'name' => 'postcode',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'city',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'country',
			'options'=> [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'phone',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'options' => [
				'billing' => 'CONTACTS_BILLING_ADDRESS',
				'shipping' => 'CONTACTS_SHIPPING_ADDRESS',
				'other' => 'CONTACTS_OTHER_ADDRESS',
			],
			'format' => ['type' => 'string'],
		]);
	}
}
/*
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
}*/
