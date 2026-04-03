<?php

class Processes_Form_Processpos extends DEEC_Form
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
			'name' => 'deliveryorderid',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_ORDER_ID',
			'format' => ['type' => 'int'],
			'attribs' => ['size' => 5],
		]);

		$this->addElement([
			'name' => 'purchaseorderid',
			'type' => 'text',
			'label' => 'PROCESSES_PURCHASE_ORDER_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'supplierid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 5],
		]);

		$this->addElement([
			'name' => 'notes',
			'type' => 'textarea',
			'label' => 'PROCESSES_NOTES',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 20,
				'rows' => 3,
			],
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
			'attribs' => [
				'class' => 'itemType',
			],
		]);

		$this->addElement([
			'name' => 'deliveryorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_ORDER_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'purchaseorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_PURCHASE_ORDER_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'shippingmethod',
			'type' => 'select',
			'label' => 'PROCESSES_SHIPPING_METHOD',
			'options' => [
				'' => 'PROCESSES_NONE',
			],
		]);

		$this->addElement([
			'name' => 'shipmentnumber',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPMENT_NUMBER',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'shipmentdate',
			'type' => 'text',
			'label' => 'PROCESSES_SHIPMENT_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'deliverydate',
			'type' => 'text',
			'label' => 'PROCESSES_DELIVERY_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
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
			'attribs' => [
				'class' => 'deliveryStatus',
			],
		]);

		$this->addElement([
			'name' => 'sku',
			'type' => 'text',
			'label' => 'PROCESSES_SKU',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'POSITIONS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'image',
			'type' => 'hidden',
			'format' => ['type' => 'string'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'textarea',
			'label' => 'POSITIONS_DESCRIPTION',
			'format' => ['type' => 'string'],
			'attribs' => [
				'cols' => 75,
				'rows' => 1,
			],
		]);

		$this->addElement([
			'name' => 'price',
			'type' => 'text',
			'label' => 'POSITIONS_PRICE',
			'required' => true,
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
		]);

		$this->addElement([
			'name' => 'quantity',
			'type' => 'text',
			'label' => 'POSITIONS_QUANTITY',
			'required' => true,
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
		]);

		$this->addElement([
			'name' => 'uom',
			'type' => 'select',
			'label' => 'POSITIONS_UOM',
			'required' => true,
			'options' => [
				'0' => 'POSITIONS_NONE',
			],
		]);

		$this->addElement([
			'name' => 'suppliername',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_NAME',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'suppliersalesorderid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_SALES_ORDER_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'suppliersalesorderdate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_SALES_ORDER_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'supplierinvoiceid',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'supplierinvoicedate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'supplierinvoicetotal',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_INVOICE_TOTAL',
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
				'size' => 10,
			],
		]);

		$this->addElement([
			'name' => 'supplierpaymentdate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_PAYMENT_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'supplierdeliverydate',
			'type' => 'text',
			'label' => 'PROCESSES_SUPPLIER_DELIVERY_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
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
		]);

		$this->addElement([
			'name' => 'servicedate',
			'type' => 'text',
			'label' => 'PROCESSES_SERVICE_DATE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'class' => 'datePickerLive',
				'size' => 9,
			],
		]);

		$this->addElement([
			'name' => 'serviceexecutedby',
			'type' => 'text',
			'label' => 'PROCESSES_SERVICE_EXECUTED_BY',
			'format' => ['type' => 'string'],
			'attribs' => ['size' => 10],
		]);

		$this->addElement([
			'name' => 'servicecompleted',
			'type' => 'checkbox',
			'label' => 'PROCESSES_SERVICE_COMPLETED',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'ordering',
			'type' => 'select',
			'required' => true,
			'format' => ['type' => 'int'],
			'options' => [],
		]);
	}
}
