<?php

class Application_Model_DbTable_Tagentity extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'tagentity';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getTagEntities($module, $controller, $id)
	{
		$tags = $this->fetchAll(
			$this->select()
				->setIntegrityCheck(false)
				->from(array('t' => 'tagentity'))
				->joinLeft(
					array('tag' => 'tag'),
					't.tagid = tag.id AND tag.clientid = t.clientid AND tag.module = t.module AND tag.controller = t.controller AND tag.deleted = 0',
					array('title as tag')
				)
				->group('t.id')
				->where('t.entityid = ?', $id)
				->where('t.module = ?', $module)
				->where('t.controller = ?', $controller)
				->where('t.clientid = ?', $this->getClientId())
				->where('t.deleted = ?', 0)
				->order(array('t.ordering ASC', 't.id ASC'))
		);
		return $tags->toArray();
	}

	public function addTagEntity($data)
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateTagEntity($id, $data)
	{
		$data = array_intersect_key($data, array('tagid' => true));
		if($data) $this->updateById((int)$id, $data);
	}

	public function deleteTagEntity($id)
	{
		$this->updateById((int)$id, array('deleted' => 1));
	}
}
