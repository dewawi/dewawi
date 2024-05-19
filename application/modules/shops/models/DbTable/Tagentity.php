<?php

class Shops_Model_DbTable_Tagentity extends Zend_Db_Table_Abstract
{

	protected $_name = 'tagentity';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function getTagEntities($module, $controller, $id)
	{
		$tags = $this->fetchAll(
			$this->select()
				->setIntegrityCheck(false)
				->from(array('t' => 'tagentity'))
				->joinLeft(array('tag' => 'tag'), 't.tagid = tag.id', array('title as tag', 'module', 'controller'))
				->group('t.id')
				->where('(t.tagid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.deleted = 0)')
				//->order($order.' '.$params['sort'])
				//->limit($params['limit'], $params['offset'])
		);
		return $tags->toArray();
	}
}
