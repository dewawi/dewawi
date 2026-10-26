<?php

class Purchases_Service_ReadonlyFormFactory
{
	public function build(string $formClass, array $document, $locale): DEEC_Form
	{
		$form = new $formClass();

		$optionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		$optionsHelper->applyFormOptions($form);
		$this->addContactpersonOptions($form, $document);

		$displayValues = DEEC_Display::rowToFormValues($form, $document, $locale);
		$form->setValues($displayValues);
		$form->setMode('readonly');

		return $form;
	}

	private function addContactpersonOptions(DEEC_Form $form, array $document): void
	{
		if (!$form->getElement('contactpersonid') || empty($document['contactid'])) return;

		$client = Zend_Registry::get('Client');
		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$client['id'];
		if ($contactClientId <= 0) return;

		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId);
		$contact = $contactDb->getContactWithID((int)$document['contactid']);
		if (!$contact) return;

		$contactpersonDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($contactClientId);
		$form->addOptions('contactpersonid', $contactpersonDb->getSelectOptionsForContact((int)$contact['id']));
	}
}
