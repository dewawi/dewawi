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

		$this->addElement([
			'type' => 'multi',
			'name' => 'tags',
			'label' => 'CONTACTS_TAGS',
			'tab' => 'overview',
			'col' => 6,
			'module' => 'contacts',
			'controller' => 'tag',
			'parentid' => 0,
			'rows' => [],
		]);

		$this->addElement([
			'type' => 'multi',
			'name' => 'comments',
			'label' => 'CONTACTS_COMMENTS',
			'tab' => 'overview',
			'col' => 6,
			'module' => 'contacts',
			'controller' => 'comment',
			'parentid' => 0,
			'rows' => [],
		]);

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

		$this->addElement([
			'type' => 'multi',
			'name' => 'comments',
			'label' => 'CONTACTS_CONTACT_PERSONS',
			'tab' => 'contactperson',
			'col' => 6,
			'module' => 'contacts',
			'controller' => 'contactperson',
			'parentid' => 0,
			'rows' => [],
		]);

		$this->addElement([
			'name' => 'debitornumber',
			'type' => 'text',
			'label' => 'CONTACTS_DEBITOR_NUMBER',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'taxnumber',
			'type' => 'text',
			'label' => 'CONTACTS_TAX_NUMBER',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'vatin',
			'type' => 'text',
			'label' => 'CONTACTS_VATIN',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'taxfree',
			'type' => 'checkbox',
			'label' => 'CONTACTS_TAX_FREE',
			'tab' => 'payment',
		]);

		$this->addElement([
			'name' => 'priceruleamount',
			'type' => 'text',
			'label' => 'CONTACTS_PRICE_RULE_AMOUNT',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'number',
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'priceruleaction',
			'type' => 'select',
			'label' => 'CONTACTS_PRICE_RULE_APPLY',
			'tab' => 'payment',
			'options' => [
				'' => 'CONTACTS_NONE',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'paymentmethod',
			'type' => 'select',
			'label' => 'CONTACTS_PAYMENT_METHOD',
			'tab' => 'payment',
			'options' => [
				'' => 'CONTACTS_NONE',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'paymentterm',
			'type' => 'text',
			'label' => 'CONTACTS_PAYMENT_TERM',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'currency',
			'type' => 'select',
			'label' => 'CONTACTS_CURRENCY',
			'tab' => 'payment',
			'required' => true,
			'options' => [
				'0' => 'CONTACTS_NONE',
			],
			'default' => '0',
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'cashdiscountdays',
			'type' => 'text',
			'label' => 'CONTACTS_CASH_DISCOUNT_DAYS',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 30,
			],
		]);

		$this->addElement([
			'name' => 'cashdiscountpercent',
			'type' => 'text',
			'label' => 'CONTACTS_CASH_DISCOUNT_DAYS_PERCENT',
			'tab' => 'payment',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'number',
				'size' => 30,
			],
		]);

	}
}

/*



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



		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('CONTACTS_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');


*/
