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
		if($shopid !== null) {
			$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		}
		//$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		$tags = array();
		foreach($data as $tag) {
			$tags[$tag->id]['id'] = $tag->id;
			$tags[$tag->id]['shopid'] = $tag->shopid;
			$tags[$tag->id]['title'] = $tag->title;
			$tags[$tag->id]['description'] = $tag->description;
			$tags[$tag->id]['footer'] = $tag->footer;
			$tags[$tag->id]['ordering'] = $tag->ordering;
		}

		return $tags;
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
