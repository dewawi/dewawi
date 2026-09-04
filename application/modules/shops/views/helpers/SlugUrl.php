<?php

class Zend_View_Helper_SlugUrl extends Zend_View_Helper_Abstract
{
	protected $_slugDb = null;

	public function SlugUrl($controller, $entityid)
	{
		if (!$this->_slugDb) {
			$this->_slugDb = new Shops_Model_DbTable_Slug();
		}

		$path = $this->_slugDb->getPath($controller, $entityid);

		if (!$path) {
			return null;
		}

		return $this->view->baseUrl('/' . $path);
	}
}
