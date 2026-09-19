<?php

class Zend_View_Helper_PageByType extends Zend_View_Helper_Abstract
{
	public function PageByType($type)
	{
		$pageDb = new Shops_Model_DbTable_Page();

		return $pageDb->getPageByType((string)$type);
	}
}
