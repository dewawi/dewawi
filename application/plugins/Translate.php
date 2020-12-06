<?php

class Application_Plugin_Translate extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		$params = $request->getParams();
		$module = $params['module'];
		$language = Zend_Registry::get('Zend_Locale');

		//Translate
		$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$language);
		Zend_Registry::set('Zend_Translate', $translate);
		//error_log($language);

		//Get view instance
		$view = Zend_Controller_Front::getInstance()
						->getParam('bootstrap')
						->getResource('view');

		if(Zend_Registry::isRegistered('User')) {
			$user = Zend_Registry::get('User');
			//if($user['admin']) {
				//Get languages
				$languageDb = new Application_Model_DbTable_Language();
				$languages = $languageDb->getLanguages();

				//Language switcher
                if(count($languages) > 1) {
				    $form = new Application_Form_Language();
				    $form->language->addMultiOptions($languages);
				    $authNamespace = new Zend_Session_Namespace('Zend_Auth');
				    if(isset($authNamespace->storage->language) && $authNamespace->storage->language) {
					    $form->language->setValue($authNamespace->storage->language);
				    }
				    $view->languageSwitcher = $form->getElement('language');
				}
			//}

			$view->language = $language;

			//Translate module specific
			/*if($module == "default") {
				$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$language);
			} else {
				$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$language.'/default');
				$translateModule = new Zend_Translate('array', BASE_PATH.'/languages/'.$language.'/'.$module);
				$translate->addTranslation($translateModule);
			}
			Zend_Registry::set('Zend_Translate', $translate);
			print_r($translate);*/
		}
	}
}
