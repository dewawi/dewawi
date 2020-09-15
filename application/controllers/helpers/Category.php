<?php

class Application_Controller_Action_Helper_Category extends Zend_Controller_Action_Helper_Abstract
{
	public function isParent($id, $parentid, $categories) {
		if(isset($categories[$parentid]['childs'])) {
			return $this->checkChilds($id, $parentid, $categories);
		}
		return false;
	}

	public function checkChilds($id, $parentid, $categories) {
		if(isset($categories[$parentid]['childs'])) {
			if(array_search($id, $categories[$parentid]['childs']) !== false) {
				return true;
			} else {
				foreach($categories[$parentid]['childs'] as $child) {
					return $this->checkChilds($id, $child, $categories);
				}
			}
		} else {
			return false;
		}
	}
}
