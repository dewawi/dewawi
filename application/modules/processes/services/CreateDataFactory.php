<?php

class Processes_Service_CreateDataFactory
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

		$currency = $currencies->getPrimaryCurrency();

		return [
			'title' => $this->getDefaultTitle($controller),
			'currency' => $currency['code'],
			'state' => 100,
		];
	}

	protected function getDefaultTitle(string $controller): string
	{
		$map = [
			'process' => $this->translate('PROCESSES_NEW_PROCESS'),
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
