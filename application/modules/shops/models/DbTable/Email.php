<?php

class Shops_Model_DbTable_Email extends Zend_Db_Table_Abstract
{

	protected $_name = 'email';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getEmail($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}

	public function getEmails($parentid, $module = 'contacts', $controller = 'contact')
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		if(!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data->toArray();
	}

	public function findContactIdByEmail(string $email)
	{
		$select = $this->select()
			->from($this->_name, ['parentid'])
			->where('module = ?', 'contacts')
			->where('controller = ?', 'contact')
			->where('clientid = ?', $this->_shop['clientid'])
			->where('deleted = ?', 0)
			->where('email = ?', trim($email))
			->limit(1);

		$row = $this->fetchRow($select);
		return $row ? (int)$row['parentid'] : 0; // parentid is the CONTACT ID
	}

	public function addEmail($data)
	{
		$data['clientid'] = $this->_shop['clientid'];
		$data['created'] = $this->_date;
		//$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateEmail($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		//$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteEmail($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
