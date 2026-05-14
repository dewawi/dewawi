<?php

class Campaigns_Service_CampaignRecipientService
{
	public function getRecipients(array $params, array $options, int $contactCatId): array
	{
		$params['_export'] = true;
		$params['limit'] = 0;
		$params['catid'] = $contactCatId;

		$query = new DEEC_List_Query();

		list($contacts, $records) = $query->fetch(
			$params,
			$options,
			Contacts_Model_Entity_Contact::listConfig()
		);

		return [
			'contacts' => $contacts,
			'records' => $records,
			'contactPersonsByCompany' => $this->getContactPersonsByCompany($contacts),
		];
	}

	protected function getContactPersonsByCompany($contacts): array
	{
		$out = [];

		$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
		$emailDb = new Contacts_Model_DbTable_Email();

		foreach ($contacts as $contact) {
			$contactId = is_array($contact) ? (int)$contact['id'] : (int)$contact->id;

			$persons = $contactpersonDb->getContactpersons(
				$contactId,
				'contacts',
				'contact'
			);

			foreach ($persons as &$person) {
				$personId = is_array($person) ? (int)$person['id'] : (int)$person->id;

				$emailRows = $emailDb->getEmails(
					$personId,
					'contacts',
					'contactperson'
				);

				$emails = [];

				foreach ((array)$emailRows as $emailRow) {
					$emails[] = is_array($emailRow)
						? ($emailRow['email'] ?? '')
						: ($emailRow->email ?? '');
				}

				$emails = array_values(array_filter(array_map('trim', $emails)));

				$person['email_list'] = implode(',', $emails);

				$salutation = trim((string)($person['salutation'] ?? ''));
				$name2 = trim((string)($person['name2'] ?? ''));

				$person['display_name'] = trim($salutation . ' ' . $name2);
			}
			unset($person);

			$out[$contactId] = $persons;
		}

		return $out;
	}
}
