<?php

class Sales_Service_EditViewModel
{
	public function build(int $id, array $user, array $row): array
	{
		return [
			'contact' => $this->getContact($row),
		];
	}

	protected function getContact(array $row): array
	{
		if (empty($row['contactid'])) {
			return [];
		}

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$row['contactid']);

		if (!$contact) {
			return [];
		}

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$emailDb = new Contacts_Model_DbTable_Email();
		$internetDb = new Contacts_Model_DbTable_Internet();

		$contact['phone'] = $phoneDb->getByParentId((int)$contact['id'], 'contacts', 'contact');
		$contact['email'] = $emailDb->getByParentId((int)$contact['id'], 'contacts', 'contact');
		$contact['internet'] = $internetDb->getByParentId((int)$contact['id'], 'contacts', 'contact');

		return $contact;
	}
}
