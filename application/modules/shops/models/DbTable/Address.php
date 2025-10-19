<?php

class Shops_Model_DbTable_Address extends Zend_Db_Table_Abstract
{

	protected $_name = 'address';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getAddress($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		if(!$data) {
			throw new Exception("Could not find row $contactid");
		}
		return $data->toArray();
	}

	public function getAddresses(int $contactId): array
	{
		$where = [
			$this->getAdapter()->quoteInto('contactid = ?', $contactId),
			$this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];
		$rows = $this->fetchAll($where, 'id ASC');
		return $rows ? $rows->toArray() : [];
	}

	public function addAddress($data)
	{
		$data['clientid'] = $this->_shop['clientid'];
		$data['created'] = $this->_date;
		//$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateAddress($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		//$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteAddress($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
