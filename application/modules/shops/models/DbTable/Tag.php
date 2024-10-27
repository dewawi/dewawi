<?php

class Shops_Model_DbTable_Tag extends Zend_Db_Table_Abstract
{

	protected $_name = 'tag';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function getTag($id)
	{
		$id = (int)$id;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'ordering');

		return $data;
	}

	public function getTags($module, $controller, $id = null)
	{
		$id = (int)$id;
		$where = array();
		//$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $id");
		}
		$tags = array();
		foreach($data as $tag) {
			$tags[$tag->id] = $tag;
		}
		return $tags;
	}
}
