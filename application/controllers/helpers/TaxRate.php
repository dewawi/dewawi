<?php

class Application_Controller_Action_Helper_TaxRate extends Zend_Controller_Action_Helper_Abstract
{
	public function getTaxRate($id) {
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrate = $taxrateDb->getTaxrate($id);
		return $taxrate['rate'];
	}

	public function getTaxRates($locale = null) {
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxratesObject = $taxrateDb->fetchAll();
		$taxrates = array();
		if($locale) {
			foreach($taxratesObject as $taxrate) {
				$taxrates[$taxrate->rate] = Zend_Locale_Format::toNumber($taxrate->rate,array('precision' => 1,'locale' => $locale)).' %';
			}
		} else {
			foreach($taxratesObject as $taxrate) {
				$taxrates[$taxrate->rate] = $taxrate->rate;
			}
		}
		return $taxrates;
	}

	public function getTaxRateIDs($locale = null) {
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxratesObject = $taxrateDb->fetchAll();
		$taxrates = array();
		if($locale) {
			foreach($taxratesObject as $taxrate) {
				$taxrates[$taxrate->id] = Zend_Locale_Format::toNumber($taxrate->rate,array('precision' => 1,'locale' => $locale)).' %';
			}
		} else {
			foreach($taxratesObject as $taxrate) {
				$taxrates[$taxrate->id] = $taxrate->rate;
			}
		}
		return $taxrates;
	}
}
