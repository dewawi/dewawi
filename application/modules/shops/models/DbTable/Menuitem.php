<?php

class Shops_Model_DbTable_Menuitem extends Zend_Db_Table_Abstract
{

	protected $_name = 'menuitem';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function getMenuitem($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getMenuitems($menuid)
	{
		$menuid = (int)$menuid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('menuid = ?', $menuid);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		return $data;
	}

	public function addMenuitem($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		//$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateMenuitem($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		//$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function lock($id)
	{
		$id = (int)$id;
		$data = array();
		//$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function unlock($id)
	{
		$id = (int)$id;
		$data = array('locked' => 0);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteMenuitem($itemid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$this->delete($where);
	}
}
