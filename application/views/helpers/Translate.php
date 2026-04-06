<?php

class Zend_View_Helper_Translate extends Zend_View_Helper_Abstract
{
	public function translate(string $key, array $args = []): string
	{
		if (!Zend_Registry::isRegistered('DEEC_Translate')) {
			return $key;
		}

		$translator = Zend_Registry::get('DEEC_Translate');
		if (!$translator instanceof DEEC_Translate) {
			return $key;
		}

		return $translator->t($key, $args);
	}
}
