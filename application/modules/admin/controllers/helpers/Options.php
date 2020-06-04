<?php

class Admin_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form) {

		$options = array();

		//Get clients
		$clientsDb = new Admin_Model_DbTable_Client();
		$clientsObject = $clientsDb->fetchAll();
		$clients = array();
		foreach($clientsObject as $client) {
			$clients[$client->id] = $client->company;
		}
		$options['clients'] = $clients;

		//Get languages
		$languagesDb = new Admin_Model_DbTable_Language();
		$languagesObject = $languagesDb->fetchAll();
		$languages = array();
		foreach($languagesObject as $language) {
			$languages[$language->code] = $language->name;
		}
		$options['languages'] = $languages;

		//Set form options
		if(isset($form->clientid) && isset($options['clients'])) $form->language->addMultiOptions($options['clients']);
		if(isset($form->language) && isset($options['languages'])) $form->language->addMultiOptions($options['languages']);

		return $options;
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
