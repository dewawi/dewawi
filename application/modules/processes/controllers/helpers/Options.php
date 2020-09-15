<?php

class Processes_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form) {

		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');
		$options['categories'] = $categories;

		//Get countries
		$countryDb = new Application_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$options['countries'] = $countries;

		//Get states
		$stateDb = new Application_Model_DbTable_State();
		$states = $stateDb->getStates();
		$options['states'] = $states;

		//Get date ranges
		$daterangeDb = new Application_Model_DbTable_Daterange();
		$dateranges = $daterangeDb->getDateranges();
		$options['dateranges'] = $dateranges;

		//Get payment status
		$paymentstatusDb = new Application_Model_DbTable_Paymentstatus();
		$paymentstatus = $paymentstatusDb->getPaymentstatus();
		$options['paymentstatus'] = $paymentstatus;

		//Get delivery status
		$deliverystatusDb = new Application_Model_DbTable_Deliverystatus();
		$deliverystatus = $deliverystatusDb->getDeliverystatus();
		$options['deliverystatus'] = $deliverystatus;

		//Get supplier order status
		$supplierorderstatusDb = new Application_Model_DbTable_Supplierorderstatus();
		$supplierorderstatus = $supplierorderstatusDb->getSupplierorderstatus();
		$options['supplierorderstatus'] = $supplierorderstatus;

		//Get payment methods
		$paymentmethodDb = new Application_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodDb->getPaymentmethods();
		$options['paymentmethods'] = $paymentmethods;

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();
		$options['shippingmethods'] = $shippingmethods;

		//Set form options
		$MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);
		if(isset($form->paymentmethod) && isset($options['paymentmethods'])) $form->paymentmethod->addMultiOptions($options['paymentmethods']);
		if(isset($form->shippingmethod) && isset($options['shippingmethods'])) $form->shippingmethod->addMultiOptions($options['shippingmethods']);

		return $options;
	}
}
