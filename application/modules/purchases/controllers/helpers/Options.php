<?php

class Purchases_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form, $clientid) {

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

		//Get states
		$options['states'] = array(
			'100' => 'STATES_CREATED',
			'101' => 'STATES_IN_PROCESS',
			'102' => 'STATES_PLEASE_CHECK',
			'103' => 'STATES_PLEASE_DELETE',
			'104' => 'STATES_RELEASED',
			'105' => 'STATES_COMPLETED',
			'106' => 'STATES_CANCELLED'
		);

		//Get date ranges
		$options['daterange'] = array(
			'0' => 'TOOLBAR_ALL',
			'today' => 'TOOLBAR_TODAY',
			'yesterday' => 'TOOLBAR_YESTERDAY',
			'last7days' => 'TOOLBAR_LAST_7_DAYS',
			'last14days' => 'TOOLBAR_LAST_14_DAYS',
			'last30days' => 'TOOLBAR_LAST_30_DAYS',
			'thisMonth' => 'TOOLBAR_THIS_MONTH',
			'lastMonth' => 'TOOLBAR_LAST_MONTH',
			'thisYear' => 'TOOLBAR_THIS_YEAR',
			'lastYear' => 'TOOLBAR_LAST_YEAR',
			'custom' => 'TOOLBAR_CUSTOM'
		);

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

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethodObject = $shippingmethodDb->fetchAll(
			$shippingmethodDb->select()
				->where('clientid = ?', $clientid)
		);
		$shippingmethods = array();
		foreach($shippingmethodObject as $shippingmethod) {
			$shippingmethods[$shippingmethod->title] = $shippingmethod->title;
		}
		$options['shippingmethods'] = $shippingmethods;

		//Get templates
		$templateDb = new Application_Model_DbTable_Template();
		$templateObject = $templateDb->fetchAll(
			$templateDb->select()
				->where('clientid = ?', $clientid)
		);
		$templates = array();
		foreach($templateObject as $template) {
			$templates[$template->id] = $template->description;
		}
		$options['templates'] = $templates;

		//Get languages
		$languages = array(
			'de_DE' => 'LANGUAGES_DE_DE',
			'en_US' => 'LANGUAGES_EN_US'
		);
		$options['languages'] = $languages;

		//Set form options
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($this->getMenuStructure($options['categories']));
		if(isset($form->country) && isset($options['countries'])) $form->country->addMultiOptions($options['countries']);
		if(isset($form->paymentmethod) && isset($options['paymentmethods'])) $form->paymentmethod->addMultiOptions($options['paymentmethods']);
		if(isset($form->shippingmethod) && isset($options['shippingmethods'])) $form->shippingmethod->addMultiOptions($options['shippingmethods']);
		if(isset($form->templateid) && isset($options['templates'])) $form->templateid->addMultiOptions($options['templates']);
		if(isset($form->language) && isset($options['languages'])) $form->language->addMultiOptions($options['languages']);

		return $options;
	}

	public function getMenuStructure($options, $id = 0, $level = 0)
	{
		$i = 1;
		$optionsStructure = array();
		$count = count($options);
		foreach($options as $option) {
			if(isset($option['parent']) && ($option['parent'] == $id)) {
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
