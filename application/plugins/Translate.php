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
