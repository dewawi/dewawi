<?php

class Purchases_Service_CreateDataFactory
{
	public function build(string $controller, int $contactId = 0): array
	{
		$data = $this->getDefaults($controller);

		if ($contactId > 0) {
			$data = array_merge($data, $this->getContactData($contactId));
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

	protected function getContactData(int $contactId): array
	{
		$data = [];

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($contactId);

		if (!$contact) {
			return $data;
		}

		$data['contactid'] = $contact['contactid'];
		$data['billingname1'] = $contact['name1'];
		$data['billingname2'] = $contact['name2'];
		$data['billingdepartment'] = $contact['department'];

		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getAddress($contact['id']);

		if (count($addresses)) {
			$data['billingstreet'] = $addresses[0]['street'];
			$data['billingpostcode'] = $addresses[0]['postcode'];
			$data['billingcity'] = $addresses[0]['city'];
			$data['billingcountry'] = $addresses[0]['country'];
		}

		if (!empty($contact['vatin'])) {
			$data['vatin'] = $contact['vatin'];
		}

		if (!empty($contact['currency'])) {
			$data['currency'] = $contact['currency'];
		}

		if (!empty($contact['taxfree'])) {
			$data['taxfree'] = $contact['taxfree'];
		}

		return $data;
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
