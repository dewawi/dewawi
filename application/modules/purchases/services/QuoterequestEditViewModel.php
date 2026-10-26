<?php

class Purchases_Service_QuoterequestEditViewModel
{
	public function build(int $quoterequestId, array $user, array $quoterequestRow): array
	{
		$contact = [];
		$contactpersonOptions = [];

		if($quoterequestRow['contactid']) {
			$contactClientId = $this->getContactClientId();
			if ($contactClientId <= 0) return ['contact' => [], 'contactpersonOptions' => []];
			$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId);
			$contact = $contactDb->getContactWithID($quoterequestRow['contactid']) ?: [];

			if ($contact) {
				$phoneDb = (new Contacts_Model_DbTable_Phone())->setClientId($contactClientId);
				$contact['phone'] = $phoneDb->getByParentId($contact['id'], 'contacts', 'contact');

				$emailDb = (new Contacts_Model_DbTable_Email())->setClientId($contactClientId);
				$contact['email'] = $emailDb->getByParentId($contact['id'], 'contacts', 'contact');

				$internetDb = (new Contacts_Model_DbTable_Internet())->setClientId($contactClientId);
				$contact['internet'] = $internetDb->getByParentId($contact['id'], 'contacts', 'contact');

				$contactpersonDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($contactClientId);
				$contactpersonOptions = $contactpersonDb->getSelectOptionsForContact((int)$contact['id']);
			}
		}

		/*if($quoterequest['contactid']) {
			$data['contactinfo'] = $contact['info'];
			$form->contactinfo->setAttrib('data-id', $contact['id']);
			$form->contactinfo->setAttrib('data-controller', 'contact');
			$form->contactinfo->setAttrib('data-module', 'contacts');
			$form->contactinfo->setAttrib('readonly', null);
		}*/

		return [
			'contact' => $contact,
			'contactpersonOptions' => $contactpersonOptions,
		];
	}

	private function getContactClientId(): int
	{
		$client = Zend_Registry::get('Client');
		return !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$client['id'];
	}

	protected function getContactFiles(int $contactId): array
	{
		$files = [];
		$path = BASE_PATH . '/files/contacts/' . $contactId;

		if (file_exists($path) && is_dir($path)) {
			$files['contactSpecific'] = [];

			if ($handle = opendir($path)) {
				while (false !== ($entry = readdir($handle))) {
					if (substr($entry, 0, 1) !== '.') {
						$files['contactSpecific'][] = $entry;
					}
				}
				closedir($handle);
			}
		}

		return $files;
	}
}
