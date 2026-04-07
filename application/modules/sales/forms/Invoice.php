<?php

class Sales_Form_Invoice extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'invoiceid',
			'type' => 'text',
			'label' => 'INVOICES_INVOICE_ID',
			'format' => ['type' => 'int'],
			'attribs' => [
				'readonly' => 'readonly',
				'size' => 5,
			],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'salesorderid',
			'type' => 'text',
			'label' => 'INVOICES_SALES_ORDER_ID',
			'format' => ['type' => 'int'],
			'attribs' => [
				'readonly' => 'readonly',
				'size' => 5,
			],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'INVOICES_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 9,
		]);

		$this->addElement([
			'name' => 'subject',
			'type' => 'text',
			'label' => 'INVOICES_SUBJECT',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'reference',
			'type' => 'text',
			'label' => 'INVOICES_REFERENCE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'quotedate',
			'type' => 'text',
			'label' => 'INVOICES_QUOTE_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePicker',
				'size' => 9,
			],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'deliverydate',
			'type' => 'text',
			'label' => 'INVOICES_DELIVERY_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePicker',
				'size' => 9,
			],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'prepayment',
			'type' => 'text',
			'label' => 'INVOICES_PREPAYMENT',
			'format' => ['type' => 'int'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'currency',
			'type' => 'select',
			'label' => 'INVOICES_CURRENCY',
			'required' => true,
			'options' => [],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'paymentmethod',
			'type' => 'select',
			'label' => 'INVOICES_PAYMENT_METHOD',
			'options' => ['' => 'INVOICES_NONE'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingmethod',
			'type' => 'select',
			'label' => 'INVOICES_SHIPPING_METHOD',
			'options' => ['' => 'INVOICES_NONE'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'info',
			'type' => 'textarea',
			'label' => 'INVOICES_INFO',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 50,
				'rows' => 10,
			],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'notes',
			'type' => 'textarea',
			'label' => 'INVOICES_NOTES',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 45,
				'rows' => 6,
			],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'header',
			'type' => 'textarea',
			'label' => 'INVOICES_HEADER',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'textarea',
			'label' => 'INVOICES_FOOTER',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'select',
			'type' => 'button',
			'label' => 'TOOLBAR_SELECT',
			'wrap' => false,
			'attribs' => [
				'class' => 'select poplight',
				'rel' => 'addCustomer',
			],
			'tab' => 'customer',
		]);

		$this->addElement([
			'name' => 'contactid',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_ID',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'billingname1',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_NAME',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingname2',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_NAME2',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingdepartment',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_DEPARTMENT',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingstreet',
			'type' => 'textarea',
			'label' => 'INVOICES_CONTACT_STREET',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 30,
				'rows' => 3,
			],
			'tab' => 'customer',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'billingpostcode',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_POSTCODE',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'billingcity',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_CITY',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 5,
		]);

		$this->addElement([
			'name' => 'billingcountry',
			'type' => 'text',
			'label' => 'INVOICES_CONTACT_COUNTRY',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'vatin',
			'type' => 'text',
			'label' => 'INVOICES_VATIN',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'customer',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'taxfree',
			'type' => 'checkbox',
			'label' => 'INVOICES_TAX_FREE',
			'format' => ['type' => 'int'],
			'tab' => 'customer',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'shippingname1',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_NAME',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingname2',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_NAME2',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingdepartment',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_DEPARTMENT',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingstreet',
			'type' => 'textarea',
			'label' => 'INVOICES_SHIPPING_STREET',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 30,
				'rows' => 3,
			],
			'tab' => 'shipping',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'shippingpostcode',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_POSTCODE',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'shippingcity',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_CITY',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 5,
		]);

		$this->addElement([
			'name' => 'shippingcountry',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_COUNTRY',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'shippingphone',
			'type' => 'text',
			'label' => 'INVOICES_SHIPPING_PHONE',
			'format' => ['type' => 'string'],
			'tab' => 'shipping',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'templateid',
			'type' => 'select',
			'label' => 'INVOICES_TEMPLATE',
			'source' => 'template',
			'options' => [],
			'tab' => 'finish',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'pdfshowprices',
			'type' => 'checkbox',
			'label' => 'INVOICES_PDF_SHOW_PRICES',
			'format' => ['type' => 'int'],
			'tab' => 'finish',
			'default' => 1,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'pdfshowdiscounts',
			'type' => 'checkbox',
			'label' => 'INVOICES_PDF_SHOW_DISCOUNTS',
			'format' => ['type' => 'int'],
			'tab' => 'finish',
			'default' => 0,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'pdfshowoptions',
			'type' => 'checkbox',
			'label' => 'INVOICES_PDF_SHOW_OPTIONS',
			'format' => ['type' => 'int'],
			'tab' => 'finish',
			'default' => 0,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'pdfshowattributes',
			'type' => 'checkbox',
			'label' => 'INVOICES_PDF_SHOW_ATTRIBUTES',
			'format' => ['type' => 'int'],
			'tab' => 'finish',
			'default' => 0,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'pdfshowcover',
			'type' => 'checkbox',
			'label' => 'INVOICES_PDF_SHOW_COVER',
			'format' => ['type' => 'int'],
			'tab' => 'finish',
			'default' => 0,
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'INVOICES_LANGUAGE',
			'source' => 'language',
			'options' => [],
			'tab' => 'finish',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'state',
			'type' => 'text',
			'label' => 'INVOICES_STATE',
			'source' => 'state',
			'format' => ['type' => 'string'],
			'tab' => 'finish',
			'col' => 3,
		]);
	}
}
