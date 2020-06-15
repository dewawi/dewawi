<?php

class Items_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form, $clientid) {

		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('item');
		$options['categories'] = $categories;

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

		//Set form options
        $MenuStructure = Zend_Controller_Action_HelperBroker::getStaticHelper('MenuStructure');
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($MenuStructure->getMenuStructure($options['categories']));
		if(isset($form->manufacturerid) && isset($options['manufacturers'])) $form->manufacturerid->addMultiOptions($options['manufacturers']);
		if(isset($form->uomid) && isset($options['uoms'])) $form->uomid->addMultiOptions($options['uoms']);
		if(isset($form->taxid) && isset($options['taxrates'])) $form->taxid->addMultiOptions($options['taxrates']);

		return $options;
	}
}
