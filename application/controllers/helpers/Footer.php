<?php

class Application_Controller_Action_Helper_Footer extends Zend_Controller_Action_Helper_Abstract
{
	public function getFooters($templateid, $clientid) {
		$footersDb = new Application_Model_DbTable_Footer();
		$footers = $footersDb->fetchAll(
			$footersDb->select()
				->where('templateid = ?', $templateid)
				->where('clientid = ?', $clientid)
				->order('column')
		);
		return $footers;
	}
}
