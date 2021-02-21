<?php

class Items_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form) {

		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('item');
		$options['categories'] = $categories;

		//Get categories
		$contactCategories = $categoriesDb->getCategories('contact');
		$options['contactCategories'] = $contactCategories;

		//Get manufacturers
		$manufacturerDb = new Application_Model_DbTable_Manufacturer();
		$manufacturers = $manufacturerDb->getManufacturers();
		$options['manufacturers'] = $manufacturers;

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();
		$options['uoms'] = $uoms;

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();
		$options['taxrates'] = $taxrates;

		//Get currencies
		$currencyDb = new Application_Model_DbTable_Currency();
		$currencies = $currencyDb->getCurrencies();
		$options['currencies'] = $currencies;

		//Get delivery times
		$deliverytimeDb = new Application_Model_DbTable_Deliverytime();
		$deliverytimes = $deliverytimeDb->getDeliverytimes();
		$options['deliverytimes'] = $deliverytimes;

		//Get price rule actions
		$priceruleactionDb = new Application_Model_DbTable_Priceruleaction();
		$priceruleactions = $priceruleactionDb->getPriceruleactions();
		$options['priceruleactions'] = $priceruleactions;

		//Set form options
		$MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->manufacturerid) && isset($options['manufacturers'])) $form->manufacturerid->addMultiOptions($options['manufacturers']);
		if(isset($form->uomid) && isset($options['uoms'])) $form->uomid->addMultiOptions($options['uoms']);
		if(isset($form->taxid) && isset($options['taxrates'])) $form->taxid->addMultiOptions($options['taxrates']);
		if(isset($form->currency) && isset($options['currencies'])) $form->currency->addMultiOptions($options['currencies']);
		if(isset($form->deliverytime) && isset($options['deliverytimes'])) $form->deliverytime->addMultiOptions($options['deliverytimes']);
		if(isset($form->deliverytimeoos) && isset($options['deliverytimes'])) $form->deliverytimeoos->addMultiOptions($options['deliverytimes']);
		if(isset($form->action) && isset($options['priceruleactions'])) $form->action->addMultiOptions($options['priceruleactions']);
		if(isset($form->itemmanufacturer) && isset($options['manufacturers'])) $form->itemmanufacturer->addMultiOptions($options['manufacturers']);
		if(isset($form->itemcatid) && isset($options['categories'])) $form->itemcatid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->contactcatid) && isset($options['contactCategories'])) $form->contactcatid->addMultiOptions($MenuStructure->getMenuStructure($options['contactCategories']));

		return $options;
	}
}
