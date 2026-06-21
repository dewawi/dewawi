<?php

class Tasks_Form_Task extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'title',
			'label' => 'TASKS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 40],
			'tab' => 'overview',
			'col' => 12,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'priority',
			'label' => 'TASKS_PRIORITY',
			'options' => [
				'0' => 'TASKS_PRIORITY_NORMAL',
				'1' => 'TASKS_PRIORITY_LOWEST',
				'2' => 'TASKS_PRIORITY_LOW',
				'3' => 'TASKS_PRIORITY_HIGH',
				'4' => 'TASKS_PRIORITY_HIGHEST',
			],
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'state',
			'label' => 'TASKS_STATE',
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_TASK',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
				'105' => 'STATES_COMPLETED',
				'106' => 'STATES_CANCELLED',
			],
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'col' => 6,
		]);

		foreach([
			'startdate' => 'TASKS_START_DATE',
			'duedate' => 'TASKS_DUE_DATE',
			'taskdate' => 'TASKS_QUOTE_DATE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'format' => ['type' => 'date'],
				'tab' => 'overview',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'reminder',
			'label' => 'TASKS_REMINDER',
			'format' => ['type' => 'bool'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'remindertype',
			'label' => 'TASKS_REMINDER_TYPE',
			'options' => [
				'email' => 'EMAIL',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		foreach([
			'description' => ['TASKS_DESCRIPTION', 45, 6],
			'notes' => ['TASKS_NOTES', 45, 6],
			'info' => ['TASKS_INFO', 45, 15],
		] as $name => $cfg) {
			$this->addElement([
				'type' => 'textarea',
				'name' => $name,
				'label' => $cfg[0],
				'format' => ['type' => 'string'],
				'attribs' => ['cols' => $cfg[1], 'rows' => $cfg[2]],
				'tab' => 'overview',
			]);
		}

		foreach([
			'header' => 'TASKS_HEADER',
			'footer' => 'TASKS_FOOTER',
		] as $name => $label) {
			$this->addElement([
				'type' => 'textarea',
				'name' => $name,
				'label' => $label,
				'format' => [
					'type' => 'html',
					'allowTags' => ['a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'],
					'allowAttribs' => ['style','title','href'],
				],
				'attribs' => ['cols' => 75, 'rows' => 18, 'class' => 'editor'],
				'tab' => 'texts',
			]);
		}

		$this->addCustomerFields();
		$this->addShippingFields();
		$this->addDocumentFields();
		$this->addPaymentFields();
		$this->addDeliveryFields();
		$this->addSupplierFields();
		$this->addServiceFields();
	}

	protected function addCustomerFields(): void
	{
		foreach([
			'contactid' => ['TASKS_CUSTOMER_ID', 'int', ['size' => 5, 'readonly' => 'readonly']],
			'billingname1' => ['CONTACTS_NAME', 'string', ['size' => 30]],
			'billingname2' => ['', 'string', ['size' => 30]],
			'billingdepartment' => ['CONTACTS_DEPARTMENT', 'string', ['size' => 30]],
			'billingpostcode' => ['CONTACTS_POSTCODE', 'string', ['size' => 30]],
			'billingcity' => ['CONTACTS_CITY', 'string', ['size' => 30]],
			'billingcountry' => ['CONTACTS_COUNTRY', 'string', ['size' => 30]],
			'vatin' => ['TASKS_VATIN', 'string', ['size' => 12]],
		] as $name => $cfg) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $cfg[0],
				'format' => ['type' => $cfg[1]],
				'attribs' => $cfg[2],
				'tab' => 'customer',
				'section' => 'CONTACTS_CUSTOMER',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'textarea',
			'name' => 'billingstreet',
			'label' => 'CONTACTS_STREET',
			'format' => ['type' => 'string'],
			'attribs' => ['cols' => 30, 'rows' => 3],
			'tab' => 'customer',
			'section' => 'CONTACTS_CUSTOMER',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'currency',
			'label' => 'QUOTES_CURRENCY',
			'required' => true,
			'source' => 'currency',
			'format' => ['type' => 'string'],
			'tab' => 'customer',
			'section' => 'CONTACTS_CUSTOMER',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'taxfree',
			'label' => 'CONTACTS_TAX_FREE',
			'format' => ['type' => 'bool'],
			'tab' => 'customer',
			'section' => 'CONTACTS_CUSTOMER',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'textarea',
			'name' => 'customerinfo',
			'label' => 'TASKS_CONTACT_INFO',
			'format' => ['type' => 'string'],
			'attribs' => ['cols' => 62, 'rows' => 30, 'readonly' => 'readonly'],
			'tab' => 'customer',
		]);
	}

	protected function addShippingFields(): void
	{
		foreach([
			'shippingname1' => 'TASKS_SHIPPING_NAME',
			'shippingname2' => '',
			'shippingdepartment' => 'TASKS_SHIPPING_DEPARTMENT',
			'shippingpostcode' => 'TASKS_SHIPPING_POSTCODE',
			'shippingcity' => 'TASKS_SHIPPING_CITY',
			'shippingcountry' => 'TASKS_SHIPPING_COUNTRY',
			'shippingphone' => 'TASKS_SHIPPING_PHONE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'format' => ['type' => 'string'],
				'attribs' => ['size' => 30],
				'tab' => 'shipping',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'textarea',
			'name' => 'shippingstreet',
			'label' => 'TASKS_SHIPPING_STREET',
			'format' => ['type' => 'string'],
			'attribs' => ['cols' => 30, 'rows' => 3],
			'tab' => 'shipping',
			'col' => 6,
		]);
	}

	protected function addDocumentFields(): void
	{
		foreach([
			'salesorderid' => 'TASKS_SALES_ORDER_ID',
			'invoiceid' => 'TASKS_INVOICE_ID',
			'prepaymentinvoiceid' => 'TASKS_PREPAYMENT_INVOICE_ID',
			'deliveryorderid' => 'TASKS_DELIVERY_ORDER_ID',
			'creditnoteid' => 'TASKS_CREDIT_NOTE_ID',
			'purchaseorderid' => 'TASKS_PURCHASE_ORDER_ID',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'format' => ['type' => 'int'],
				'attribs' => ['size' => 10],
				'tab' => 'documents',
				'col' => 6,
			]);
		}

		foreach([
			'salesorderdate' => 'TASKS_SALES_ORDER_DATE',
			'invoicedate' => 'TASKS_INVOICE_DATE',
			'prepaymentinvoicedate' => 'TASKS_PREPAYMENT_INVOICE_DATE',
			'deliveryorderdate' => 'TASKS_DELIVERY_ORDER_DATE',
			'creditnotedate' => 'TASKS_CREDIT_NOTE_DATE',
			'purchaseorderdate' => 'TASKS_PURCHASE_ORDER_DATE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'format' => ['type' => 'date'],
				'tab' => 'documents',
				'col' => 6,
			]);
		}
	}

	protected function addPaymentFields(): void
	{
		$this->addElement([
			'type' => 'select',
			'name' => 'paymentmethod',
			'label' => 'TASKS_PAYMENT_METHOD',
			'options' => ['' => 'TASKS_NONE'],
			'source' => 'paymentmethod',
			'format' => ['type' => 'string'],
			'tab' => 'payment',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'paymentstatus',
			'label' => 'TASKS_PAYMENT_STATUS',
			'options' => [
				'waitingForPayment' => 'TASKS_WAITING_FOR_PAYMENT',
				'prepaymentReceived' => 'TASKS_PREPAYMENT_RECEIVED',
				'paymentCompleted' => 'TASKS_PAYMENT_COMPLETED',
			],
			'format' => ['type' => 'string'],
			'tab' => 'payment',
			'col' => 6,
		]);

		foreach([
			'total' => 'TASKS_TOTAL',
			'invoicetotal' => 'TASKS_INVOICE_TOTAL',
			'prepaymenttotal' => 'TASKS_PREPAYMENT_TOTAL',
			'creditnotetotal' => 'TASKS_CREDIT_NOTE_TOTAL',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'number', 'size' => 10],
				'format' => ['type' => 'decimal', 'precision' => 2],
				'tab' => 'payment',
				'col' => 6,
			]);
		}

		foreach([
			'paymentdate' => 'TASKS_PAYMENT_DATE',
			'prepaymentdate' => 'TASKS_PREPAYMENT_DATE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'format' => ['type' => 'date'],
				'tab' => 'payment',
				'col' => 6,
			]);
		}

		foreach([
			'prepayment' => 'TASKS_PREPAYMENT',
			'creditnote' => 'TASKS_CREDIT_NOTE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'checkbox',
				'name' => $name,
				'label' => $label,
				'format' => ['type' => 'bool'],
				'tab' => 'payment',
				'col' => 6,
			]);
		}
	}

	protected function addDeliveryFields(): void
	{
		$this->addElement([
			'type' => 'select',
			'name' => 'shippingmethod',
			'label' => 'TASKS_SHIPPING_METHOD',
			'options' => ['' => 'TASKS_NONE'],
			'source' => 'shippingmethod',
			'format' => ['type' => 'string'],
			'tab' => 'delivery',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'deliverystatus',
			'label' => 'TASKS_DELIVERY_STATUS',
			'options' => [
				'deliveryIsWaiting' => 'TASKS_DELIVERY_IS_WAITING',
				'partialDelivered' => 'TASKS_PARTIAL_DElIVERED',
				'deliveryCompleted' => 'TASKS_DELIVERY_COMPLETED',
			],
			'format' => ['type' => 'string'],
			'tab' => 'delivery',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'itemtype',
			'label' => 'TASKS_ITEM_TYPE',
			'options' => [
				'' => 'ITEMS_NONE',
				'stockItem' => 'ITEMS_STOCK_ITEM',
				'deliveryItem' => 'ITEMS_DELIVERY_ITEM',
				'service' => 'ITEMS_SERVICE',
			],
			'format' => ['type' => 'string'],
			'tab' => 'delivery',
			'col' => 6,
		]);

		foreach([
			'shipmentnumber' => 'TASKS_SHIPMENT_NUMBER',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'format' => ['type' => 'string'],
				'attribs' => ['size' => 10],
				'tab' => 'delivery',
				'col' => 6,
			]);
		}

		foreach([
			'shipmentdate' => 'TASKS_SHIPMENT_DATE',
			'deliverydate' => 'TASKS_DELIVERY_DATE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'format' => ['type' => 'date'],
				'tab' => 'delivery',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'editpositionsseparately',
			'label' => 'TASKS_EDIT_POSITIONS_SEPARATELY',
			'format' => ['type' => 'bool'],
			'tab' => 'delivery',
			'col' => 6,
		]);
	}

	protected function addSupplierFields(): void
	{
		foreach([
			'supplierid' => ['TASKS_SUPPLIER_ID', 'int'],
			'suppliername' => ['TASKS_SUPPLIER_NAME', 'string'],
			'suppliersalesorderid' => ['TASKS_SUPPLIER_SALES_ORDER_ID', 'string'],
			'supplierinvoiceid' => ['TASKS_SUPPLIER_INVOICE_ID', 'string'],
		] as $name => $cfg) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $cfg[0],
				'format' => ['type' => $cfg[1]],
				'attribs' => ['size' => 10],
				'tab' => 'supplier',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'supplierordered',
			'label' => 'TASKS_SUPPLIER_ORDERED',
			'format' => ['type' => 'bool'],
			'tab' => 'supplier',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'supplierorderstatus',
			'label' => 'TASKS_SUPPLIER_ORDER_STATUS',
			'options' => [
				'supplierNotOrdered' => 'TASKS_SUPPLIER_NOT_ORDERED',
				'supplierOrdered' => 'TASKS_SUPPLIER_ORDERED',
				'supplierPayed' => 'TASKS_SUPPLIER_PAYED',
			],
			'attribs' => ['class' => 'supplierOrderStatus'],
			'format' => ['type' => 'string'],
			'tab' => 'supplier',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'supplierinvoicetotal',
			'label' => 'TASKS_SUPPLIER_INVOICE_TOTAL',
			'attribs' => ['class' => 'number', 'size' => 10],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'supplier',
			'col' => 6,
		]);

		foreach([
			'suppliersalesorderdate' => 'TASKS_SUPPLIER_SALES_ORDER_DATE',
			'supplierinvoicedate' => 'TASKS_SUPPLIER_INVOICE_DATE',
			'supplierpaymentdate' => 'TASKS_SUPPLIER_PAYMENT_DATE',
			'supplierdeliverydate' => 'TASKS_SUPPLIER_DELIVERY_DATE',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => ['class' => 'datePicker', 'size' => 9],
				'format' => ['type' => 'date'],
				'tab' => 'supplier',
				'col' => 6,
			]);
		}
	}

	protected function addServiceFields(): void
	{
		$this->addElement([
			'type' => 'text',
			'name' => 'servicedate',
			'label' => 'TASKS_SERVICE_DATE',
			'attribs' => ['class' => 'datePicker', 'size' => 9],
			'format' => ['type' => 'date'],
			'tab' => 'service',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'servicecompleted',
			'label' => 'TASKS_SERVICE_COMPLETED',
			'format' => ['type' => 'bool'],
			'tab' => 'service',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'responsible',
			'label' => 'TASKS_RESPONSIBLE_PERSON',
			'required' => true,
			'source' => 'user',
			'format' => ['type' => 'int'],
			'tab' => 'service',
			'col' => 6,
		]);
	}
}
