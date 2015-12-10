<?php

class Application_Controller_Action_Helper_DeliveryStatus extends Zend_Controller_Action_Helper_Abstract
{
	public function getDeliveryStatus()
	{
		$deliverystatus = array();
		$deliverystatus['deliveryIsWaiting'] = 'PROCESSES_DELIVERY_IS_WAITING';
		$deliverystatus['partialDelivered'] = 'PROCESSES_PARTIAL_DElIVERED';
		$deliverystatus['deliveryCompleted'] = 'PROCESSES_DELIVERY_COMPLETED';
		return $deliverystatus;
	}
}
