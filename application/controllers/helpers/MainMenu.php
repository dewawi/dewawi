<?php

class Application_Controller_Action_Helper_MainMenu extends Zend_Controller_Action_Helper_Abstract
{
	public function getMainMenu() {
		//$modulesDb = new Application_Model_DbTable_Module();
		//$modules = $modulesDb->getModules();
		$mainmenu = array();
		/*foreach($modules as $module) {
			if($module->active && $module->menu) {
				$data = Zend_Json::decode($module->menu);
				foreach($data as $key => $value) {
					if(isset($mainmenu[$key]['childs'])) {
						foreach($value['childs'] as $ordering => $child) {
							$mainmenu[$key]['childs'][$ordering] = $child;
						}
					} else {
						$mainmenu[$key] = $value;
					}
				}
			}
		}
		foreach($mainmenu as $key => $value) {
			if(isset($value['childs'])) {
				ksort($mainmenu[$key]['childs']);
			}
		}*/
		return $mainmenu;
	}
}
