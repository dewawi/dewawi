<?php

class Statistics_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
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

		//Set form options
		$MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);

		return $options;
	}
}
