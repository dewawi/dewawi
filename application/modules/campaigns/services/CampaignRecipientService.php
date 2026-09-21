<?php

class Campaigns_Service_CampaignRecipientService
{
	public function getRecipients(int $campaignId, int $page, int $limit, int $contactCatId, bool $contactSubcat): array
	{
		$page = max(1, $page);
		$limit = max(10, min(100, $limit));

		$categories = $this->getCategories();

		$params = [
			'page' => $page,
			'limit' => $limit,
			'catid' => $contactCatId > 0 ? $contactCatId : 'all',
			'keyword' => '',
			'country' => '0',
			'tagid' => 0,
			'order' => 'id',
			'sort' => 'ASC',
		];

		$config = Contacts_Model_Entity_Contact::listConfig();
		$config['pinned'] = false;
		$config['filters']['catid']['subcategories'] = $contactSubcat;

		$query = new DEEC_List_Query();

		list($contacts, $records) = $query->fetch(
			$params,
			['catid' => $categories],
			$config
		);

		return [
			'contacts' => $contacts,
			'records' => $records,
			'contactPersonsByCompany' => $this->getContactPersonsByCompany($contacts),
			'emailmessages' => $this->getEmailMessagesByContact($contacts, $campaignId),
		];
	}

	public function getErrors(int $campaignId): array
	{
		$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
		$adapter = $emailmessageDb->getAdapter();
		$clientId = $emailmessageDb->getClientId();

		$select = $adapter->select()
			->from(['em' => 'emailmessage'], [
				'id',
				'contactid',
				'recipient',
				'messagesent',
				'messagesentby',
				'response',
			])
			->joinLeft(
				['c' => 'contact'],
				'c.id = em.contactid AND c.clientid = em.clientid AND c.deleted = 0',
				[
					'contactnumber' => 'contactid',
					'contactname' => 'name1',
				]
			)
			->where('em.parentid = ?', $campaignId)
			->where('em.module = ?', 'campaigns')
			->where('em.controller = ?', 'campaign')
			->where('em.clientid = ?', $clientId)
			->where('em.deleted = ?', 0)
			->where('em.response IS NOT NULL')
			->where('em.response != ?', '')
			->where('em.response != ?', 'sent')
			->where('em.response != ?', 'pending')
			->order('em.id DESC');

		return $adapter->fetchAll($select);
	}

	protected function getContactPersonsByCompany($contacts): array
	{
		$out = [];

		$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
		$emailDb = new Contacts_Model_DbTable_Email();

		foreach($contacts as $contact) {
			$contactId = is_array($contact) ? (int)$contact['id'] : (int)$contact->id;

			$persons = $contactpersonDb->getByParentId(
				$contactId,
				'contacts',
				'contact'
			);

			foreach($persons as &$person) {
				$emailRows = $emailDb->getByParentId(
					(int)$person['id'],
					'contacts',
					'contactperson'
				);

				$emails = [];

				foreach($emailRows as $emailRow) {
					if(!empty($emailRow['email'])) {
						$emails[] = trim($emailRow['email']);
					}
				}

				$person['email_list'] = implode(',', array_values(array_unique($emails)));

				$salutation = trim((string)($person['salutation'] ?? ''));
				$name2 = trim((string)($person['name2'] ?? ''));

				$person['display_name'] = trim($salutation.' '.$name2);
			}
			unset($person);

			$out[$contactId] = $persons;
		}

		return $out;
	}

	protected function getCategories(): array
	{
		$categoryDb = new Application_Model_DbTable_Category();
		$categories = $categoryDb->getCategories('contact');

		foreach($categories as &$category) {
			$category['childs'] = [];
		}
		unset($category);

		foreach($categories as $id => $category) {
			$parentId = (int)($category['parentid'] ?? 0);

			if($parentId > 0 && isset($categories[$parentId])) {
				$categories[$parentId]['childs'][] = (int)$id;
			}
		}

		return $categories;
	}

	protected function getEmailMessagesByContact($contacts, int $campaignId): array
	{
		$contactIds = [];

		foreach($contacts as $contact) {
			$contactIds[] = is_array($contact) ? (int)$contact['id'] : (int)$contact->id;
		}

		$contactIds = array_values(array_unique(array_filter($contactIds)));

		if(!$contactIds) return [];

		$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
		$adapter = $emailmessageDb->getAdapter();

		$where = [
			$adapter->quoteInto('parentid = ?', $campaignId),
			$adapter->quoteInto('module = ?', 'campaigns'),
			$adapter->quoteInto('controller = ?', 'campaign'),
			$adapter->quoteInto('clientid = ?', $emailmessageDb->getClientId()),
			$adapter->quoteInto('contactid IN (?)', $contactIds),
			$adapter->quoteInto('deleted = ?', 0),
		];

		$rows = $emailmessageDb->fetchAll($where, 'id DESC')->toArray();
		$messages = [];

		foreach($rows as $row) {
			$messages[(int)$row['contactid']][] = $row;
		}

		return $messages;
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

	public function getCampaignRecipientStatus(array $campaign): array
	{
		$emailDb = new Contacts_Model_DbTable_Email();
		$dbConfig = $emailDb->getAdapter()->getConfig();

		$categoryDb = new Application_Model_DbTable_Category();
		$categories = $categoryDb->getCategories('contact');

		$emailaddress = new DEEC_Emailaddress(
			BASE_PATH,
			$dbConfig['host'],
			$dbConfig['username'],
			$dbConfig['password'],
			$dbConfig['dbname']
		);

		return $emailaddress->getCampaignRecipientStatus(
			(int)$campaign['clientid'],
			(int)$campaign['contactcatid'],
			(bool)$campaign['contactsubcat'],
			(int)$campaign['id'],
			$categories
		);
	}
}
