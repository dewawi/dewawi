<?php

class Application_Model_DbTable_Tagentity extends Zend_Db_Table_Abstract
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
		$tagEntityDb = new Application_Model_DbTable_Tagentity();
		$tags = $tagEntityDb->fetchAll(
			$tagEntityDb->select()
				->setIntegrityCheck(false)
				->from(array('t' => 'tagentity'))
				->joinLeft(array('tag' => 'tag'), 't.tagid = tag.id', array('title as tag', 'module', 'controller'))
				->group('t.id')
				->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.clientid = "'.$this->_client['id'].'") AND (t.deleted = 0)')
				//->order($order.' '.$params['sort'])
				//->limit($params['limit'], $params['offset'])
		);
		return $tags->toArray();
	}

	public function addTagEntity($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateTagEntity($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteTagEntity($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
