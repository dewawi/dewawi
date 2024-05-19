<?php

class Admin_Model_DbTable_Tag extends Zend_Db_Table_Abstract
{

	protected $_name = 'tag';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getTag($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getTags($type, $parentid = null, $shopid = null)
	{
		$where = array();
		if($parentid !== null) {
			//$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		}
		$shopid = 100;
		if($shopid !== null) {
			$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		}
		//$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		$categories = array();
		foreach($data as $tag) {
			$categories[$tag->id]['id'] = $tag->id;
			//$categories[$tag->id]['type'] = $tag->type;
			$categories[$tag->id]['title'] = $tag->title;
			$categories[$tag->id]['slug'] = $tag->slug;
			//$categories[$tag->id]['image'] = $tag->image;
			$categories[$tag->id]['description'] = $tag->description;
			$categories[$tag->id]['footer'] = $tag->footer;
			$categories[$tag->id]['ordering'] = $tag->ordering;
		}

		return $categories;
	}

	public function addTag($data, $clientid = 0)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		if($clientid) {
			$data['clientid'] = $clientid;
		} else {
			$data['clientid'] = $this->_client['id'];
		}
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateTag($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortTag($id, $ordering)
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

	public function deleteTag($id)
	{
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, 'id =' . (int)$id);
	}
}
