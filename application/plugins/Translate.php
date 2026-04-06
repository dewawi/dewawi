<?php

class Application_Plugin_Translate extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request)
	{
		if (!Zend_Registry::isRegistered('DEEC_Translate')) {
			return;
		}

		$tr = Zend_Registry::get('DEEC_Translate');
		if (!$tr instanceof DEEC_Translate) {
			return;
		}

		$localeCode = trim((string)$tr->getLocale());
		if ($localeCode === '') {
			return;
		}

		$base = BASE_PATH . '/languages/' . $localeCode;

		// Load common.php from every module directory
		if (is_dir($base)) {
			$dirs = scandir($base);

			foreach ($dirs as $dirName) {
				if ($dirName === '.' || $dirName === '..' || $dirName === 'default') {
					continue;
				}

				$moduleDir = $base . '/' . $dirName;
				if (!is_dir($moduleDir)) {
					continue;
				}

				$commonFile = $moduleDir . '/common.php';
				if (is_file($commonFile)) {
					$tr->load($dirName . '_common', $commonFile);
				}
			}
		}

		// Load active module files
		$module = trim((string)$request->getModuleName());
		if ($module !== '') {
			$dir = $base . '/' . $module;
			if (is_dir($dir)) {
				$tr->loadDir($module, $dir);
			}
		}

		$view = Zend_Controller_Front::getInstance()
			->getParam('bootstrap')
			->getResource('view');

		$view->language = Zend_Registry::get('Zend_Locale');

		if (!Zend_Registry::isRegistered('User')) {
			return;
		}

		$languageDb = new Application_Model_DbTable_Language();
		$languages = $languageDb->getLanguages();

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
