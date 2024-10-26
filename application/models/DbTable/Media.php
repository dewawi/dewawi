<?php

class Application_Model_DbTable_Media extends Zend_Db_Table_Abstract
{

	protected $_name = 'media';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getMedia($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getMediaByParentID($parentid, $module, $controller)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function addMedia($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateMedia($id, $title)
	{
		$id = (int)$id;
		$data = array();
		$data['title'] = $title;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function getMaxOrdering($parentid, $type)
	{
		$select = $this->select()
			->from($this, array('max_ordering' => new Zend_Db_Expr('MAX(ordering)')))
			->where('type = ?', $type)
			->where('parentid = ?', $parentid)
			->where('deleted = ?', 0);
		
		$result = $this->fetchRow($select);
		
		if ($result) {
			return (int) $result->max_ordering;
		} else {
			return 0;
		}
	}

	public function lock($id)
	{
		$id = (int)$id;
		$data = array();
		$data['locked'] = $this->_user['id'];
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

	public function deleteMedia($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteMediaByParentID($parentid, $module, $controller)
	{
		$parentid = (int)$parentid;
		$data = array('deleted' => 1);
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$this->update($data, $where);
	}
}
