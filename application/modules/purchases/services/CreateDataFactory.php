<?php

class Purchases_Service_CreateDataFactory
{
	public function build(string $controller, int $contactId = 0): array
	{
		$data = $this->getDefaults($controller);

		if($contactId > 0) {
			$contactDataFactory = new Contacts_Service_ContactDataFactory();
			$data = array_merge($data, $contactDataFactory->getContactData($contactId));
		}

		return $data;
	}

	protected function getDefaults(string $controller): array
	{
		$currencies = new Application_Model_DbTable_Currency();
		$languages = new Application_Model_DbTable_Language();
		$templates = new Application_Model_DbTable_Template();

		$currency = $currencies->getPrimaryCurrency();
		$language = $languages->getPrimaryLanguage();
		$template = $templates->getPrimaryTemplate();

		return [
			'title' => $this->getDefaultTitle($controller),
			'currency' => $currency['code'],
			'templateid' => $template['id'],
			'language' => $language['code'],
			'state' => 100,
		];
	}

	protected function getDefaultTitle(string $controller): string
	{
		$map = [
			'quoterequest' => $this->translate('QUOTE_REQUESTS_NEW_QUOTE_REQUEST'),
			'purchaseorder' => $this->translate('PURCHASE_ORDERS_NEW_PURCHASE_ORDER'),
		];

		return $map[$controller] ?? strtoupper($controller) . ' NEW';
	}

	protected function getTranslator(): ?DEEC_Translate
	{
		if (Zend_Registry::isRegistered('DEEC_Translate')) {
			$translator = Zend_Registry::get('DEEC_Translate');
			if ($translator instanceof DEEC_Translate) {
				return $translator;
			}
		}

		return null;
	}

	protected function translate(string $key): string
	{
		$translator = $this->getTranslator();
		if ($translator) {
			return $translator->t($key);
		}

		return $key;
	}
}
