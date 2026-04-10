<?php

class Processes_Form_Process extends DEEC_Form
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
			'name' => 'title',
			'type' => 'text',
			'label' => 'PROCESSES_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'salesorderid',
			'type' => 'text',
			'label' => 'PROCESSES_SALES_ORDER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'salesorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_SALES_ORDER_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'info',
			'type' => 'textarea',
			'label' => 'PROCESSES_INFO',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 45,
				'rows' => 15,
			],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'notes',
			'type' => 'textarea',
			'label' => 'PROCESSES_NOTES',
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
			'label' => 'PROCESSES_HEADER',
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
			'label' => 'PROCESSES_FOOTER',
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
			'name' => 'vatin',
			'type' => 'text',
			'label' => 'PROCESSES_VATIN',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 12],
			'tab' => 'overview',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'editpositionsseparately',
			'type' => 'checkbox',
			'label' => 'PROCESSES_EDIT_POSITIONS_SEPARATELY',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'state',
			'type' => 'select',
			'label' => 'PROCESSES_STATE',
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_PROCESS',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
				'105' => 'STATES_COMPLETED',
				'106' => 'STATES_CANCELLED',
			],
			'tab' => 'overview',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'deliveryorderid',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_ORDER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'delivery',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'deliverydate',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'delivery',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'deliveryorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_ORDER_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'delivery',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'invoiceid',
			'type' => 'text',
			'label' => 'PROCESSES_INVOICE_ID',
			'format' => ['type' => 'string'],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'invoicedate',
			'type' => 'text',
			'label' => 'PROCESSES_INVOICE_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'invoicetotal',
			'type' => 'text',
			'label' => 'PROCESSES_INVOICE_TOTAL',
			'format' => ['type' => 'decimal'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'paymentmethod',
			'type' => 'select',
			'label' => 'PROCESSES_PAYMENT_METHOD',
			'options' => [
				'' => 'PROCESSES_NONE',
			],
			'tab' => 'billing',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'currency',
			'type' => 'select',
			'label' => 'PROCESSES_CURRENCY',
			'required' => true,
			'options' => [],
			'source' => 'currency',
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'taxfree',
			'type' => 'checkbox',
			'label' => 'PROCESSES_TAX_FREE',
			'format' => ['type' => 'int'],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'total',
			'type' => 'text',
			'label' => 'PROCESSES_TOTAL',
			'format' => ['type' => 'decimal'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'paymentdate',
			'type' => 'text',
			'label' => 'PROCESSES_PAYMENT_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'prepayment',
			'type' => 'checkbox',
			'label' => 'PROCESSES_PREPAYMENT',
			'format' => ['type' => 'int'],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'prepaymenttotal',
			'type' => 'text',
			'label' => 'PROCESSES_PREPAYMENT_TOTAL',
			'format' => ['type' => 'decimal'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'prepaymentdate',
			'type' => 'text',
			'label' => 'PROCESSES_PREPAYMENT_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'prepaymentinvoiceid',
			'type' => 'text',
			'label' => 'PROCESSES_PREPAYMENT_INVOICE_ID',
			'format' => ['type' => 'string'],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'prepaymentinvoicedate',
			'type' => 'text',
			'label' => 'PROCESSES_PREPAYMENT_INVOICE_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'paymentstatus',
			'type' => 'select',
			'label' => 'PROCESSES_PAYMENT_STATUS',
			'options' => [
				'waitingForPayment' => 'PROCESSES_WAITING_FOR_PAYMENT',
				'prepaymentReceived' => 'PROCESSES_PREPAYMENT_RECEIVED',
				'paymentCompleted' => 'PROCESSES_PAYMENT_COMPLETED',
			],
			'tab' => 'billing',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'creditnote',
			'type' => 'checkbox',
			'label' => 'PROCESSES_CREDIT_NOTE',
			'format' => ['type' => 'int'],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'creditnotetotal',
			'type' => 'text',
			'label' => 'PROCESSES_CREDIT_NOTE_TOTAL',
			'format' => ['type' => 'decimal'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
			'tab' => 'billing',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'creditnoteid',
			'type' => 'text',
			'label' => 'PROCESSES_CREDIT_NOTE_ID',
			'format' => ['type' => 'string'],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'creditnotedate',
			'type' => 'text',
			'label' => 'PROCESSES_CREDIT_NOTE_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'billing',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'supplierid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'supplier',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'purchaseorderid',
			'type' => 'text',
			'label' => 'PROCESSES_PURCHASE_ORDER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'supplier',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'purchaseorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_PURCHASE_ORDER_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'supplier',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'itemtype',
			'type' => 'select',
			'label' => 'PROCESSES_ITEM_TYPE',
			'options' => [
				'' => 'ITEMS_NONE',
				'stockItem' => 'ITEMS_STOCK_ITEM',
				'deliveryItem' => 'ITEMS_DELIVERY_ITEM',
				'service' => 'ITEMS_SERVICE',
			],
			'tab' => 'supplier',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'suppliername',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_NAME',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierordered',
			'type' => 'checkbox',
			'label' => 'PROCESSES_SUPPLIER_ORDERED',
			'format' => ['type' => 'int'],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'suppliersalesorderid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_SALES_ORDER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'suppliersalesorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_SALES_ORDER_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierinvoiceid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_ID',
			'format' => ['type' => 'string'],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierinvoicedate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierinvoicetotal',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_TOTAL',
			'format' => ['type' => 'decimal'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierpaymentdate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_PAYMENT_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierdeliverydate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_DELIVERY_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'supplier',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'supplierorderstatus',
			'type' => 'select',
			'label' => 'PROCESSES_SUPPLIER_ORDER_STATUS',
			'options' => [
				'supplierNotOrdered' => 'PROCESSES_SUPPLIER_NOT_ORDERED',
				'supplierOrdered' => 'PROCESSES_SUPPLIER_ORDERED',
				'supplierPayed' => 'PROCESSES_SUPPLIER_PAYED',
			],
			'attribs' => [
				'class' => 'supplierOrderStatus',
			],
			'tab' => 'supplier',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingmethod',
			'type' => 'select',
			'label' => 'PROCESSES_SHIPPING_METHOD',
			'options' => [
				'' => 'PROCESSES_NONE',
			],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shipmentnumber',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPMENT_NUMBER',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
			'tab' => 'shipping',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'shipmentdate',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPMENT_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'shipping',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'deliverystatus',
			'type' => 'select',
			'label' => 'PROCESSES_DELIVERY_STATUS',
			'options' => [
				'deliveryIsWaiting' => 'PROCESSES_DELIVERY_IS_WAITING',
				'partialDelivered' => 'PROCESSES_PARTIAL_DElIVERED',
				'deliveryCompleted' => 'PROCESSES_DELIVERY_COMPLETED',
			],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'servicedate',
			'type' => 'text',
			'label' => 'PROCESSES_SERVICE_DATE',
			'format' => [
				'type' => 'date',
				'pattern' => 'Y-m-d',
				'displayPattern' => 'd.m.Y',
			],
			'attribs' => [
				'class' => 'datePicker',
			],
			'tab' => 'service',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'servicecompleted',
			'type' => 'checkbox',
			'label' => 'PROCESSES_SERVICE_COMPLETED',
			'format' => ['type' => 'int'],
			'tab' => 'service',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'contactid',
			'type' => 'text',
			'label' => 'PROCESSES_CUSTOMER_ID',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'billingname1',
			'type' => 'text',
			'label' => 'PROCESSES_CUSTOMER_NAME',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingname2',
			'type' => 'text',
			'label' => 'PROCESSES_CUSTOMER_NAME2',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingdepartment',
			'type' => 'text',
			'label' => 'PROCESSES_CUSTOMER_DEPARTMENT',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'billingstreet',
			'type' => 'textarea',
			'label' => 'CONTACTS_STREET',
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
			'label' => 'CONTACTS_POSTCODE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'billingcity',
			'type' => 'text',
			'label' => 'CONTACTS_CITY',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'billingcountry',
			'type' => 'text',
			'label' => 'CONTACTS_COUNTRY',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'customer',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'customerinfo',
			'type' => 'textarea',
			'label' => 'PROCESSES_CONTACT_INFO',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 62,
				'rows' => 30,
				'readonly' => 'readonly',
			],
			'tab' => 'customer',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'shippingname1',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_NAME',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 50],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingname2',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_NAME2',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 50],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingdepartment',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_DEPARTMENT',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 50],
			'tab' => 'shipping',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shippingstreet',
			'type' => 'textarea',
			'label' => 'PROCESSES_SHIPPING_STREET',
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
			'label' => 'PROCESSES_SHIPPING_POSTCODE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'shipping',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'shippingcity',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_CITY',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'shipping',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'shippingcountry',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_COUNTRY',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'shipping',
			'col' => 4,
		]);

		$this->addElement([
			'name' => 'shippingphone',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPPING_PHONE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 30],
			'tab' => 'shipping',
			'col' => 4,
		]);
	}
}
