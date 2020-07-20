<?php

class Application_Controller_Action_Helper_Currency extends Zend_Controller_Action_Helper_Abstract
{
	public function direct($fromcode, $tocode, $price, $helper) {
		return $price / 1.1428;
	}

	public function convert($fromcode, $tocode, $price, $helper) {
		return $price / 1.1428;
	}

	public function getCurrency($currency = null, $symbol = null) {
        $instance = Zend_Registry::get('Zend_Currency');
        if($currency !== null) $this->setCurrency($instance, $currency, $symbol);
		return $instance;
	}

	public function setCurrency($instance, $currency, $symbol = null) {
        if($symbol == 'USE_SYMBOL') {
		    $instance->setFormat(array(
                'currency' => $currency,
                'symbol' => $instance->getSymbol($currency, $instance->getLocale()),
                'display' => Zend_Currency::USE_SYMBOL
            ));
        } else {
		    $instance->setFormat(array(
                'currency' => $currency,
                'symbol' => $instance->getSymbol($currency, $instance->getLocale()),
                'display' => Zend_Currency::NO_SYMBOL
            ));
        }
		return $instance;
	}
}
