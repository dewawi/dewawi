<?php

class Processes_Form_Process extends Zend_Form
{
	public function init()
	{
		$this->setName('process');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['salesorderid'] = new Zend_Form_Element_Text('salesorderid');
		$form['salesorderid']->setLabel('PROCESSES_SALES_ORDER_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['invoiceid'] = new Zend_Form_Element_Text('invoiceid');
		$form['invoiceid']->setLabel('PROCESSES_INVOICE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['prepaymentinvoiceid'] = new Zend_Form_Element_Text('prepaymentinvoiceid');
		$form['prepaymentinvoiceid']->setLabel('PROCESSES_PREPAYMENT_INVOICE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['deliveryorderid'] = new Zend_Form_Element_Text('deliveryorderid');
		$form['deliveryorderid']->setLabel('PROCESSES_DELIVERY_ORDER_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['creditnoteid'] = new Zend_Form_Element_Text('creditnoteid');
		$form['creditnoteid']->setLabel('PROCESSES_CREDIT_NOTE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['purchaseorderid'] = new Zend_Form_Element_Text('purchaseorderid');
		$form['purchaseorderid']->setLabel('PROCESSES_PURCHASE_ORDER_ID')
			->addFilter('Int')
			->setAttrib('size', '10');

		$form['customerid'] = new Zend_Form_Element_Text('customerid');
		$form['customerid']->setLabel('PROCESSES_CUSTOMER_ID')
			->addFilter('Int')
			->setAttrib('size', '5')
			->setAttrib('readonly', 'readonly');

		$form['supplierid'] = new Zend_Form_Element_Text('supplierid');
		$form['supplierid']->setLabel('PROCESSES_SUPPLIER_ID')
			->addFilter('Int')
			->setAttrib('size', '5');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('PROCESSES_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('PROCESSES_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '15');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('PROCESSES_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('PROCESSES_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('PROCESSES_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('PROCESSES_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['processdate'] = new Zend_Form_Element_Text('processdate');
		$form['processdate']->setLabel('PROCESSES_QUOTE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['salesorderdate'] = new Zend_Form_Element_Text('salesorderdate');
		$form['salesorderdate']->setLabel('PROCESSES_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['invoicedate'] = new Zend_Form_Element_Text('invoicedate');
		$form['invoicedate']->setLabel('PROCESSES_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['invoicetotal'] = new Zend_Form_Element_Text('invoicetotal');
		$form['invoicetotal']->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['prepaymentinvoicedate'] = new Zend_Form_Element_Text('prepaymentinvoicedate');
		$form['prepaymentinvoicedate']->setLabel('PROCESSES_PREPAYMENT_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliveryorderdate'] = new Zend_Form_Element_Text('deliveryorderdate');
		$form['deliveryorderdate']->setLabel('PROCESSES_DELIVERY_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['creditnotedate'] = new Zend_Form_Element_Text('creditnotedate');
		$form['creditnotedate']->setLabel('PROCESSES_CREDIT_NOTE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['purchaseorderdate'] = new Zend_Form_Element_Text('purchaseorderdate');
		$form['purchaseorderdate']->setLabel('PROCESSES_PURCHASE_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['paymentmethod'] = new Zend_Form_Element_Select('paymentmethod');
		$form['paymentmethod']->setLabel('PROCESSES_PAYMENT_METHOD')
			->addMultiOption('', 'PROCESSES_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

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
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverydate'] = new Zend_Form_Element_Text('deliverydate');
		$form['deliverydate']->setLabel('PROCESSES_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverystatus'] = new Zend_Form_Element_Select('deliverystatus');
		$form['deliverystatus']->setLabel('PROCESSES_DELIVERY_STATUS')
			->addMultiOption('deliveryIsWaiting', 'PROCESSES_DELIVERY_IS_WAITING')
			->addMultiOption('partialDelivered', 'PROCESSES_PARTIAL_DElIVERED')
			->addMultiOption('deliveryCompleted', 'PROCESSES_DELIVERY_COMPLETED')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['itemtype'] = new Zend_Form_Element_Select('itemtype');
		$form['itemtype']->setLabel('PROCESSES_ITEM_TYPE')
			->addMultiOption('', 'ITEMS_NONE')
			->addMultiOption('stockItem', 'ITEMS_STOCK_ITEM')
			->addMultiOption('deliveryItem', 'ITEMS_DELIVERY_ITEM')
			->addMultiOption('service', 'ITEMS_SERVICE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['suppliername'] = new Zend_Form_Element_Text('suppliername');
		$form['suppliername']->setLabel('PROCESSES_SUPPLIER_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['supplierordered'] = new Zend_Form_Element_Checkbox('supplierordered');
		$form['supplierordered']->setLabel('PROCESSES_SUPPLIER_ORDERED');

		$form['suppliersalesorderid'] = new Zend_Form_Element_Text('suppliersalesorderid');
		$form['suppliersalesorderid']->setLabel('PROCESSES_SUPPLIER_SALES_ORDER_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['suppliersalesorderdate'] = new Zend_Form_Element_Text('suppliersalesorderdate');
		$form['suppliersalesorderdate']->setLabel('PROCESSES_SUPPLIER_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
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
			->setAttrib('class', 'datePicker')
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
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['supplierdeliverydate'] = new Zend_Form_Element_Text('supplierdeliverydate');
		$form['supplierdeliverydate']->setLabel('PROCESSES_SUPPLIER_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
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
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['servicecompleted'] = new Zend_Form_Element_Checkbox('servicecompleted');
		$form['servicecompleted']->setLabel('PROCESSES_SERVICE_COMPLETED');

		$form['billingname1'] = new Zend_Form_Element_Text('billingname1');
		$form['billingname1']->setLabel('CONTACTS_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['billingname2'] = new Zend_Form_Element_Text('billingname2');
		$form['billingname2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['billingdepartment'] = new Zend_Form_Element_Text('billingdepartment');
		$form['billingdepartment']->setLabel('CONTACTS_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['billingstreet'] = new Zend_Form_Element_Textarea('billingstreet');
		$form['billingstreet']->setLabel('CONTACTS_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['billingpostcode'] = new Zend_Form_Element_Text('billingpostcode');
		$form['billingpostcode']->setLabel('CONTACTS_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['billingcity'] = new Zend_Form_Element_Text('billingcity');
		$form['billingcity']->setLabel('CONTACTS_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['billingcountry'] = new Zend_Form_Element_Text('billingcountry');
		$form['billingcountry']->setLabel('CONTACTS_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['currency'] = new Zend_Form_Element_Select('currency');
		$form['currency']->setLabel('QUOTES_CURRENCY')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['taxfree'] = new Zend_Form_Element_Checkbox('taxfree');
		$form['taxfree']->setLabel('CONTACTS_TAX_FREE');

		$form['customerinfo'] = new Zend_Form_Element_Textarea('customerinfo');
		$form['customerinfo']->setLabel('PROCESSES_CONTACT_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30')
			->setAttrib('readonly', 'readonly');

		$form['shippingname1'] = new Zend_Form_Element_Text('shippingname1');
		$form['shippingname1']->setLabel('PROCESSES_SHIPPING_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingname2'] = new Zend_Form_Element_Text('shippingname2');
		$form['shippingname2']->setLabel('')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingdepartment'] = new Zend_Form_Element_Text('shippingdepartment');
		$form['shippingdepartment']->setLabel('PROCESSES_SHIPPING_DEPARTMENT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['shippingstreet'] = new Zend_Form_Element_Textarea('shippingstreet');
		$form['shippingstreet']->setLabel('PROCESSES_SHIPPING_STREET')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '30')
			->setAttrib('rows', '3');

		$form['shippingpostcode'] = new Zend_Form_Element_Text('shippingpostcode');
		$form['shippingpostcode']->setLabel('PROCESSES_SHIPPING_POSTCODE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcity'] = new Zend_Form_Element_Text('shippingcity');
		$form['shippingcity']->setLabel('PROCESSES_SHIPPING_CITY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingcountry'] = new Zend_Form_Element_Text('shippingcountry');
		$form['shippingcountry']->setLabel('PROCESSES_SHIPPING_COUNTRY')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['shippingphone'] = new Zend_Form_Element_Text('shippingphone');
		$form['shippingphone']->setLabel('PROCESSES_SHIPPING_PHONE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['total'] = new Zend_Form_Element_Text('total');
		$form['total']->setLabel('PROCESSES_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['paymentdate'] = new Zend_Form_Element_Text('paymentdate');
		$form['paymentdate']->setLabel('PROCESSES_PAYMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['prepayment'] = new Zend_Form_Element_Checkbox('prepayment');
		$form['prepayment']->setLabel('PROCESSES_PREPAYMENT')
			->setDecorators(array('Label', 'ViewHelper'));

		$form['prepaymenttotal'] = new Zend_Form_Element_Text('prepaymenttotal');
		$form['prepaymenttotal']->setLabel('PROCESSES_PREPAYMENT_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['prepaymentdate'] = new Zend_Form_Element_Text('prepaymentdate');
		$form['prepaymentdate']->setLabel('PROCESSES_PREPAYMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['paymentstatus'] = new Zend_Form_Element_Select('paymentstatus');
		$form['paymentstatus']->setLabel('PROCESSES_PAYMENT_STATUS')
			->addMultiOption('waitingForPayment', 'PROCESSES_WAITING_FOR_PAYMENT')
			->addMultiOption('prepaymentReceived', 'PROCESSES_PREPAYMENT_RECEIVED')
			->addMultiOption('paymentCompleted', 'PROCESSES_PAYMENT_COMPLETED')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['creditnote'] = new Zend_Form_Element_Checkbox('creditnote');
		$form['creditnote']->setLabel('PROCESSES_CREDIT_NOTE')
			->setDecorators(array('Label', 'ViewHelper'));

		$form['creditnotetotal'] = new Zend_Form_Element_Text('creditnotetotal');
		$form['creditnotetotal']->setLabel('PROCESSES_CREDIT_NOTE_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['editpositionsseparately'] = new Zend_Form_Element_Checkbox('editpositionsseparately');
		$form['editpositionsseparately']->setLabel('PROCESSES_EDIT_POSITIONS_SEPARATELY');

		$form['state'] = new Zend_Form_Element_Select('state');
		$form['state']->setLabel('PROCESSES_STATE')
			->addMultiOption('100', 'STATES_CREATED')
			->addMultiOption('101', 'STATES_IN_PROCESS')
			->addMultiOption('102', 'STATES_PLEASE_CHECK')
			->addMultiOption('103', 'STATES_PLEASE_DELETE')
			->addMultiOption('104', 'STATES_RELEASED')
			->addMultiOption('105', 'STATES_COMPLETED')
			->addMultiOption('106', 'STATES_CANCELLED')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$this->addElements($form);
	}
}
