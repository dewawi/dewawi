<?php

class Campaigns_Service_CampaignRecipientService
{
	public function getRecipients(array $params, array $options, int $contactCatId, bool $contactSubcat): array
	{
		$params['_export'] = true;
		$params['limit'] = 0;
		$params['catid'] = $contactCatId;

		$config = Contacts_Model_Entity_Contact::listConfig();
		$config['filters']['catid']['subcategories'] = $contactSubcat;

		$query = new DEEC_List_Query();

		list($contacts, $records) = $query->fetch(
			$params,
			$options,
			$config
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

	public function getRecipientStatus($contacts, int $campaignId): array
	{
		$contactIds = [];

		foreach($contacts as $contact) {
			$contactIds[] = is_array($contact) ? (int)$contact['id'] : (int)$contact->id;
		}

		$contactIds = array_values(array_unique(array_filter($contactIds)));

		$empty = [
			'total' => 0,
			'eligible' => 0,
			'suppressed' => 0,
			'open' => 0,
			'pending' => 0,
			'sent' => 0,
			'failed' => 0,
		];

		if(!$contactIds) return $empty;

		$emailDb = new Contacts_Model_DbTable_Email();
		$adapter = $emailDb->getAdapter();
		$clientId = $emailDb->getClientId();
		$campaignId = (int)$campaignId;
		$contactIds = implode(',', array_map('intval', $contactIds));

		$sql = '
			SELECT
				COUNT(*) AS total,
				SUM(r.suppressed = 0) AS eligible,
				SUM(r.suppressed = 1) AS suppressed,
				SUM(
					r.suppressed = 0
					AND COALESCE(h.sent, 0) = 0
					AND COALESCE(h.pending, 0) = 0
					AND COALESCE(h.failed, 0) < 3
				) AS open,
				SUM(
					r.suppressed = 0
					AND COALESCE(h.sent, 0) = 0
					AND COALESCE(h.pending, 0) > 0
				) AS pending,
				SUM(
					r.suppressed = 0
					AND COALESCE(h.sent, 0) > 0
				) AS sent,
				SUM(
					r.suppressed = 0
					AND COALESCE(h.sent, 0) = 0
					AND COALESCE(h.pending, 0) = 0
					AND COALESCE(h.failed, 0) >= 3
				) AS failed
			FROM (
				SELECT email, MAX(suppressed) AS suppressed
				FROM (
					SELECT
						LOWER(TRIM(e.email)) AS email,
						EXISTS (
							SELECT 1
							FROM email AS se
							WHERE se.clientid = e.clientid
								AND se.deleted = 0
								AND se.suppressed = 1
								AND LOWER(TRIM(se.email)) = LOWER(TRIM(e.email))
						) AS suppressed
					FROM email AS e
					WHERE e.clientid = '.$clientId.'
						AND e.deleted = 0
						AND e.module = "contacts"
						AND e.controller = "contact"
						AND e.parentid IN ('.$contactIds.')
						AND e.email IS NOT NULL
						AND TRIM(e.email) != ""

					UNION ALL

					SELECT
						LOWER(TRIM(e.email)) AS email,
						EXISTS (
							SELECT 1
							FROM email AS se
							WHERE se.clientid = e.clientid
								AND se.deleted = 0
								AND se.suppressed = 1
								AND LOWER(TRIM(se.email)) = LOWER(TRIM(e.email))
						) AS suppressed
					FROM contactperson AS cp
					INNER JOIN email AS e
						ON e.parentid = cp.id
						AND e.module = "contacts"
						AND e.controller = "contactperson"
						AND e.clientid = cp.clientid
						AND e.deleted = 0
					WHERE cp.clientid = '.$clientId.'
						AND cp.deleted = 0
						AND cp.parentid IN ('.$contactIds.')
						AND e.email IS NOT NULL
						AND TRIM(e.email) != ""
				) AS addresses
				GROUP BY email
			) AS r
			LEFT JOIN (
				SELECT
					LOWER(TRIM(recipient)) AS email,
					SUM(response = "sent") AS sent,
					SUM(response = "pending" AND messagesent >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS pending,
					SUM(response != "sent" AND response != "pending") AS failed
				FROM emailmessage
				WHERE parentid = '.$campaignId.'
					AND module = "campaigns"
					AND controller = "campaign"
					AND clientid = '.$clientId.'
					AND deleted = 0
				GROUP BY LOWER(TRIM(recipient))
			) AS h ON h.email = r.email
		';

		$row = $adapter->fetchRow($sql);

		if(!$row) return $empty;

		foreach($empty as $key => $value) {
			$empty[$key] = (int)($row[$key] ?? 0);
		}

		return $empty;
	}
}
