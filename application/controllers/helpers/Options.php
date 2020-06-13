<?php

class Application_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form, $clientid) {

		$options = array();

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');
		$options['categories'] = $categories;

		//Get states
		$stateDb = new Application_Model_DbTable_State();
		$states = $stateDb->getStates();
		$options['states'] = $states;

		//Get templates
		$templateDb = new Application_Model_DbTable_Template();
		$templates = $templateDb->getTemplates();
		$options['templates'] = $templates;

		//Get languages
		$languageDb = new Application_Model_DbTable_Language();
		$languages = $languageDb->getLanguages();
		$options['languages'] = $languages;

		//Set form options
		if(isset($form->catid) && isset($options['categories'])) $form->catid->addMultiOptions($this->getMenuStructure($options['categories']));
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
