<?php

class Processes_Form_Processpos extends Zend_Form
{
	public function init()
	{
		$this->setName('processpos');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['deliveryorderid'] = new Zend_Form_Element_Text('deliveryorderid');
		$form['deliveryorderid']->setLabel('PROCESSES_DELIVERY_ORDER_ID')
			->addFilter('Int')
			->setAttrib('size', '5');

		$form['purchaseorderid'] = new Zend_Form_Element_Text('purchaseorderid');
		$form['purchaseorderid']->setLabel('PROCESSES_PURCHASE_ORDER_ID')
			//->addFilter('Int')
			->setAttrib('size', '10');

		$form['supplierid'] = new Zend_Form_Element_Text('supplierid');
		$form['supplierid']->setLabel('PROCESSES_SUPPLIER_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('PROCESSES_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '20')
			->setAttrib('rows', '3');

		$form['itemtype'] = new Zend_Form_Element_Select('itemtype');
		$form['itemtype']->setLabel('PROCESSES_ITEM_TYPE')
			->addMultiOption('', 'ITEMS_NONE')
			->addMultiOption('stockItem', 'ITEMS_STOCK_ITEM')
			->addMultiOption('deliveryItem', 'ITEMS_DELIVERY_ITEM')
			->addMultiOption('service', 'ITEMS_SERVICE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'itemType');

		$form['deliveryorderdate'] = new Zend_Form_Element_Text('deliveryorderdate');
		$form['deliveryorderdate']->setLabel('PROCESSES_DELIVERY_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['purchaseorderdate'] = new Zend_Form_Element_Text('purchaseorderdate');
		$form['purchaseorderdate']->setLabel('PROCESSES_PURCHASE_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['shippingmethod'] = new Zend_Form_Element_Select('shippingmethod');
		$form['shippingmethod']->setLabel('PROCESSES_SHIPPING_METHOD')
			->addMultiOption('', 'PROCESSES_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['shipmentnumber'] = new Zend_Form_Element_Text('shipmentnumber');
		$form['shipmentnumber']->setLabel('PROCESSES_SHIPMENT_NUMBER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['shipmentdate'] = new Zend_Form_Element_Text('shipmentdate');
		$form['shipmentdate']->setLabel('PROCESSES_SHIPMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['deliverydate'] = new Zend_Form_Element_Text('deliverydate');
		$form['deliverydate']->setLabel('PROCESSES_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['deliverystatus'] = new Zend_Form_Element_Select('deliverystatus');
		$form['deliverystatus']->setLabel('PROCESSES_DELIVERY_STATUS')
			->addMultiOption('deliveryIsWaiting', 'PROCESSES_DELIVERY_IS_WAITING')
			->addMultiOption('partialDelivered', 'PROCESSES_PARTIAL_DElIVERED')
			->addMultiOption('deliveryCompleted', 'PROCESSES_DELIVERY_COMPLETED')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'deliveryStatus');

		$form['sku'] = new Zend_Form_Element_Text('sku');
		$form['sku']->setLabel('PROCESSES_SKU')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '10');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('POSITIONS_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['image'] = new Zend_Form_Element_Hidden('image');
		$form['image']->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('POSITIONS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '1');

		$form['price'] = new Zend_Form_Element_Text('price');
		$form['price']->setLabel('POSITIONS_PRICE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['quantity'] = new Zend_Form_Element_Text('quantity');
		$form['quantity']->setLabel('POSITIONS_QUANTITY')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->addValidator('NotEmpty')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['uom'] = new Zend_Form_Element_Select('uom');
		$form['uom']->setLabel('POSITIONS_UOM')
			->addMultiOption(0, 'POSITIONS_NONE')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['suppliername'] = new Zend_Form_Element_Text('suppliername');
		$form['suppliername']->setLabel('PROCESSES_SUPPLIER_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['suppliersalesorderid'] = new Zend_Form_Element_Text('suppliersalesorderid');
		$form['suppliersalesorderid']->setLabel('PROCESSES_SUPPLIER_SALES_ORDER_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['suppliersalesorderdate'] = new Zend_Form_Element_Text('suppliersalesorderdate');
		$form['suppliersalesorderdate']->setLabel('PROCESSES_SUPPLIER_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['supplierinvoiceid'] = new Zend_Form_Element_Text('supplierinvoiceid');
		$form['supplierinvoiceid']->setLabel('PROCESSES_SUPPLIER_INVOICE_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['supplierinvoicedate'] = new Zend_Form_Element_Text('supplierinvoicedate');
		$form['supplierinvoicedate']->setLabel('PROCESSES_SUPPLIER_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['supplierinvoicetotal'] = new Zend_Form_Element_Text('supplierinvoicetotal');
		$form['supplierinvoicetotal']->setLabel('PROCESSES_SUPPLIER_INVOICE_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['supplierpaymentdate'] = new Zend_Form_Element_Text('supplierpaymentdate');
		$form['supplierpaymentdate']->setLabel('PROCESSES_SUPPLIER_PAYMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['supplierdeliverydate'] = new Zend_Form_Element_Text('supplierdeliverydate');
		$form['supplierdeliverydate']->setLabel('PROCESSES_SUPPLIER_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['supplierorderstatus'] = new Zend_Form_Element_Select('supplierorderstatus');
		$form['supplierorderstatus']->setLabel('PROCESSES_SUPPLIER_ORDER_STATUS')
			->addMultiOption('supplierNotOrdered', 'PROCESSES_SUPPLIER_NOT_ORDERED')
			->addMultiOption('supplierOrdered', 'PROCESSES_SUPPLIER_ORDERED')
			->addMultiOption('supplierPayed', 'PROCESSES_SUPPLIER_PAYED')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'supplierOrderStatus');

		$form['servicedate'] = new Zend_Form_Element_Text('servicedate');
		$form['servicedate']->setLabel('PROCESSES_SERVICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePickerLive')
			->setAttrib('size', '9');

		$form['serviceexecutedby'] = new Zend_Form_Element_Text('serviceexecutedby');
		$form['serviceexecutedby']->setLabel('PROCESSES_SERVICE_EXECUTED_BY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['servicecompleted'] = new Zend_Form_Element_Checkbox('servicecompleted');
		$form['servicecompleted']->setLabel('PROCESSES_SERVICE_COMPLETED')
			->setDecorators(array('Label', 'ViewHelper'));

		$form['ordering'] = new Zend_Form_Element_Select('ordering');
		$form['ordering']->addFilter('Int')
			->setRequired(true)
			->addValidator('NotEmpty');

		foreach($form as $element) {
			if(!$element->getLabel()) $element->setDecorators(array('ViewHelper'));
		}
		$this->addElements($form);
		//$this->setElementDecorators(array('ViewHelper'));
	}
}
