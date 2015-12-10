<?php

class Application_Controller_Action_Helper_Template extends Zend_Controller_Action_Helper_Abstract
{
	public function getTemplates($clientid) { 
		$templateDb = new Application_Model_DbTable_Template();
		$templateObject = $templateDb->fetchAll(
			$templateDb->select()
				->where('clientid = ?', $clientid)
		);
		$templates = array();
		foreach($templateObject as $template) {
			$templates[$template->id] = $template->description;
		}
		return $templates;
	}
}
