<?php

class Application_Plugin_Translate extends Zend_Controller_Plugin_Abstract
{
	public function preDispatch(Zend_Controller_Request_Abstract $request)
	{
		if (Zend_Registry::isRegistered('DEEC_Translate')) {
			$tr = Zend_Registry::get('DEEC_Translate');
			if ($tr instanceof DEEC_Translate) $tr->loadModule(trim((string)$request->getModuleName()));
		}
	}

	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if (!Zend_Registry::isRegistered('DEEC_Translate')) {
			return;
		}

		$tr = Zend_Registry::get('DEEC_Translate');
		if (!$tr instanceof DEEC_Translate) {
			return;
		}

		$module = trim((string)$request->getModuleName());
		if ($module === 'shops' && Zend_Registry::isRegistered('SiteContext')) {
			$tr->setLocale((string)Zend_Registry::get('SiteContext')->getLanguage());
			Zend_Registry::set('Zend_Locale', new Zend_Locale($tr->getLocale()));
			Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => $tr->getLocale(), 'precision' => 2]));
		}
		$tr->loadModule($module);

		$view = Zend_Controller_Front::getInstance()
			->getParam('bootstrap')
			->getResource('view');

		$view->language = Zend_Registry::get('Zend_Locale');

		if (!Zend_Registry::isRegistered('User')) {
			return;
		}

		$languageDb = new Application_Model_DbTable_Language();
		$languages = array_intersect_key($languageDb->getLanguages(), array_flip(DEEC_Translate::LOCALES));

		if (count($languages) > 1) {
			$form = new Application_Form_Language();
			$form->language->addMultiOptions($languages);

			$authNamespace = new Zend_Session_Namespace('Zend_Auth');
			if (isset($authNamespace->storage->language) && $authNamespace->storage->language) {
				$form->language->setValue($authNamespace->storage->language);
			}

			$view->languageSwitcher = $form->getElement('language');
		}
	}
}
