<?php

class Items_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form, $clientid) {

		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categoriesObject = $categoriesDb->fetchAll(
					$categoriesDb->select()
						->where('type = ?', 'item')
						->where('clientid = ?', $clientid)
						->order('ordering')
		);
		$categories = array();
		foreach($categoriesObject as $category) {
			if(!$category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['parent'] = $category->parentid;
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
				$categories[$category->id]['parent'] = $category->parentid;
				if($category->parentid) {
					if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
					if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
					array_push($categories[$category->parentid]['childs'], $category->id);
				}
			}
		}
		$options['categories'] = $categories;

		//Get manufacturers
		$manufacturerDb = new Application_Model_DbTable_Manufacturer();
		$manufacturersObject = $manufacturerDb->fetchAll();
		$manufacturers = array();
		foreach($manufacturersObject as $manufacturer) {
			$manufacturers[$manufacturer->id] = $manufacturer->name;
		}
		$options['manufacturers'] = $manufacturers;

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uomsObject = $uomDb->fetchAll();
		$uoms = array();
		foreach($uomsObject as $uom) {
			$uoms[$uom->id] = $uom->title;
		}
		$options['uoms'] = $uoms;

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxratesObject = $taxrateDb->fetchAll();
		$taxrates = array();
		$locale = Zend_Registry::get('Zend_Locale');
		foreach($taxratesObject as $taxrate) {
			$taxrates[$taxrate->id] = Zend_Locale_Format::toNumber($taxrate->rate,array('precision' => 1,'locale' => $locale)).' %';
		}
		$options['taxrates'] = $uoms;

		//Set form options
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($this->getMenuStructure($options['categories']));
		if(isset($form->manufacturerid) && isset($options['manufacturers'])) $form->manufacturerid->addMultiOptions($options['manufacturers']);
		if(isset($form->uomid) && isset($options['uoms'])) $form->uomid->addMultiOptions($this->getMenuStructure($options['uoms']));
		if(isset($form->taxid) && isset($options['taxrates'])) $form->taxid->addMultiOptions($this->getMenuStructure($options['taxrates']));

		return $options;
	}

	public function getMenuStructure($options, $id = 0, $level = 0)
	{
		$i = 1;
		$optionsStructure = array();
		$count = count($options);
		foreach($options as $option) {
			if($option['parent'] == $id) {
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
