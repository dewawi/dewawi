<?php

class Contacts_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form)
	{
		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');
		$options['categories'] = $categories;

		//Get countries
		$countryDb = new Application_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$options['countries'] = $countries;

		//Get payment methods
		$paymentmethodDb = new Application_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodDb->getPaymentmethods();
		$options['paymentmethods'] = $paymentmethods;

		//Get currencies
		$currencyDb = new Application_Model_DbTable_Currency();
		$currencies = $currencyDb->getCurrencies();
		$options['currencies'] = $currencies;

		//Get price rule actions
		$priceruleactionDb = new Application_Model_DbTable_Priceruleaction();
		$priceruleactions = $priceruleactionDb->getPriceruleactions();
		$options['priceruleactions'] = $priceruleactions;

		//Get tags
		$tagDb = new Application_Model_DbTable_Tag();
		$tags = $tagDb->getTags('contacts', 'contact');
		$options['tags'] = $tags;

		//Set form options
		$MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);
		if(isset($form->paymentmethod) && isset($options['paymentmethods'])) $form->paymentmethod->addMultiOptions($options['paymentmethods']);
		if(isset($form->currency) && isset($options['currencies'])) $form->currency->addMultiOptions($options['currencies']);
		if(isset($form->priceruleaction) && isset($options['priceruleactions'])) $form->priceruleaction->addMultiOptions($options['priceruleactions']);
		if(isset($form->tagid) && isset($options['tags'])) $form->tagid->addMultiOptions($options['tags']);

		return $options;
	}
}
