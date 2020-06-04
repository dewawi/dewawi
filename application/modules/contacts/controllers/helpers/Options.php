<?php

class Contacts_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form, $clientid)
	{
		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categoriesObject = $categoriesDb->fetchAll(
					$categoriesDb->select()
						->where('type = ?', 'contact')
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

		//Get countries
		$countryDb = new Application_Model_DbTable_Country();
		$countriesObject = $countryDb->fetchAll(
					$countryDb->select()
						->where('clientid = ?', $clientid)
		);
		$countries = array();
		foreach($countriesObject as $country) {
			$countries[$country->code] = $country->name;
		}
		asort($countries);
		$options['countries'] = $countries;

		//Get payment methods
		$paymentmethodDb = new Application_Model_DbTable_Paymentmethod();
		$paymentmethodObject = $paymentmethodDb->fetchAll(
			$paymentmethodDb->select()
				->where('clientid = ?', $clientid)
		);
		$paymentmethods = array();
		foreach($paymentmethodObject as $paymentmethod) {
			$paymentmethods[$paymentmethod->title] = $paymentmethod->title;
		}
		$options['paymentmethods'] = $paymentmethods;


		//Set form options
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($this->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);
		if(isset($form->paymentmethod) && isset($options['paymentmethods'])) $form->paymentmethod->addMultiOptions($options['paymentmethods']);

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
