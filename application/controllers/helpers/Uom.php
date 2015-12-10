<?php

class Application_Controller_Action_Helper_Uom extends Zend_Controller_Action_Helper_Abstract
{
	public function getUom($id) {
		$uomDb = new Application_Model_DbTable_Uom();
		$uom = $uomDb->getUom($id);
		return $uom['title'];
	}

	public function getUoms() {
		$uomDb = new Application_Model_DbTable_Uom();
		$uomsObject = $uomDb->fetchAll();
		$uoms = array();
		foreach($uomsObject as $uom) {
			$uoms[$uom->id] = $uom->title;
		}
		return $uoms;
	}
}
