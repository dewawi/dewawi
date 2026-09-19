<?php

class Admin_Model_DbTable_Slug extends DEEC_Model_DbTable_Entity
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
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : [];
	}

	public function getEntitySlug($module, $controller, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : [];
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
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = array();
		$data['parentid'] = (int)$parentid;
		if($slug) $data['slug'] = $slug;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];

		$this->update($data, $where);
	}

	public function saveSlug($module, $controller, $shopid, $parentid, $entityid, $slug = null)
	{
		$existing = $this->getEntitySlug($module, $controller, $entityid);

		if ((int)$shopid <= 0) {
			if ($existing) {
				$this->deleteSlugById((int)$existing['id']);
			}

			return 0;
		}

		if ($existing) {
			$data = array();
			$data['shopid'] = (int)$shopid;
			$data['parentid'] = (int)$parentid;

			if ($slug !== null) {
				$slug = trim((string)$slug, '/');
				$data['slug'] = $slug !== '' ? $slug : (string)$entityid;
			}

			$data['modified'] = $this->_date;
			$data['modifiedby'] = $this->_user['id'];

			$this->update($data, 'id = ' . (int)$existing['id']);

			return (int)$existing['id'];
		}

		$slug = trim((string)$slug, '/');

		if ($slug === '') {
			$slug = (string)$entityid;
		}

		return $this->addSlug($module, $controller, $shopid, $parentid, $entityid, $slug);
	}

	public function sortSlug($id, $ordering)
	{
		$data = array();
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$data['ordering'] = $ordering;
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteSlug($module, $controller, $shopid, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$this->update(array('deleted' => 1), $where);
	}

	public function deleteSlugById($id)
	{
		$this->update(array(
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id'],
		), 'id = ' . (int)$id);
	}
}
