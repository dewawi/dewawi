<?php

class Contacts_Form_Contact extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'contactid',
			'label' => 'CONTACTS_CONTACT_ID',
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'catid',
			'label' => 'CONTACTS_CATEGORY',
			'options'=> ['0' => 'CATEGORIES_MAIN_CATEGORY'],
			'source' => 'category:contact',
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'name1',
			'label' => 'CONTACTS_NAME_ORGANISATION',
			'required' => true,
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'name2',
			'label' => 'CONTACTS_NAME_AFFIX',
			'format' => ['type' => 'string'],
			'tab' => 'overview'
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'department',
			'label' => 'CONTACTS_DEPARTMENT',
			'format' => ['type' => 'string'],
			'tab' => 'overview'
		]);

        $this->addElement([
            'type' => 'multi',
            'name' => 'address',
            'label' => 'CONTACTS_ADDRESS',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'address',
            'parentid' => 0,
            'rows' => [],
        ]);

        $this->addElement([
            'type' => 'multi',
            'name' => 'phone',
            'label' => 'CONTACTS_PHONE',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'phone',
            'parentid' => 0,
            'rows' => [],
        ]);

        $this->addElement([
            'type' => 'multi',
            'name' => 'email',
            'label' => 'CONTACTS_EMAIL',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'email',
            'parentid' => 0,
            'rows' => [],
        ]);

        $this->addElement([
            'type' => 'multi',
            'name' => 'internet',
            'label' => 'CONTACTS_INTERNET',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'internet',
            'parentid' => 0,
            'rows' => [],
        ]);

        /*$this->addElement([
            'type' => 'multi',
            'name' => 'tags',
            'label' => 'CONTACTS_TAGS',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'tags',
            'parentid' => 0,
            'rows' => [],
        ]);*/

        /*$this->addElement([
            'type' => 'multi',
            'name' => 'comments',
            'label' => 'CONTACTS_COMMENTS',
            'tab' => 'overview',
            'col' => 6,
            'module' => 'contacts',
            'controller' => 'comments',
            'parentid' => 0,
            'rows' => [],
        ]);*/

		$this->addElement([
			'name' => 'info',
			'type' => 'textarea',
			'label' => 'CONTACTS_INFO_INTERNAL',
			'info' => 'Interne Informationen werden nicht auf Angeboten, Rechnungen etc. angezeigt.',
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 75,
				'rows' => 10,
			],
			'tab' => 'overview',
		]);

	}
}

/*


		$form['debitornumber'] = new Zend_Form_Element_Text('debitornumber');
		$form['debitornumber']->setLabel('CONTACTS_DEBITOR_NUMBER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['taxnumber'] = new Zend_Form_Element_Text('taxnumber');
		$form['taxnumber']->setLabel('CONTACTS_TAX_NUMBER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('CONTACTS_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['taxfree'] = new Zend_Form_Element_Checkbox('taxfree');
		$form['taxfree']->setLabel('CONTACTS_TAX_FREE');

		$form['priceruleamount'] = new Zend_Form_Element_Text('priceruleamount');
		$form['priceruleamount']->setLabel('CONTACTS_PRICE_RULE_AMOUNT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'number')
			->setAttrib('size', '30');

		$form['priceruleaction'] = new Zend_Form_Element_Select('priceruleaction');
		$form['priceruleaction']->setLabel('CONTACTS_PRICE_RULE_APPLY')
			->addMultiOption('', 'CONTACTS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['paymentmethod'] = new Zend_Form_Element_Select('paymentmethod');
		$form['paymentmethod']->setLabel('CONTACTS_PAYMENT_METHOD')
			->addMultiOption('', 'CONTACTS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['paymentterm'] = new Zend_Form_Element_Text('paymentterm');
		$form['paymentterm']->setLabel('CONTACTS_PAYMENT_TERM')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['currency'] = new Zend_Form_Element_Select('currency');
		$form['currency']->setLabel('CONTACTS_CURRENCY')
			->addMultiOption(0, 'CONTACTS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['cashdiscountdays'] = new Zend_Form_Element_Text('cashdiscountdays');
		$form['cashdiscountdays']->setLabel('CONTACTS_CASH_DISCOUNT_DAYS')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['cashdiscountpercent'] = new Zend_Form_Element_Text('cashdiscountpercent');
		$form['cashdiscountpercent']->setLabel('CONTACTS_CASH_DISCOUNT_DAYS_PERCENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'number')
			->setAttrib('size', '30');

		$this->addElements($form);
	}
}




		$form['name1'] = new Zend_Form_Element_Text('name1');
		$form['name1']->setLabel('CONTACTS_NAME_ORGANISATION')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');	

		$form['name2'] = new Zend_Form_Element_Text('name2');
		$form['name2']->setLabel('CONTACTS_NAME_AFFIX')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['department'] = new Zend_Form_Element_Text('department');
		$form['department']->setLabel('CONTACTS_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');




		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['contactid'] = new Zend_Form_Element_Text('contactid');
		$form['contactid']->setLabel('CONTACTS_CONTACT_ID')
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

		$this->addElement([
			'type' => 'select',
			'name' => 'type',
			'label' => 'CONTACTS_TYPE',
			'options' => [
				'customer' => 'CONTACTS_CUSTOMER',
				'supplier' => 'CONTACTS_SUPPLIER',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);




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
		$form['info']->setLabel('CONTACTS_INFO_INTERNAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('CONTACTS_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');


*/
