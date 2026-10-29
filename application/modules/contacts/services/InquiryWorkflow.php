<?php

class Contacts_Service_InquiryWorkflow
{
	private int $contactClientId;
	private int $salesClientId;
	private int $itemClientId;

	public function __construct()
	{
		$client = Zend_Registry::get('Client');
		$defaultClientId = (int)($client['id'] ?? 0);
		$isChild = !empty($client['parentid']);
		$this->contactClientId = $isChild ? (int)($client['modules']['contacts'] ?? 0) : $defaultClientId;
		$this->salesClientId = $isChild ? (int)($client['modules']['sales'] ?? 0) : $defaultClientId;
		$this->itemClientId = $isChild ? (int)($client['modules']['items'] ?? 0) : $defaultClientId;

		if ($this->contactClientId <= 0) {
			throw new RuntimeException('Inquiry workflow client context is missing.');
		}
	}

	public function buildContext(array $inquiry): array
	{
		$defaults = $this->getContactDefaults($inquiry);

		return [
			'contact' => $this->getContact((int)($inquiry['contactid'] ?? 0)),
			'quote' => $this->getQuote((int)($inquiry['quoteid'] ?? 0)),
			'quote_linked' => (int)($inquiry['quoteid'] ?? 0) > 0,
			'suggestions' => $this->findContactSuggestions($defaults),
			'contact_defaults' => $defaults,
			'item' => $this->findItem($inquiry),
		];
	}

	public function getContactDefaults(array $inquiry): array
	{
		$data = json_decode((string)($inquiry['data'] ?? ''), true);
		$data = is_array($data) ? $data : [];
		$company = $this->first($data, ['firma', 'company', 'name1']);
		$firstname = $this->first($data, ['firstname', 'vorname']);
		$lastname = $this->first($data, ['lastname', 'nachname']);
		$person = $this->first($data, ['ansprechpartner', 'contactperson', 'name']);

		if ($person !== '' && $firstname === '' && $lastname === '') {
			$parts = preg_split('/\s+/', $person, 2);
			$firstname = count($parts) > 1 ? (string)$parts[0] : '';
			$lastname = (string)($parts[count($parts) - 1] ?? '');
		}

		$name1 = $company ?: trim($firstname.' '.$lastname);
		$email = $this->first($data, ['email', 'e-mail', 'mail']);
		if ($name1 === '') $name1 = $email;

		return [
			'name1' => $name1,
			'name2' => $company !== '' ? trim($firstname.' '.$lastname) : '',
			'firstname' => $firstname,
			'lastname' => $lastname,
			'street' => $this->first($data, ['street', 'strasse', 'straße', 'adresse', 'address']),
			'postcode' => $this->first($data, ['postcode', 'plz', 'zip']),
			'city' => $this->first($data, ['city', 'stadt']),
			'country' => $this->first($data, ['country', 'land']) ?: 'DE',
			'phone' => $this->first($data, ['phone', 'telefon', 'telephone']),
			'email' => $email,
			'confirm_duplicate' => 0,
		];
	}

	public function findContactSuggestions(array $data): array
	{
		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($this->contactClientId);
		$queries = array_values(array_unique(array_filter([
			trim((string)($data['email'] ?? '')),
			trim((string)($data['name1'] ?? '')),
			trim((string)($data['name2'] ?? '')),
		])));
		$suggestions = [];

		foreach ($queries as $query) {
			if (mb_strlen($query) < 2) continue;

			foreach ($contactDb->suggestContacts($query, $this->contactClientId, 5) as $suggestion) {
				$suggestions[(int)$suggestion['id']] = $suggestion;
			}
		}

		return array_values($suggestions);
	}

	public function assignContact(int $inquiryId, int $contactId): void
	{
		if ($contactId > 0 && !$this->getContact($contactId)) {
			throw new InvalidArgumentException('Invalid contact assignment.');
		}

		$inquiryDb = new Contacts_Model_DbTable_Inquiry();
		$adapter = $inquiryDb->getAdapter();

		try {
			$adapter->beginTransaction();
			if (!$inquiryDb->getByIdForUpdate($inquiryId)) throw new RuntimeException('Inquiry not found.');
			$inquiryDb->updateById($inquiryId, ['contactid' => $contactId ?: null]);
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function createContact(int $inquiryId, array $data): int
	{
		$inquiryDb = new Contacts_Model_DbTable_Inquiry();
		$adapter = $inquiryDb->getAdapter();
		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($this->contactClientId);
		$incrementDb = (new Application_Model_DbTable_Increment())->setClientId($this->contactClientId);

		try {
			$adapter->beginTransaction();
			$inquiry = $inquiryDb->getByIdForUpdate($inquiryId);
			if (!$inquiry) throw new RuntimeException('Inquiry not found.');
			if (!empty($inquiry['contactid'])) throw new RuntimeException('Inquiry already has a contact.');

			$contactNumber = (int)$incrementDb->getIncrement('contactid', true);
			while ($contactDb->fetchRow($contactDb->select()
				->where('contactid = ?', $contactNumber)
				->where('clientid = ?', $this->contactClientId)
				->where('deleted = ?', 0)
			)) $contactNumber++;

			$contactId = $contactDb->create([
				'contactid' => $contactNumber,
				'catid' => 0,
				'name1' => trim((string)$data['name1']),
				'name2' => trim((string)($data['name2'] ?? '')),
			]);
			$incrementDb->setIncrement($contactNumber, 'contactid');

			$roleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId($this->contactClientId);
			$roleDb->syncForContact($contactId, ['prospect']);

			if ($this->hasAny($data, ['street', 'postcode', 'city'])) {
				$addressDb = (new Contacts_Model_DbTable_Address())->setClientId($this->contactClientId);
				$addressDb->createForParent($contactId, 'contacts', 'contact', [
					'type' => 'billing',
					'street' => trim((string)($data['street'] ?? '')),
					'postcode' => trim((string)($data['postcode'] ?? '')),
					'city' => trim((string)($data['city'] ?? '')),
					'country' => trim((string)($data['country'] ?? '')),
				]);
			}

			if (trim((string)($data['phone'] ?? '')) !== '') {
				$phoneDb = (new Contacts_Model_DbTable_Phone())->setClientId($this->contactClientId);
				$phoneDb->createForParent($contactId, 'contacts', 'contact', [
					'type' => 'phone',
					'phone' => trim((string)$data['phone']),
				]);
			}

			$emailParentId = $contactId;
			$emailController = 'contact';
			if ($this->hasAny($data, ['firstname', 'lastname'])) {
				$personDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($this->contactClientId);
				$emailParentId = $personDb->createForParent($contactId, 'contacts', 'contact', [
					'firstname' => trim((string)($data['firstname'] ?? '')),
					'lastname' => trim((string)($data['lastname'] ?? '')) ?: trim((string)$data['name1']),
				]);
				$emailController = 'contactperson';
			}

			if (trim((string)($data['email'] ?? '')) !== '') {
				$emailDb = (new Contacts_Model_DbTable_Email())->setClientId($this->contactClientId);
				$emailDb->createForParent($emailParentId, 'contacts', $emailController, [
					'email' => trim((string)$data['email']),
					'password' => password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT),
				]);
			}

			$inquiryDb->updateById($inquiryId, ['contactid' => $contactId]);
			$adapter->commit();
			return $contactId;
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function createQuoteDraft(int $inquiryId): array
	{
		if ($this->salesClientId <= 0) throw new RuntimeException('Sales client context is missing.');

		$inquiryDb = new Contacts_Model_DbTable_Inquiry();
		$adapter = $inquiryDb->getAdapter();

		try {
			$adapter->beginTransaction();
			$inquiry = $inquiryDb->getByIdForUpdate($inquiryId);
			if (!$inquiry) throw new RuntimeException('Inquiry not found.');

			if (!empty($inquiry['quoteid'])) {
				$quote = $this->getQuote((int)$inquiry['quoteid']);
				if (!$quote) throw new RuntimeException('Linked quote is not available.');
				$adapter->commit();
				return ['id' => (int)$quote['id'], 'created' => false];
			}

			$contactId = (int)($inquiry['contactid'] ?? 0);
			if (!$this->getContact($contactId)) throw new RuntimeException('A customer must be assigned first.');

			$data = json_decode((string)($inquiry['data'] ?? ''), true);
			$data = is_array($data) ? $data : [];
			$quoteData = (new Sales_Service_CreateDataFactory())->build('quote', $contactId);
			$quoteData['subject'] = $this->first($data, ['subject', 'betreff', 'titel']);
			$quoteData['reference'] = $this->first($data, ['projektname', 'project', 'reference']);
			$quoteData['info'] = $this->first($data, ['message', 'nachricht']);
			$quoteData['subtotal'] = 0;
			$quoteData['taxes'] = 0;
			$quoteData['total'] = 0;

			$quoteDb = (new Sales_Model_DbTable_Quote())->setClientId($this->salesClientId);
			$quoteId = $quoteDb->create($quoteData);
			$item = $this->findItem($inquiry);

			if ($item) {
				$quantity = (float)$this->first($data, ['quantity', 'menge', 'qty']);
				if ($quantity <= 0) $quantity = 1;
				$price = (float)((float)($item['specialprice'] ?? 0) > 0 ? $item['specialprice'] : ($item['price'] ?? 0));
				$taxrate = (float)$adapter->fetchOne($adapter->select()
					->from('taxrate', ['rate'])
					->where('id = ?', (int)($item['taxid'] ?? 0))
					->where('clientid = ?', $this->itemClientId)
					->where('deleted = ?', 0)
					->limit(1));
				$uom = (string)$adapter->fetchOne($adapter->select()
					->from('uom', ['title'])
					->where('id = ?', (int)($item['uomid'] ?? 0))
					->where('clientid = ?', $this->itemClientId)
					->where('deleted = ?', 0)
					->limit(1));
				$positionDb = (new Sales_Model_DbTable_Quotepos())->setClientId($this->salesClientId);
				$positionDb->addPosition([
					'parentid' => $quoteId,
					'itemid' => (int)$item['id'],
					'possetid' => 0,
					'sku' => (string)$item['sku'],
					'title' => (string)$item['title'],
					'description' => (string)($item['description'] ?? ''),
					'price' => $price,
					'taxrate' => $taxrate,
					'quantity' => $quantity,
					'total' => $price * $quantity,
					'currency' => (string)$quoteData['currency'],
					'uom' => $uom,
				]);
				Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct($quoteId, 'sales', 'quote');
			}

			$inquiryDb->updateById($inquiryId, ['quoteid' => $quoteId]);
			$adapter->commit();
			return ['id' => $quoteId, 'created' => true];
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	private function getContact(int $id): ?array
	{
		if ($id <= 0) return null;
		return (new Contacts_Model_DbTable_Contact())->setClientId($this->contactClientId)->getById($id);
	}

	private function getQuote(int $id): ?array
	{
		if ($id <= 0 || $this->salesClientId <= 0) return null;
		return (new Sales_Model_DbTable_Quote())->setClientId($this->salesClientId)->getById($id);
	}

	private function findItem(array $inquiry): ?array
	{
		if ($this->itemClientId <= 0) return null;
		$data = json_decode((string)($inquiry['data'] ?? ''), true);
		$data = is_array($data) ? $data : [];
		$sku = $this->first($data, ['sku', 'item_sku', 'artikelnummer']);
		if ($sku === '') return null;

		$itemDb = (new Items_Model_DbTable_Item())->setClientId($this->itemClientId);
		$item = $itemDb->getItemBySKU($sku);
		if (!$item) return null;

		$count = (int)$itemDb->getAdapter()->fetchOne($itemDb->getAdapter()->select()
			->from('item', ['COUNT(*)'])
			->where('sku = ?', $sku)
			->where('clientid = ?', $this->itemClientId)
			->where('deleted = ?', 0));

		return $count === 1 ? $item : null;
	}

	private function first(array $data, array $keys): string
	{
		foreach ($keys as $key) {
			$value = $data[$key] ?? null;
			if (is_scalar($value) && trim((string)$value) !== '') return trim((string)$value);
		}
		return '';
	}

	private function hasAny(array $data, array $keys): bool
	{
		foreach ($keys as $key) {
			if (trim((string)($data[$key] ?? '')) !== '') return true;
		}
		return false;
	}
}
