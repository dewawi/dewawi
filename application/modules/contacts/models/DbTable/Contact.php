<?php

class Contacts_Model_DbTable_Contact extends DEEC_Model_DbTable_Entity
{
	private const DELETE_DOCUMENT_REFERENCES = [
		Sales_Model_DbTable_Quote::class => ['contactid'],
		Sales_Model_DbTable_Salesorder::class => ['contactid'],
		Sales_Model_DbTable_Invoice::class => ['contactid'],
		Sales_Model_DbTable_Deliveryorder::class => ['contactid'],
		Sales_Model_DbTable_Creditnote::class => ['contactid'],
		Sales_Model_DbTable_Reminder::class => ['contactid'],
		Purchases_Model_DbTable_Quoterequest::class => ['contactid'],
		Purchases_Model_DbTable_Purchaseorder::class => ['contactid'],
	];

	private const DELETE_OPERATION_REFERENCES = [
		Processes_Model_DbTable_Process::class => ['contactid', 'supplierid'],
		Tasks_Model_DbTable_Task::class => ['contactid', 'supplierid'],
	];

	private const DELETE_INTERNAL_OPERATION_REFERENCES = [
		Items_Model_DbTable_Stockplanning::class => ['contactid'],
		Shops_Model_DbTable_Order::class => ['contactid'],
	];

	private const DELETE_COMMUNICATION_REFERENCES = [
		Contacts_Model_DbTable_Emailmessage::class => ['contactid'],
		Contacts_Model_DbTable_Downloadtracking::class => ['contactid'],
	];

	private const DELETE_NUMBER_COMMUNICATION_REFERENCES = [
		Application_Model_DbTable_Feedback::class => ['contactid'],
	];

	protected $_name = 'contact';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;
	private array $deleteReferenceClientIds = [];

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getContact($id)
	{
		return $this->getById((int)$id) ?: false;
	}

	public function getContactForEdit($id)
	{
		return $this->getById((int)$id);
	}

	public function getContactWithID($contactid)
	{
		if (
			!(is_int($contactid) && $contactid > 0)
			&& !(is_string($contactid) && preg_match('/^[1-9][0-9]*$/D', trim($contactid)))
		) {
			return false;
		}

		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->getClientId());
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$row = $this->fetchRow($where);
		if(!$row) return false;
		return $row->toArray();
	}

	public function getActiveImportContacts(): array
	{
		$rows = $this->fetchAll(
			$this->select()
				->from($this->_name, ['id', 'contactid', 'catid', 'name1', 'name2', 'department'])
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->order('id ASC')
		);

		return $rows ? $rows->toArray() : [];
	}

	public function getContactsByCategory($catid)
	{
		$catid = (int)$catid;
		$row = $this->fetchAll('catid = ' . $catid);
		if (!$row) {
			throw new Exception("Could not find row $catid");
		}
		return $row->toArray();
	}

	public function getLatestContacts()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->getClientId());
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addContact($data)
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateContact($id, $data)
	{
		$this->updateById((int)$id, $data);
	}

	public function deleteContact($id)
	{
		$this->deleteById((int)$id);
	}

	public function canDelete(array $row): bool
	{
		return $this->getDeleteBlockReason($row) === null;
	}

	public function getDeleteBlockReason(array $row): ?string
	{
		$id = (int)($row['id'] ?? 0);
		$contactNumber = (int)($row['contactid'] ?? 0);

		if($id <= 0) return 'MESSAGES_CANNOT_BE_DELETED';

		$clientIds = $this->getDeleteReferenceClientIds();

		if($contactNumber > 0 && $this->hasActiveReference(self::DELETE_DOCUMENT_REFERENCES, $contactNumber, $clientIds)) {
			return 'MESSAGES_CONTACT_DELETE_BLOCKED_DOCUMENTS';
		}

		if(($contactNumber > 0 && $this->hasActiveReference(self::DELETE_OPERATION_REFERENCES, $contactNumber, $clientIds))
			|| $this->hasActiveReference(self::DELETE_INTERNAL_OPERATION_REFERENCES, $id, $clientIds)
			|| $this->hasActiveTableReference('shopinquirydata', ['contactid'], $id, $clientIds)) {
			return 'MESSAGES_CONTACT_DELETE_BLOCKED_OPERATIONS';
		}

		if($this->hasActiveReference(self::DELETE_COMMUNICATION_REFERENCES, $id, $clientIds)
			|| ($contactNumber > 0 && $this->hasActiveReference(self::DELETE_NUMBER_COMMUNICATION_REFERENCES, $contactNumber, $clientIds))) {
			return 'MESSAGES_CONTACT_DELETE_BLOCKED_COMMUNICATIONS';
		}

		return null;
	}

	protected function getDeleteReferenceClientIds(): array
	{
		$ownerId = $this->getClientId();
		if(isset($this->deleteReferenceClientIds[$ownerId])) return $this->deleteReferenceClientIds[$ownerId];

		$clientDb = new Application_Model_DbTable_Client();
		$rows = $clientDb->fetchAll(
			$clientDb->select()
				->from('client', ['id'])
				->where('parentid = ?', $ownerId)
				->where('deleted = ?', 0)
		);
		$clientIds = [$ownerId];

		foreach($rows as $row) $clientIds[] = (int)$row->id;

		return $this->deleteReferenceClientIds[$ownerId] = array_values(array_unique($clientIds));
	}

	protected function hasActiveReference(array $references, int $value, array $clientIds): bool
	{
		foreach($references as $class => $fields) {
			$db = new $class();

			if($this->hasActiveTableReference($db->info(Zend_Db_Table_Abstract::NAME), $fields, $value, $clientIds)) return true;
		}

		return false;
	}

	protected function hasActiveTableReference(string $table, array $fields, int $value, array $clientIds): bool
	{
		$conditions = [];

		foreach($fields as $field) $conditions[] = $this->getAdapter()->quoteInto($field . ' = ?', $value);

		return (bool)$this->getAdapter()->fetchRow(
			$this->getAdapter()->select()
				->from($table, ['id'])
				->where('(' . implode(' OR ', $conditions) . ')')
				->where('clientid IN (?)', $clientIds)
				->where('deleted = ?', 0)
				->limit(1)
		);
	}

	public function suggestContacts(string $keyword, int $clientId, int $limit = 10): array
	{
		$db = $this->getAdapter();

		$select = $this->select()
			->setIntegrityCheck(false)
			->from(['c' => 'contact'], [
				'id',
				'contactid',
				'name1',
				'name2',
			])
			->joinLeft(
				['a' => 'address'],
				"a.id = (
					SELECT MIN(a2.id)
					FROM address AS a2
					WHERE a2.parentid = c.id
						AND a2.clientid = c.clientid
						AND a2.module = 'contacts'
						AND a2.controller = 'contact'
						AND a2.type = 'billing'
						AND a2.deleted = 0
				)",
				[
					'street',
					'postcode',
					'city',
					'country',
				]
			)
			->where('c.clientid = ?', $clientId)
			->where('c.deleted = ?', 0)
			->order('c.name1 ASC')
			->limit($limit);

		$words = preg_split('/\s+/', trim($keyword));

		foreach ($words as $word) {
			if ($word === '') {
				continue;
			}

			$like = '%' . $word . '%';

			$select->where(
				'(
					c.id LIKE ' . $db->quote($like) . '
					OR c.contactid LIKE ' . $db->quote($like) . '
					OR c.name1 LIKE ' . $db->quote($like) . '
					OR c.name2 LIKE ' . $db->quote($like) . '
					OR a.street LIKE ' . $db->quote($like) . '
					OR a.postcode LIKE ' . $db->quote($like) . '
					OR a.city LIKE ' . $db->quote($like) . '
					OR EXISTS (
						SELECT 1 FROM email AS ce
						WHERE ce.parentid = c.id
							AND ce.clientid = c.clientid
							AND ce.module = \'contacts\'
							AND ce.controller = \'contact\'
							AND ce.deleted = 0
							AND ce.email LIKE ' . $db->quote($like) . '
					)
					OR EXISTS (
						SELECT 1
						FROM contactperson AS cp
						LEFT JOIN email AS cpe
							ON cpe.parentid = cp.id
							AND cpe.clientid = cp.clientid
							AND cpe.module = \'contacts\'
							AND cpe.controller = \'contactperson\'
							AND cpe.deleted = 0
						WHERE cp.parentid = c.id
							AND cp.clientid = c.clientid
							AND cp.module = \'contacts\'
							AND cp.controller = \'contact\'
							AND cp.deleted = 0
							AND (
								cp.firstname LIKE ' . $db->quote($like) . '
								OR cp.lastname LIKE ' . $db->quote($like) . '
								OR cp.name1 LIKE ' . $db->quote($like) . '
								OR cp.name2 LIKE ' . $db->quote($like) . '
								OR cpe.email LIKE ' . $db->quote($like) . '
							)
					)
				)'
			);
		}

		$rows = $this->fetchAll($select);

		$items = [];

		foreach ($rows as $row) {
			$address = trim($row->street . ', ' . $row->postcode . ' ' . $row->city);
			$label = trim($row->contactid . ' · ' . $row->name1 . ' ' . $row->name2);

			$items[] = [
				'id' => (int)$row->id,
				'contactid' => (string)$row->contactid,
				'label' => $label,
				'subtitle' => $address,
			];
		}

		return $items;
	}
}
