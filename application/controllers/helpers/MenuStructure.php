<?php

class Application_Controller_Action_Helper_MenuStructure extends Zend_Controller_Action_Helper_Abstract
{
	public function getMenuStructure($options, $id = 0, $level = 0)
	{
		$i = 1;
		$optionsStructure = array();
		$count = count($options);
		foreach($options as $option) {
			if(isset($option['parentid']) && ($option['parentid'] == $id)) {
				$optionsStructure[$option['id']] = str_repeat(' -- ', $level).$option['title'];
				if(isset($option['childs']) && !empty($option['childs'])) {
					$childOptions = $this->getMenuStructure($options, $option['id'], $level+1);
					foreach($childOptions as $childId =>$childOption) $optionsStructure[$childId] = $childOption;
				}
				++$i;
			}
		}
		if(($count > 0) && ($level == 0)) {
			$request  = $this->getRequest();
			$module = $request->getParam('module', null);
			$action = $request->getParam('action', null);
			if(($module != 'admin') && (($action == 'index') || ($action == 'select'))) $optionsStructure[0] = 'CATEGORIES_NOT_CATEGORISED';
		}
		return $optionsStructure;
	}
}
