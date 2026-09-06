<?php

class Zend_View_Helper_PageByType extends Zend_View_Helper_Abstract
{
	public function PageByType($type)
	{
		$shop = Zend_Registry::get('Shop');
		$pageDb = new Shops_Model_DbTable_Page();

		return $pageDb->getPageByType($type, (int)$shop['id']);
	}
}
