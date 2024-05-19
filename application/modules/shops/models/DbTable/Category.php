<?php

class Shops_Model_DbTable_Category extends Zend_Db_Table_Abstract
{

	protected $_name = 'category';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function getCategory($categoryid, $shopid)
	{
		$categoryid = (int)$categoryid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('catid = ?', $catid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getCategoryBySlug($slug, $shopid)
	{
		$shopid = (int)$shopid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('type = ?', 'shop');
		$where[] = $this->getAdapter()->quoteInto('slug = ?', $slug);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'ordering');

		return $data;
	}

	public function getCategories($shopid)
	{
		$shopid = (int)$shopid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('type = ?', 'shop');
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		return $data;
	}
}
