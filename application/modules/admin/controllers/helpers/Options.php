<?php

class Admin_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($form) {

		$options = array();

		//Get clients
		$clientDb = new Admin_Model_DbTable_Client();
		$data = $clientDb->getClients();
		$clients = array();
		foreach($data as $client) {
			$clients[$client->id] = $client->company;
		}
		$options['clients'] = $clients;

		//Get languages
		$languageDb = new Application_Model_DbTable_Language();
		$languages = $languageDb->getLanguages();
		$options['languages'] = $languages;

		//Set form options
		if(isset($form->clientid) && isset($options['clients'])) $form->clientid->addMultiOptions($options['clients']);
		if(isset($form->language) && isset($options['languages'])) $form->language->addMultiOptions($options['languages']);

		return $options;
	}
}
