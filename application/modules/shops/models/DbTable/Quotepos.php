<?php

class Shops_Model_DbTable_Quotepos extends DEEC_Model_DbTable_Position
{
	protected $_name = 'quotepos';

	public function init()
	{
		parent::init();

		if (!Zend_Registry::isRegistered('SiteContext')) {
			throw new RuntimeException('Site context is missing');
		}

		$siteContext = Zend_Registry::get('SiteContext');
		$this->setClientId($siteContext->getClientId());
	}
}
