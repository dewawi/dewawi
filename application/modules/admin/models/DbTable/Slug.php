<?php

class Admin_Model_DbTable_Slug extends Zend_Db_Table_Abstract
{

	protected $_name = 'slug';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getSlug($module, $controller, $shopid, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', $entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);

		$row = $this->fetchRow($where);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addSlug($module, $controller, $shopid, $parentid, $entityid, $slug)
	{
		$data = array();
		$data['module'] = $module;
		$data['controller'] = $controller;
		$data['shopid'] = $shopid;
		$data['entityid'] = $entityid;
		$data['parentid'] = $parentid;
		$data['slug'] = $slug;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateSlug($module, $controller, $shopid, $parentid, $entityid, $slug = null)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', $entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);

		$data = array();
		$data['parentid'] = $parentid;
		if($slug) $data['slug'] = $slug;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];

		// Perform the update query
		$this->update($data, $where);
	}

	public function sortSlug($id, $ordering)
	{
		$data = array();
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$data['ordering'] = $ordering;
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id)
	{
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array();
		$data['locked'] = 0;
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteSlug($module, $controller, $shopid, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', $entityid);
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, $where);
	}
}
