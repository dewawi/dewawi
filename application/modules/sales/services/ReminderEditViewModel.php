<?php

class Sales_Service_ReminderEditViewModel
{
	public function build(int $reminderId, array $user, array $reminderRow): array
	{
		//Get contact
		if($reminderRow['contactid']) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContactWithID($reminderRow['contactid']);

			//Phone
			$phoneDb = new Contacts_Model_DbTable_Phone();
			$contact['phone'] = $phoneDb->getByParentId($contact['id'], 'contacts', 'contact');

			//Email
			$emailDb = new Contacts_Model_DbTable_Email();
			$contact['email'] = $emailDb->getByParentId($contact['id'], 'contacts', 'contact');

			//Internet
			$internetDb = new Contacts_Model_DbTable_Internet();
			$contact['internet'] = $internetDb->getByParentId($contact['id'], 'contacts', 'contact');
		}

		/*if($reminder['contactid']) {
			$data['contactinfo'] = $contact['info'];
			$form->contactinfo->setAttrib('data-id', $contact['id']);
			$form->contactinfo->setAttrib('data-controller', 'contact');
			$form->contactinfo->setAttrib('data-module', 'contacts');
			$form->contactinfo->setAttrib('readonly', null);
		}*/

		return [
			'contact' => $contact,
		];
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
