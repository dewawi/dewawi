<?php

class Campaigns_Form_Campaign extends Zend_Form
{
	public function init()
	{
		$this->setName('campaign');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['salesorderid'] = new Zend_Form_Element_Text('salesorderid');
		$form['salesorderid']->setLabel('TASKS_SALES_ORDER_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['invoiceid'] = new Zend_Form_Element_Text('invoiceid');
		$form['invoiceid']->setLabel('TASKS_INVOICE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['prepaymentinvoiceid'] = new Zend_Form_Element_Text('prepaymentinvoiceid');
		$form['prepaymentinvoiceid']->setLabel('TASKS_PREPAYMENT_INVOICE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['deliveryorderid'] = new Zend_Form_Element_Text('deliveryorderid');
		$form['deliveryorderid']->setLabel('TASKS_DELIVERY_ORDER_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['creditnoteid'] = new Zend_Form_Element_Text('creditnoteid');
		$form['creditnoteid']->setLabel('TASKS_CREDIT_NOTE_ID')
			//->addFilter('Int')
			->setAttrib('size', '5');

		$form['purchaseorderid'] = new Zend_Form_Element_Text('purchaseorderid');
		$form['purchaseorderid']->setLabel('TASKS_PURCHASE_ORDER_ID')
			->addFilter('Int')
			->setAttrib('size', '10');

		$form['customerid'] = new Zend_Form_Element_Text('customerid');
		$form['customerid']->setLabel('TASKS_CUSTOMER_ID')
			->addFilter('Int')
			->setAttrib('size', '5')
			->setAttrib('readonly', 'readonly');

		$form['supplierid'] = new Zend_Form_Element_Text('supplierid');
		$form['supplierid']->setLabel('TASKS_SUPPLIER_ID')
			->addFilter('Int')
			->setAttrib('size', '5');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('TASKS_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['contactcatid'] = new Zend_Form_Element_Select('contactcatid');
		$form['contactcatid']->setLabel('PRICE_RULES_CONTACT_CATEGORY')
			->addMultiOption(0, 'PRICE_RULES_ITEMS_ALL_CATEGORIES')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['contactsubcat'] = new Zend_Form_Element_Checkbox('contactsubcat');
		$form['contactsubcat']->setLabel('PRICE_RULES_CONTACT_APPLY_TO_SUBCATEGORIES');

		$form['priority'] = new Zend_Form_Element_Select('priority');
		$form['priority']->setLabel('TASKS_PRIORITY')
			->addMultiOption('0', 'TASKS_PRIORITY_NORMAL')
			->addMultiOption('1', 'TASKS_PRIORITY_LOWEST')
			->addMultiOption('2', 'TASKS_PRIORITY_LOW')
			->addMultiOption('3', 'TASKS_PRIORITY_HIGH')
			->addMultiOption('4', 'TASKS_PRIORITY_HIGHEST')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['startdate'] = new Zend_Form_Element_Text('startdate');
		$form['startdate']->setLabel('TASKS_START_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['duedate'] = new Zend_Form_Element_Text('duedate');
		$form['duedate']->setLabel('TASKS_DUE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['reminder'] = new Zend_Form_Element_Checkbox('reminder');
		$form['reminder']->setLabel('TASKS_REMINDER');

		$form['remindertype'] = new Zend_Form_Element_Select('remindertype');
		$form['remindertype']->setLabel('TASKS_REMINDER_TYPE')
			->addMultiOption('email', 'EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('TASKS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('TASKS_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '15');

		$form['notes'] = new Zend_Form_Element_Textarea('notes');
		$form['notes']->setLabel('TASKS_NOTES')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '45')
			->setAttrib('rows', '6');

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('TASKS_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('TASKS_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['vatin'] = new Zend_Form_Element_Text('vatin');
		$form['vatin']->setLabel('TASKS_VATIN')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['campaigndate'] = new Zend_Form_Element_Text('campaigndate');
		$form['campaigndate']->setLabel('TASKS_QUOTE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['salesorderdate'] = new Zend_Form_Element_Text('salesorderdate');
		$form['salesorderdate']->setLabel('TASKS_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['invoicedate'] = new Zend_Form_Element_Text('invoicedate');
		$form['invoicedate']->setLabel('TASKS_INVOICE_DATE')
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
		$form['prepaymentinvoicedate']->setLabel('TASKS_PREPAYMENT_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliveryorderdate'] = new Zend_Form_Element_Text('deliveryorderdate');
		$form['deliveryorderdate']->setLabel('TASKS_DELIVERY_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['creditnotedate'] = new Zend_Form_Element_Text('creditnotedate');
		$form['creditnotedate']->setLabel('TASKS_CREDIT_NOTE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['purchaseorderdate'] = new Zend_Form_Element_Text('purchaseorderdate');
		$form['purchaseorderdate']->setLabel('TASKS_PURCHASE_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['paymentmethod'] = new Zend_Form_Element_Select('paymentmethod');
		$form['paymentmethod']->setLabel('TASKS_PAYMENT_METHOD')
			->addMultiOption('', 'TASKS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['shippingmethod'] = new Zend_Form_Element_Select('shippingmethod');
		$form['shippingmethod']->setLabel('TASKS_SHIPPING_METHOD')
			->addMultiOption('', 'TASKS_NONE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['shipmentnumber'] = new Zend_Form_Element_Text('shipmentnumber');
		$form['shipmentnumber']->setLabel('TASKS_SHIPMENT_NUMBER')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['shipmentdate'] = new Zend_Form_Element_Text('shipmentdate');
		$form['shipmentdate']->setLabel('TASKS_SHIPMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverydate'] = new Zend_Form_Element_Text('deliverydate');
		$form['deliverydate']->setLabel('TASKS_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['deliverystatus'] = new Zend_Form_Element_Select('deliverystatus');
		$form['deliverystatus']->setLabel('TASKS_DELIVERY_STATUS')
			->addMultiOption('deliveryIsWaiting', 'TASKS_DELIVERY_IS_WAITING')
			->addMultiOption('partialDelivered', 'TASKS_PARTIAL_DElIVERED')
			->addMultiOption('deliveryCompleted', 'TASKS_DELIVERY_COMPLETED')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['itemtype'] = new Zend_Form_Element_Select('itemtype');
		$form['itemtype']->setLabel('TASKS_ITEM_TYPE')
			->addMultiOption('', 'ITEMS_NONE')
			->addMultiOption('stockItem', 'ITEMS_STOCK_ITEM')
			->addMultiOption('deliveryItem', 'ITEMS_DELIVERY_ITEM')
			->addMultiOption('service', 'ITEMS_SERVICE')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['suppliername'] = new Zend_Form_Element_Text('suppliername');
		$form['suppliername']->setLabel('TASKS_SUPPLIER_NAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['supplierordered'] = new Zend_Form_Element_Checkbox('supplierordered');
		$form['supplierordered']->setLabel('TASKS_SUPPLIER_ORDERED');

		$form['suppliersalesorderid'] = new Zend_Form_Element_Text('suppliersalesorderid');
		$form['suppliersalesorderid']->setLabel('TASKS_SUPPLIER_SALES_ORDER_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['suppliersalesorderdate'] = new Zend_Form_Element_Text('suppliersalesorderdate');
		$form['suppliersalesorderdate']->setLabel('TASKS_SUPPLIER_SALES_ORDER_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['supplierinvoiceid'] = new Zend_Form_Element_Text('supplierinvoiceid');
		$form['supplierinvoiceid']->setLabel('TASKS_SUPPLIER_INVOICE_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '10');

		$form['supplierinvoicedate'] = new Zend_Form_Element_Text('supplierinvoicedate');
		$form['supplierinvoicedate']->setLabel('TASKS_SUPPLIER_INVOICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['supplierinvoicetotal'] = new Zend_Form_Element_Text('supplierinvoicetotal');
		$form['supplierinvoicetotal']->setLabel('TASKS_SUPPLIER_INVOICE_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['supplierpaymentdate'] = new Zend_Form_Element_Text('supplierpaymentdate');
		$form['supplierpaymentdate']->setLabel('TASKS_SUPPLIER_PAYMENT_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['supplierdeliverydate'] = new Zend_Form_Element_Text('supplierdeliverydate');
		$form['supplierdeliverydate']->setLabel('TASKS_SUPPLIER_DELIVERY_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['supplierorderstatus'] = new Zend_Form_Element_Select('supplierorderstatus');
		$form['supplierorderstatus']->setLabel('TASKS_SUPPLIER_ORDER_STATUS')
			->addMultiOption('supplierNotOrdered', 'TASKS_SUPPLIER_NOT_ORDERED')
			->addMultiOption('supplierOrdered', 'TASKS_SUPPLIER_ORDERED')
			->addMultiOption('supplierPayed', 'TASKS_SUPPLIER_PAYED')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'supplierOrderStatus');

		$form['servicedate'] = new Zend_Form_Element_Text('servicedate');
		$form['servicedate']->setLabel('TASKS_SERVICE_DATE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('class', 'datePicker')
			->setAttrib('size', '9');

		$form['servicecompleted'] = new Zend_Form_Element_Checkbox('servicecompleted');
		$form['servicecompleted']->setLabel('TASKS_SERVICE_COMPLETED');

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
		$form['customerinfo']->setLabel('TASKS_CONTACT_INFO')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '62')
			->setAttrib('rows', '30')
			->setAttrib('readonly', 'readonly');

		$form['total'] = new Zend_Form_Element_Text('total');
		$form['total']->setLabel('TASKS_TOTAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('Float')
			->setAttrib('class', 'number')
			->setAttrib('size', '10');

		$form['responsible'] = new Zend_Form_Element_Select('responsible');
		$form['responsible']->setLabel('TASKS_RESPONSIBLE_PERSON')
			->setRequired(true)
			->addValidator('NotEmpty');

		$form['state'] = new Zend_Form_Element_Select('state');
		$form['state']->setLabel('TASKS_STATE')
			->addMultiOption('100', 'STATES_CREATED')
			->addMultiOption('101', 'STATES_IN_TASK')
			->addMultiOption('102', 'STATES_PLEASE_CHECK')
			->addMultiOption('103', 'STATES_PLEASE_DELETE')
			->addMultiOption('104', 'STATES_RELEASED')
			->addMultiOption('105', 'STATES_COMPLETED')
			->addMultiOption('106', 'STATES_CANCELLED')
			->addFilter('StripTags')
			->addFilter('StringTrim');

		$form['activated'] = new Zend_Form_Element_Checkbox('activated');
		$form['activated']->setLabel('TASKS_ACTIVATED')
			->addFilter('Int');

		$this->addElements($form);
	}
}
