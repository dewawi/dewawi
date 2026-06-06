<?php

class Contacts_Model_DbTable_Contact extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'contact';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getInfo()
	{
		return $this->info(Zend_Db_Table_Abstract::COLS);
	}

	public function getContact($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}

	public function getContactForEdit($id)
	{
		$id = (int)$id;

		$where = [];
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : null;
	}

	public function getContactWithID($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$row = $this->fetchRow($where);
		if(!$row) return false;
		return $row->toArray();
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
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addContact($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateContact($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteContact($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
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
				"a.parentid = c.id
					AND a.module = 'contacts'
					AND a.controller = 'contact'
					AND a.type = 'billing'",
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
