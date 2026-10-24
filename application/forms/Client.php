<?php

class Application_Form_Client extends Zend_Form
{
	public function init()
	{
		$this->setName('client');

		$clientid = new Zend_Form_Element_Select('clientid');
		$clientid->removeDecorator('label')
			->setRequired(true)
			->addValidator('Digits', true);

		$session = new Zend_Session_Namespace('ClientSwitch');
		if(!isset($session->token)) $session->token = bin2hex(random_bytes(32));
		$token = $session->token;

		$csrf = new Zend_Form_Element_Hidden('client_csrf');
		$csrf->removeDecorator('label')
			->setRequired(true)
			->setValue($token)
			->addValidator('Callback', true, ['callback' => static function($value) use ($token) {
				return is_string($value) && hash_equals($token, $value);
			}]);

		$this->addElements(array($clientid, $csrf));
	}
}
