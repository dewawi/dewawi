<?php

class Shops_Model_DbTable_Template extends Zend_Db_Table_Abstract
{

	protected $_name = 'template';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getTemplate($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where);
		if(!$data) {
			throw new Exception("Could not find row $id");
		}
		return $data->toArray();
	}

	public function getPrimaryTemplate()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'ordering');
		if(!$data) {
			throw new Exception("Could not find template");
		}
		return $data->toArray();
	}

	public function getDefaultTemplate()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('`default` = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where);
		if($data) {
			return $data->toArray();
		}
	}

	public function getTemplates()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$templates = array();
		foreach($data as $template) {
			$templates[$template->id] = $template->description;
		}
		return $templates;
	}
}
