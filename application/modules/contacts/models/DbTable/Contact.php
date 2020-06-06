<?php

class Contacts_Model_DbTable_Contact extends Zend_Db_Table_Abstract
{
	protected $_name = 'contact';

	public function getContact($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}

	public function getContacts($ids)
	{
		$row = $this->fetchAll("id IN ('" . implode("', '", $ids) . "')");
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
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

	public function addContact($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateContact($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id, $locked, $lockedtime)
	{
		$data = array(
			'locked' => $locked,
			'lockedtime' => $lockedtime
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array(
			'locked' => 0
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteContact($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
