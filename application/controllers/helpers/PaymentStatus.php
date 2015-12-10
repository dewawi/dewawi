<?php

class Application_Controller_Action_Helper_PaymentStatus extends Zend_Controller_Action_Helper_Abstract
{
	public function getPaymentStatus()
	{
		$paymentstatus = array();
		$paymentstatus['waitingForPayment'] = 'PROCESSES_WAITING_FOR_PAYMENT';
		$paymentstatus['prepaymentReceived'] = 'PROCESSES_PREPAYMENT_RECEIVED';
		$paymentstatus['paymentCompleted'] = 'PROCESSES_PAYMENT_COMPLETED';
		return $paymentstatus;
	}
}
