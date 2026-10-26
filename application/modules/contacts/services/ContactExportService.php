<?php

class Contacts_Service_ContactExportService
{
	public const PROFILE_COMPANIES = 'companies';
	public const PROFILE_CONTACTPERSONS = 'contactpersons';

	public function createList(string $profile, $contacts, array $context = []): DEEC_List
	{
		if (!in_array($profile, [self::PROFILE_COMPANIES, self::PROFILE_CONTACTPERSONS], true)) {
			throw new InvalidArgumentException('Invalid contact export profile.');
		}

		$contacts = $this->normalizeContacts($contacts);
		$clientId = $this->getContactClientId($contacts);
		$related = $this->loadRelatedData($contacts, $clientId, $context);
		$items = $profile === self::PROFILE_CONTACTPERSONS
			? $this->buildContactpersonRows($contacts, $related, $context)
			: $this->buildCompanyRows($contacts, $related, $context);

		return new DEEC_List([
			'items' => $items,
			'columns' => $this->getColumns($profile),
			'view' => $context['view'] ?? null,
			'module' => 'contacts',
			'controller' => 'contact',
			'selectable' => false,
		]);
	}

	public function export(string $filePath, $contacts, array $context = []): array
	{
		$profile = (string)($context['profile'] ?? self::PROFILE_COMPANIES);
		$file = 'contacts-' . $profile . '-' . date('Ymd-His') . '.csv';
		$handle = fopen($filePath . $file, 'wb');

		if ($handle === false) {
			throw new RuntimeException('Unable to create contact export file.');
		}

		try {
			$this->createList($profile, $contacts, $context)->writeCsv($handle);
		} finally {
			fclose($handle);
		}

		return [
			'name' => $file,
			'path' => $filePath . $file,
		];
	}

	private function normalizeContacts($contacts): array
	{
		$normalized = [];

		foreach ($contacts as $contact) {
			if (is_object($contact) && method_exists($contact, 'toArray')) {
				$contact = $contact->toArray();
			} elseif (is_object($contact)) {
				$contact = get_object_vars($contact);
			}

			if (!is_array($contact) || (int)($contact['id'] ?? 0) <= 0) {
				continue;
			}

			$id = (int)$contact['id'];
			if (!isset($normalized[$id])) {
				$normalized[$id] = $contact;
			}
		}

		return array_values($normalized);
	}

	private function getContactClientId(array $contacts): int
	{
		if (!$contacts) {
			return 0;
		}

		$clientIds = [];

		foreach ($contacts as $contact) {
			$clientId = (int)($contact['clientid'] ?? 0);
			if ($clientId <= 0) {
				throw new RuntimeException('Contact export requires an authorized client context.');
			}
			$clientIds[$clientId] = true;
		}

		if (count($clientIds) !== 1) {
			throw new RuntimeException('Contact export cannot combine multiple client contexts.');
		}

		return (int)array_key_first($clientIds);
	}

	protected function loadRelatedData(array $contacts, int $clientId, array $context): array
	{
		$contactIds = array_map(static function(array $contact): int {
			return (int)$contact['id'];
		}, $contacts);

		if (!$contactIds) {
			return [
				'addresses' => [],
				'phones' => [],
				'emails' => [],
				'internets' => [],
				'contactpersons' => [],
				'contactpersonEmails' => [],
				'categories' => [],
				'tags' => [],
				'roles' => [],
			];
		}

		$addressDb = (new Contacts_Model_DbTable_Address())->setClientId($clientId);
		$phoneDb = (new Contacts_Model_DbTable_Phone())->setClientId($clientId);
		$emailDb = (new Contacts_Model_DbTable_Email())->setClientId($clientId);
		$internetDb = (new Contacts_Model_DbTable_Internet())->setClientId($clientId);
		$contactpersonDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($clientId);

		$contactpersons = $contactpersonDb->getByParentIds($contactIds, 'contacts', 'contact');
		$contactpersonIds = array_map(static function(array $contactperson): int {
			return (int)$contactperson['id'];
		}, $contactpersons);

		$categoryDb = (new Application_Model_DbTable_Category())->setClientId($clientId);
		$categories = $categoryDb->getCategories('contact');

		$tags = $context['tagEntities'] ?? null;
		if (!is_array($tags)) {
			$tagEntityDb = (new Application_Model_DbTable_Tagentity())->setClientId($clientId);
			$tags = $tagEntityDb->getTagEntitiesForParents('contacts', 'contact', $contactIds);
		}

		$roles = $context['contactRoles'] ?? null;
		if (!is_array($roles)) {
			$contactRoleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId($clientId);
			$roles = $contactRoleDb->getForContacts($contactIds);
		}

		return [
			'addresses' => $this->groupRows($addressDb->getByParentIds($contactIds, 'contacts', 'contact')),
			'phones' => $this->groupRows($phoneDb->getByParentIds($contactIds, 'contacts', 'contact')),
			'emails' => $this->groupRows($emailDb->getByParentIds($contactIds, 'contacts', 'contact')),
			'internets' => $this->groupRows($internetDb->getByParentIds($contactIds, 'contacts', 'contact')),
			'contactpersons' => $this->groupRows($contactpersons),
			'contactpersonEmails' => $this->groupRows($emailDb->getByParentIds($contactpersonIds, 'contacts', 'contactperson')),
			'categories' => $categories,
			'tags' => $tags,
			'roles' => $roles,
		];
	}

	private function groupRows(array $rows): array
	{
		$grouped = [];

		foreach ($rows as $row) {
			$grouped[(int)$row['parentid']][] = $row;
		}

		return $grouped;
	}

	private function buildCompanyRows(array $contacts, array $related, array $context): array
	{
		$rows = [];

		foreach ($contacts as $contact) {
			$id = (int)$contact['id'];
			$address = $this->selectAddress($related['addresses'][$id] ?? []);

			$rows[] = [
				'contactid' => $this->plain($contact['contactid'] ?? ''),
				'name1' => $this->plain($contact['name1'] ?? ''),
				'name2' => $this->plain($contact['name2'] ?? ''),
				'department' => $this->plain($contact['department'] ?? ''),
				'street' => $this->plain($address['street'] ?? ''),
				'postcode' => $this->plain($address['postcode'] ?? ''),
				'city' => $this->plain($address['city'] ?? ''),
				'country' => $this->plain($address['country'] ?? ''),
				'phone' => $this->joinField($related['phones'][$id] ?? [], 'phone'),
				'email' => $this->joinField($related['emails'][$id] ?? [], 'email'),
				'internet' => $this->joinField($related['internets'][$id] ?? [], 'internet'),
				'category' => $this->getCategory($contact, $related['categories']),
				'tags' => $this->joinField($related['tags'][$id] ?? [], 'tag'),
				'roles' => $this->joinRoles($related['roles'][$id] ?? [], $context),
			];
		}

		return $rows;
	}

	private function buildContactpersonRows(array $contacts, array $related, array $context): array
	{
		$rows = [];

		foreach ($contacts as $contact) {
			$contactId = (int)$contact['id'];
			$category = $this->getCategory($contact, $related['categories']);
			$tags = $this->joinField($related['tags'][$contactId] ?? [], 'tag');
			$roles = $this->joinRoles($related['roles'][$contactId] ?? [], $context);

			foreach ($related['contactpersons'][$contactId] ?? [] as $contactperson) {
				$personId = (int)$contactperson['id'];
				$rows[] = [
					'contactid' => $this->plain($contact['contactid'] ?? ''),
					'company' => $this->plain($contact['name1'] ?? ''),
					'firstname' => $this->plain($contactperson['firstname'] ?? ''),
					'lastname' => $this->plain($contactperson['lastname'] ?? ''),
					'salutation' => $this->plain($contactperson['salutation'] ?? ''),
					'title' => $this->plain($contactperson['title'] ?? ''),
					'position' => $this->plain($contactperson['position'] ?? ''),
					'department' => $this->plain($contactperson['department'] ?? ''),
					'email' => $this->joinField($related['contactpersonEmails'][$personId] ?? [], 'email'),
					'category' => $category,
					'tags' => $tags,
					'roles' => $roles,
				];
			}
		}

		return $rows;
	}

	private function selectAddress(array $addresses): array
	{
		$fallback = $addresses[0] ?? [];

		foreach ($addresses as $address) {
			if ((string)($address['type'] ?? '') === 'billing') {
				return $address;
			}
		}

		return $fallback;
	}

	private function getCategory(array $contact, array $categories): string
	{
		$category = $categories[(int)($contact['catid'] ?? 0)] ?? [];
		return $this->plain($category['breadcrumb'] ?? $category['title'] ?? '');
	}

	private function joinField(array $rows, string $field): string
	{
		$values = [];

		foreach ($rows as $row) {
			$value = $this->plain($row[$field] ?? '');
			if ($value !== '') {
				$values[$value] = true;
			}
		}

		return implode(', ', array_keys($values));
	}

	private function plain($value): string
	{
		$value = html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return trim(strip_tags($value));
	}

	private function joinRoles(array $roles, array $context): string
	{
		$labels = [];

		foreach (Contacts_Model_DbTable_Contactrole::ROLE_LABELS as $role => $translationKey) {
			if (!in_array($role, $roles, true)) {
				continue;
			}

			$label = $translationKey;
			$view = $context['view'] ?? null;

			if ($view && method_exists($view, 'translate')) {
				$label = $view->translate($translationKey);
			} elseif (Zend_Registry::isRegistered('DEEC_Translate')) {
				$translator = Zend_Registry::get('DEEC_Translate');
				if (method_exists($translator, 't')) {
					$label = $translator->t($translationKey);
				}
			}

			$labels[] = $this->plain($label);
		}

		return implode(', ', $labels);
	}

	private function getColumns(string $profile): array
	{
		if ($profile === self::PROFILE_CONTACTPERSONS) {
			return [
				['name' => 'contactid', 'label' => 'CONTACTS_CONTACT_ID'],
				['name' => 'company', 'label' => 'CONTACTS_EXPORT_COMPANY'],
				['name' => 'firstname', 'label' => 'CONTACTS_CONTACT_PERSONS_FIRSTNAME'],
				['name' => 'lastname', 'label' => 'CONTACTS_CONTACT_PERSONS_LASTNAME'],
				['name' => 'salutation', 'label' => 'CONTACTS_CONTACT_PERSONS_SALUTATION'],
				['name' => 'title', 'label' => 'CONTACTS_CONTACT_PERSONS_TITLE'],
				['name' => 'position', 'label' => 'CONTACTS_CONTACT_PERSONS_POSITION'],
				['name' => 'department', 'label' => 'CONTACTS_CONTACT_PERSONS_DEPARTMENT'],
				['name' => 'email', 'label' => 'CONTACTS_CONTACT_PERSONS_EMAIL'],
				['name' => 'category', 'label' => 'CONTACTS_CATEGORY'],
				['name' => 'tags', 'label' => 'CONTACTS_TAGS'],
				['name' => 'roles', 'label' => 'CONTACTS_ROLES'],
			];
		}

		return [
			['name' => 'contactid', 'label' => 'CONTACTS_CONTACT_ID'],
			['name' => 'name1', 'label' => 'CONTACTS_NAME_ORGANISATION'],
			['name' => 'name2', 'label' => 'CONTACTS_NAME_AFFIX'],
			['name' => 'department', 'label' => 'CONTACTS_DEPARTMENT'],
			['name' => 'street', 'label' => 'CONTACTS_STREET'],
			['name' => 'postcode', 'label' => 'CONTACTS_POSTCODE'],
			['name' => 'city', 'label' => 'CONTACTS_CITY'],
			['name' => 'country', 'label' => 'CONTACTS_COUNTRY'],
			['name' => 'phone', 'label' => 'CONTACTS_PHONE'],
			['name' => 'email', 'label' => 'CONTACTS_EMAIL'],
			['name' => 'internet', 'label' => 'CONTACTS_INTERNET'],
			['name' => 'category', 'label' => 'CONTACTS_CATEGORY'],
			['name' => 'tags', 'label' => 'CONTACTS_TAGS'],
			['name' => 'roles', 'label' => 'CONTACTS_ROLES'],
		];
	}
}
