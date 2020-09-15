<?php

class Sales_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
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

		//Get payment methods
		$paymentmethodDb = new Application_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodDb->getPaymentmethods();
		$options['paymentmethods'] = $paymentmethods;

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();
		$options['shippingmethods'] = $shippingmethods;

		//Get currencies
		$currencyDb = new Application_Model_DbTable_Currency();
		$currencies = $currencyDb->getCurrencies();
		$options['currencies'] = $currencies;

		//Get templates
		$templateDb = new Application_Model_DbTable_Template();
		$templates = $templateDb->getTemplates();
		$options['templates'] = $templates;

		//Get languages
		$languageDb = new Application_Model_DbTable_Language();
		$languages = $languageDb->getLanguages();
		$options['languages'] = $languages;

		//Set form options
		$MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);
		if(isset($form->paymentmethod) && isset($options['paymentmethods'])) $form->paymentmethod->addMultiOptions($options['paymentmethods']);
		if(isset($form->shippingmethod) && isset($options['shippingmethods'])) $form->shippingmethod->addMultiOptions($options['shippingmethods']);
		if(isset($form->currency) && isset($options['currencies'])) $form->currency->addMultiOptions($options['currencies']);
		if(isset($form->templateid) && isset($options['templates'])) $form->templateid->addMultiOptions($options['templates']);
		if(isset($form->language) && isset($options['languages'])) $form->language->addMultiOptions($options['languages']);

		return $options;
	}
}
