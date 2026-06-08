<?php

class Application_Model_DbTable_Media extends DEEC_Model_DbTable_Entity
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

	public function getCategoryMedia($categories) {
		$images = array();
		foreach($categories as $key => $category) {
			$images[$category['id']] = $this->getMedia($category['id']);
		}
		//print_r($images);
		return $images;
	}

	public function getMediaByParentID($parentid, $module, $controller)
	{
		$select = $this->select()
			->where('parentid = ?', (int)$parentid)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0)
			->order('ordering ASC')
			->order('id ASC');

		return $this->fetchAll($select);
	}

	public function getMediaByContext(string $module, string $controller, int $parentId, ?string $type = null): array
	{
		$select = $this->select()
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('parentid = ?', $parentId)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0)
			->order('ordering ASC');

		if ($type !== null && $type !== '') {
			$select->where('type = ?', $type);
		}

		return $this->fetchAll($select)->toArray();
	}

	public function getMaxOrderingByContext(string $module, string $controller, int $parentId, string $type): int
	{
		$select = $this->select()
			->from($this, ['max_ordering' => new Zend_Db_Expr('MAX(ordering)')])
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('parentid = ?', $parentId)
			->where('type = ?', $type)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0);

		$row = $this->fetchRow($select);

		return $row ? (int)$row->max_ordering : 0;
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
