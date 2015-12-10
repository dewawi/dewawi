<?php

class Application_Controller_Action_Helper_ShippingMethod extends Zend_Controller_Action_Helper_Abstract
{
	public function getShippingMethods($clientid)
	{
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethodObject = $shippingmethodDb->fetchAll(
			$shippingmethodDb->select()
				->where('clientid = ?', $clientid)
		);
		$shippingmethods = array();
		foreach($shippingmethodObject as $shippingmethod) {
			$shippingmethods[$shippingmethod->title] = $shippingmethod->title;
		}
		return $shippingmethods;
	}
}
