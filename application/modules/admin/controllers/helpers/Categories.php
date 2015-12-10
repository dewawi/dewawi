<?php

class Admin_Controller_Action_Helper_Categories extends Zend_Controller_Action_Helper_Abstract
{
	public function getCategories($form = null, $clientid, $type, $parentid = null) {

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		if($parentid !== null) {
			$categoriesObject = $categoriesDb->fetchAll(
				$categoriesDb->select()
					->where('type = ?', $type)
					->where('parentid = ?', $parentid)
					->where('clientid = ?', $clientid)
					->order('ordering')
			);
		} else {
			$categoriesObject = $categoriesDb->fetchAll(
				$categoriesDb->select()
					->where('type = ?', $type)
					->where('clientid = ?', $clientid)
					->order('ordering')
			);
		}
		$categories = array();
		foreach($categoriesObject as $category) {
			if(!$category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['parentid'] = $category->parentid;
				$categories[$category->id]['ordering'] = $category->ordering;
				if($category->parentid) {
					if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
					if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
					array_push($categories[$category->parentid]['childs'], $category->id);
				}
			}
		}
		foreach($categoriesObject as $category) {
			if($category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['parentid'] = $category->parentid;
				$categories[$category->id]['ordering'] = $category->ordering;
				if($category->parentid) {
					if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
					if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
					array_push($categories[$category->parentid]['childs'], $category->id);
				}
			}
		}

		//Set form options
		if(isset($form->parentid)) $form->parentid->addMultiOptions($this->getMenuStructure($categories));

		return $categories;
	}

	public function getMenuStructure($options, $id = 0, $level = 0)
	{
		$i = 1;
		$optionsStructure = array();
		$count = count($options);
		foreach($options as $option) {
			if($option['parentid'] == $id) {
				$optionsStructure[$option['id']] = str_repeat(' -- ', $level).$option['title'];
				if(isset($option['childs']) && !empty($option['childs'])) {
					$childOptions = $this->getMenuStructure($options, $option['id'], $level+1);
					foreach($childOptions as $childId =>$childOption) $optionsStructure[$childId] = $childOption;
				}
				++$i;
			}
		}
		return $optionsStructure;
	}
}
